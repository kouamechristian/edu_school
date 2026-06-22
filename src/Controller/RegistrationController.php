<?php

namespace App\Controller;

use App\Controller\Concern\HandlesEntityDeletion;
use App\Entity\Classroom;
use App\Entity\PreRegistration;
use App\Entity\Registration;
use App\Form\RegistrationEnrollType;
use App\Form\RegistrationType;
use App\Repository\ClassroomRepository;
use App\Repository\PreRegistrationRepository;
use App\Repository\RegistrationRepository;
use App\Service\EnrollmentService;
use App\Service\SchoolContextService;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
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

    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(RegistrationRepository $registrationRepository, PaginatorInterface $paginator, Request $request): Response
    {
        $school = $this->schoolContextService->getCurrentSchool();
        $schoolYear = $this->schoolContextService->getCurrentSchoolYear();

        if (!$school) {
            $this->addFlash('warning', 'Veuillez sélectionner un établissement pour voir les inscriptions.');
            return $this->redirectToRoute('admin_student_index');
        }

        $registrations = $registrationRepository->findBySchoolAndYear($school->getId(), $schoolYear?->getId());
        $pagination = $paginator->paginate($registrations, $request->query->getInt('page', 1), 50);

        return $this->render('registration/index.html.twig', [
            'registrations' => $pagination,
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
