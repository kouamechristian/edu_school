<?php

namespace App\Controller;

use App\Controller\Concern\HandlesEntityDeletion;
use App\Entity\Classroom;
use App\Entity\Level;
use App\Entity\PreRegistration;
use App\Entity\Registration;
use App\Form\RegistrationEnrollType;
use App\Form\RegistrationType;
use App\Repository\ClassroomRepository;
use App\Repository\LevelRepository;
use App\Repository\PreRegistrationRepository;
use App\Repository\RegistrationRepository;
use App\Service\EnrollmentService;
use App\Service\SchoolContextService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * CRUD des inscriptions (table registration).
 *
 * Une inscription part TOUJOURS d'une préinscription validée : à la création,
 * EnrollmentService crée l'élève (table student) à partir des informations de la
 * préinscription s'il n'existe pas encore, sinon réutilise l'élève existant
 * (réinscription, sans doublon). L'inscription garde un lien vers sa préinscription.
 */
#[Route('/admin/registrations', name: 'admin_registration_')]
#[IsGranted('ROLE_INSCRIPTION')]
class RegistrationController extends AbstractController
{
    use HandlesEntityDeletion;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private SchoolContextService $schoolContextService,
        private EnrollmentService $enrollmentService,
    ) {
    }

    /**
     * Index des inscriptions groupées par niveau : pour chaque niveau, ses classes
     * (avec occupation/capacité), ses inscriptions et le nombre d'élèves en attente
     * d'inscription (préinscriptions validées du niveau). Un bouton par niveau ouvre
     * l'inscription en masse.
     */
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(
        RegistrationRepository $registrationRepository,
        ClassroomRepository $classroomRepository,
        PreRegistrationRepository $preRegistrationRepository,
        LevelRepository $levelRepository
    ): Response {
        $school = $this->schoolContextService->getCurrentSchool();
        $schoolYear = $this->schoolContextService->getCurrentSchoolYear();

        if (!$school) {
            $this->addFlash('warning', 'Veuillez sélectionner un établissement pour voir les inscriptions.');
            return $this->redirectToRoute('admin_student_index');
        }

        $levels = $levelRepository->findBySchool($school->getId());
        $registrations = $registrationRepository->findBySchoolAndYear($school->getId(), $schoolYear?->getId());
        $classrooms = $classroomRepository->findBySchoolAndYear($school->getId(), $schoolYear?->getId());
        $enrolledByClassroom = $registrationRepository->countActiveByClassroom($school->getId(), $schoolYear?->getId());
        $pending = $preRegistrationRepository->findReadyForEnrollment($school->getId(), $schoolYear?->getId());

        // Inscriptions et préinscriptions en attente, regroupées par niveau.
        $regsByLevel = [];
        foreach ($registrations as $reg) {
            $levelId = $reg->getClassroom()?->getLevel()?->getId() ?? 0;
            $regsByLevel[$levelId][] = $reg;
        }
        $pendingByLevel = [];
        foreach ($pending as $preReg) {
            $levelId = $preReg->getRequestedLevel()?->getId() ?? 0;
            $pendingByLevel[$levelId] = ($pendingByLevel[$levelId] ?? 0) + 1;
        }

        // Détail des classes par niveau (occupation / capacité / places restantes).
        $classesByLevel = [];
        foreach ($classrooms as $classroom) {
            $levelId = $classroom->getLevel()?->getId() ?? 0;
            $count = $enrolledByClassroom[$classroom->getId()] ?? 0;
            $capacity = $classroom->getCapacity();
            $classesByLevel[$levelId][] = [
                'classroom' => $classroom,
                'count' => $count,
                'capacity' => $capacity,
                'remaining' => $capacity !== null ? max(0, $capacity - $count) : null,
            ];
        }

        // Construction de la vue par niveau.
        $levelGroups = [];
        foreach ($levels as $level) {
            $lid = $level->getId();
            $classes = $classesByLevel[$lid] ?? [];
            $seats = 0;
            $unlimited = false;
            foreach ($classes as $c) {
                if ($c['remaining'] === null) {
                    $unlimited = true;
                } else {
                    $seats += $c['remaining'];
                }
            }
            $levelGroups[] = [
                'level' => $level,
                'registrations' => $regsByLevel[$lid] ?? [],
                'classes' => $classes,
                'pending' => $pendingByLevel[$lid] ?? 0,
                'seats' => $seats,
                'unlimited' => $unlimited,
            ];
        }

        return $this->render('registration/index.html.twig', [
            'level_groups' => $levelGroups,
            'unassigned' => $regsByLevel[0] ?? [],
            'pending_no_level' => $pendingByLevel[0] ?? 0,
            'current_school' => $school,
            'current_school_year' => $schoolYear,
        ]);
    }

    /**
     * Inscription d'un élève à partir d'une préinscription validée. Le formulaire
     * (RegistrationEnrollType) expose la préinscription, la classe et les champs de
     * l'inscription (redoublant, boursier). À la soumission, EnrollmentService
     * crée/réutilise l'élève, crée l'inscription et affecte les frais ; on applique
     * ensuite les champs choisis.
     */
    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        PreRegistrationRepository $preRegistrationRepository,
        ClassroomRepository $classroomRepository,
        RegistrationRepository $registrationRepository
    ): Response {
        $school = $this->schoolContextService->getCurrentSchool();
        $schoolYear = $this->schoolContextService->getCurrentSchoolYear();

        if (!$school) {
            $this->addFlash('warning', 'Veuillez sélectionner un établissement pour créer une inscription.');
            return $this->redirectToRoute('admin_student_index');
        }

        // Préinscriptions validées disponibles : conditionnent l'affichage du formulaire.
        $preRegistrations = $preRegistrationRepository->findReadyForEnrollment($school->getId(), $schoolYear?->getId());

        // Capacité des classes : on exclut du choix les classes pleines (capacité atteinte)
        // et on affiche les places restantes sur les classes disponibles.
        $classrooms = $classroomRepository->findBySchoolAndYear($school->getId(), $schoolYear?->getId());
        $enrolledByClassroom = $registrationRepository->countActiveByClassroom($school->getId(), $schoolYear?->getId());
        $fullClassroomIds = [];
        $remainingByClassroom = [];
        foreach ($classrooms as $classroom) {
            $capacity = $classroom->getCapacity();
            if ($capacity === null) {
                continue; // capacité non renseignée : pas de limite.
            }
            $remaining = $capacity - ($enrolledByClassroom[$classroom->getId()] ?? 0);
            $remainingByClassroom[$classroom->getId()] = $remaining;
            if ($remaining <= 0) {
                $fullClassroomIds[] = $classroom->getId();
            }
        }

        // Classes disponibles (non pleines) regroupées par niveau : sert au JS pour
        // n'afficher, à la sélection d'une préinscription, que les classes de SON niveau.
        $classroomsByLevel = [];
        $allAvailable = [];
        foreach ($classrooms as $classroom) {
            if (in_array($classroom->getId(), $fullClassroomIds, true)) {
                continue;
            }
            $entry = [
                'id' => $classroom->getId(),
                'name' => $classroom->getName(),
                'remaining' => $remainingByClassroom[$classroom->getId()] ?? null,
            ];
            $allAvailable[] = $entry;
            $levelId = $classroom->getLevel()?->getId();
            if ($levelId) {
                $classroomsByLevel[$levelId][] = $entry;
            }
        }

        // Pour chaque préinscription : les classes de son niveau demandé. Si la
        // préinscription n'a pas de niveau, on propose toutes les classes disponibles.
        $enrollData = [];
        foreach ($preRegistrations as $preReg) {
            $levelId = $preReg->getRequestedLevel()?->getId();
            $enrollData[$preReg->getId()] = [
                'level' => $preReg->getRequestedLevel()?->getName(),
                'classes' => $levelId ? ($classroomsByLevel[$levelId] ?? []) : $allAvailable,
            ];
        }

        $form = $this->createForm(RegistrationEnrollType::class, null, [
            'excluded_classroom_ids' => $fullClassroomIds,
            'remaining_by_classroom' => $remainingByClassroom,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            /** @var PreRegistration $preRegistration */
            $preRegistration = $data['preRegistration'];
            /** @var Classroom $classroom */
            $classroom = $data['classroom'];

            if ($preRegistration->getStatus() !== 'validated'
                || $preRegistration->getSchool()?->getId() !== $school->getId()) {
                $this->addFlash('error', 'Préinscription introuvable ou non valide pour l\'inscription.');
                return $this->redirectToRoute('admin_registration_new');
            }

            // Garde-fou : la classe a pu se remplir entre l'affichage et la soumission.
            if (in_array($classroom->getId(), $fullClassroomIds, true)) {
                $this->addFlash('error', sprintf('La classe %s est complète.', $classroom->getName()));
                return $this->redirectToRoute('admin_registration_new');
            }

            // Sécurité (indépendante du JS) : la classe doit appartenir au niveau
            // demandé par la préinscription.
            $requestedLevel = $preRegistration->getRequestedLevel();
            if ($requestedLevel !== null && $classroom->getLevel()?->getId() !== $requestedLevel->getId()) {
                $this->addFlash('error', sprintf(
                    'La classe %s n\'appartient pas au niveau demandé (%s).',
                    $classroom->getName(),
                    $requestedLevel->getName()
                ));
                return $this->redirectToRoute('admin_registration_new');
            }

            try {
                $registration = $this->enrollmentService->enrollFromPreRegistration($preRegistration, $classroom, $schoolYear);
            } catch (\RuntimeException $e) {
                $this->addFlash('error', $e->getMessage());
                return $this->redirectToRoute('admin_registration_new');
            }

            // Application des champs choisis sur le formulaire d'inscription.
            $registration->setIsRepeating((bool) $data['isRepeating']);
            $registration->setBoursier((bool) $data['boursier']);
            // Statut « active » par défaut (entité Registration::$isActive = true).
            $this->entityManager->flush();

            $verbe = $preRegistration->isReturning() ? 'réinscrit' : 'inscrit';
            $this->addFlash('success', sprintf(
                'L\'élève %s a été %s avec succès en classe de %s.',
                $registration->getStudent()?->getFullName(),
                $verbe,
                $classroom->getName()
            ));

            return $this->redirectToRoute('admin_registration_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('registration/new.html.twig', [
            'form' => $form,
            'preRegistrations' => $preRegistrations,
            'enroll_data' => $enrollData,
            'current_school_year' => $schoolYear,
        ]);
    }

    /**
     * Inscription en masse des élèves d'un niveau : on sélectionne plusieurs
     * préinscriptions validées du niveau et le système les affecte automatiquement aux
     * classes du niveau en équilibrant les effectifs (chaque élève va dans la classe la
     * moins remplie ayant encore de la place).
     */
    #[Route('/niveau/{id}', name: 'level', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function levelEnroll(
        Level $level,
        Request $request,
        PreRegistrationRepository $preRegistrationRepository,
        ClassroomRepository $classroomRepository,
        RegistrationRepository $registrationRepository
    ): Response {
        $school = $this->schoolContextService->getCurrentSchool();
        $schoolYear = $this->schoolContextService->getCurrentSchoolYear();

        if (!$school || $level->getSchool()?->getId() !== $school->getId()) {
            $this->addFlash('error', 'Niveau introuvable pour l\'établissement courant.');
            return $this->redirectToRoute('admin_registration_index');
        }

        // Préinscriptions validées de CE niveau, en attente d'inscription.
        $pending = array_values(array_filter(
            $preRegistrationRepository->findReadyForEnrollment($school->getId(), $schoolYear?->getId()),
            static fn (PreRegistration $p) => $p->getRequestedLevel()?->getId() === $level->getId()
        ));

        // Classes du niveau avec places restantes (ordre : numéro/nom).
        $enrolledByClassroom = $registrationRepository->countActiveByClassroom($school->getId(), $schoolYear?->getId());
        $classes = [];
        foreach ($classroomRepository->findBySchoolAndYear($school->getId(), $schoolYear?->getId()) as $classroom) {
            if ($classroom->getLevel()?->getId() !== $level->getId()) {
                continue;
            }
            $capacity = $classroom->getCapacity();
            $classes[] = [
                'classroom' => $classroom,
                'count' => $enrolledByClassroom[$classroom->getId()] ?? 0,
                'capacity' => $capacity,
                'remaining' => $capacity !== null ? max(0, $capacity - ($enrolledByClassroom[$classroom->getId()] ?? 0)) : null,
            ];
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('level_enroll' . $level->getId(), (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Jeton de sécurité invalide, veuillez réessayer.');
                return $this->redirectToRoute('admin_registration_level', ['id' => $level->getId()]);
            }

            $selectedIds = array_map('intval', (array) $request->request->all('pre_registrations'));
            if ($selectedIds === []) {
                $this->addFlash('warning', 'Veuillez sélectionner au moins un élève à inscrire.');
                return $this->redirectToRoute('admin_registration_level', ['id' => $level->getId()]);
            }

            // Préinscriptions sélectionnées (dans l'ordre de la liste affichée).
            $byId = [];
            foreach ($pending as $p) {
                $byId[$p->getId()] = $p;
            }
            $selected = [];
            foreach ($selectedIds as $id) {
                if (isset($byId[$id])) {
                    $selected[] = $byId[$id];
                }
            }

            // Affectation automatique : équilibrer les effectifs. Chaque élève est placé
            // dans la classe ayant le plus petit effectif courant (parmi celles qui ont
            // encore de la place), ce qui répartit les élèves de façon homogène.
            $remaining = [];
            $count = [];
            foreach ($classes as $c) {
                $cid = $c['classroom']->getId();
                $remaining[$cid] = $c['remaining']; // null = illimité
                $count[$cid] = $c['count'];          // effectif courant
            }

            $enrolled = 0;
            $placedByClass = [];
            $unplaced = [];
            $errors = 0;

            foreach ($selected as $preReg) {
                // Classe avec le plus petit effectif parmi celles ayant de la place.
                $target = null;
                $bestCount = null;
                foreach ($classes as $c) {
                    $cid = $c['classroom']->getId();
                    $hasSpace = $remaining[$cid] === null || $remaining[$cid] > 0;
                    if (!$hasSpace) {
                        continue;
                    }
                    if ($target === null || $count[$cid] < $bestCount) {
                        $target = $c['classroom'];
                        $bestCount = $count[$cid];
                    }
                }

                if ($target === null) {
                    $unplaced[] = $preReg->getFullName();
                    continue;
                }

                try {
                    $registration = $this->enrollmentService->enrollFromPreRegistration($preReg, $target, $schoolYear);
                    $registration->setIsRepeating($preReg->isRepeating());
                    $tid = $target->getId();
                    $count[$tid]++;
                    if ($remaining[$tid] !== null) {
                        $remaining[$tid]--;
                    }
                    $enrolled++;
                    $placedByClass[$target->getName()] = ($placedByClass[$target->getName()] ?? 0) + 1;
                } catch (\RuntimeException $e) {
                    $errors++;
                }
            }

            $this->entityManager->flush();

            if ($enrolled > 0) {
                $detail = [];
                foreach ($placedByClass as $name => $n) {
                    $detail[] = sprintf('%s : %d', $name, $n);
                }
                $this->addFlash('success', sprintf(
                    '%d élève(s) inscrit(s) automatiquement (%s).',
                    $enrolled,
                    implode(' · ', $detail)
                ));
            }
            if ($unplaced !== []) {
                $this->addFlash('warning', sprintf(
                    'Plus de place dans les classes du niveau : %d élève(s) non inscrit(s) (%s).',
                    count($unplaced),
                    implode(', ', $unplaced)
                ));
            }
            if ($errors > 0) {
                $this->addFlash('error', sprintf('%d inscription(s) ont échoué (élève déjà inscrit ?).', $errors));
            }

            return $this->redirectToRoute('admin_registration_level', ['id' => $level->getId()], Response::HTTP_SEE_OTHER);
        }

        $totalSeats = 0;
        $unlimited = false;
        foreach ($classes as $c) {
            if ($c['remaining'] === null) {
                $unlimited = true;
            } else {
                $totalSeats += $c['remaining'];
            }
        }

        return $this->render('registration/level.html.twig', [
            'level' => $level,
            'pending' => $pending,
            'classes' => $classes,
            'total_seats' => $totalSeats,
            'unlimited' => $unlimited,
            'current_school_year' => $schoolYear,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Registration $registration): Response
    {
        $form = $this->createForm(RegistrationType::class, $registration);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();

            $this->addFlash('success', 'L\'inscription a été modifiée avec succès.');

            return $this->redirectToRoute('admin_registration_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('registration/edit.html.twig', [
            'registration' => $registration,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, Registration $registration): Response
    {
        if ($this->isCsrfTokenValid('delete' . $registration->getId(), $request->request->get('_token'))) {
            $this->deleteEntity(
                $this->entityManager,
                $registration,
                'L\'inscription a été supprimée avec succès.',
                'Suppression impossible : cette inscription est liée à des frais ou paiements existants.'
            );
        }

        return $this->redirectToRoute('admin_registration_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/toggle', name: 'toggle', methods: ['POST'])]
    public function toggle(Request $request, Registration $registration): Response
    {
        if ($this->isCsrfTokenValid('toggle' . $registration->getId(), $request->request->get('_token'))) {
            $registration->setIsActive(!$registration->isActive());
            $this->entityManager->flush();

            $status = $registration->isActive() ? 'réactivée' : 'annulée';
            $this->addFlash('success', "L'inscription a été {$status} avec succès.");
        }

        return $this->redirectToRoute('admin_registration_index', [], Response::HTTP_SEE_OTHER);
    }
}
