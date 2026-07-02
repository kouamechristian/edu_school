<?php

namespace App\Controller;

use App\Entity\Bulletin;
use App\Entity\BulletinLine;
use App\Entity\Period;
use App\Entity\Student;
use App\Entity\User;
use App\Controller\Concern\HandlesEntityDeletion;
use App\Form\BulletinFormType;
use App\Repository\BulletinRepository;
use App\Repository\ClassroomRepository;
use App\Repository\PeriodRepository;
use App\Repository\StudentRepository;
use App\Service\GradeCalculationService;
use App\Service\SchoolContextService;
use Doctrine\ORM\EntityManagerInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/bulletins')]
#[IsGranted('ROLE_ENSEIGNANT')]
class BulletinController extends AbstractController
{
    use HandlesEntityDeletion;

    public function __construct(
        private GradeCalculationService $gradeCalculationService
    ) {
    }

    /**
     * Liste des bulletins créés (libellé, niveau, période…), avec un bouton « Nouveau ».
     */
    #[Route('/', name: 'admin_bulletin_index', methods: ['GET'])]
    public function index(
        BulletinRepository $bulletinRepository,
        SchoolContextService $contextService
    ): Response {
        $currentSchool = $contextService->getCurrentSchool();
        $currentYear = $contextService->getCurrentSchoolYear();

        return $this->render('bulletin/index.html.twig', [
            'bulletins' => $currentSchool
                ? $bulletinRepository->findBySchoolAndYear($currentSchool->getId(), $currentYear?->getId())
                : [],
            'current_school' => $currentSchool,
            'current_year' => $currentYear,
        ]);
    }

    /**
     * Création d'un bulletin : libellé, moyenne sur, niveau, période. À l'enregistrement,
     * on redirige vers la page listant tous les élèves du niveau choisi.
     */
    #[Route('/new', name: 'admin_bulletin_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        SchoolContextService $contextService,
        BulletinRepository $bulletinRepository
    ): Response {
        $currentSchool = $contextService->getCurrentSchool();
        $currentYear = $contextService->getCurrentSchoolYear();

        if (!$currentSchool || !$currentYear) {
            $this->addFlash('warning', 'Veuillez sélectionner un établissement et une année scolaire.');

            return $this->redirectToRoute('admin_bulletin_index');
        }

        $bulletin = new Bulletin();
        $form = $this->createForm(BulletinFormType::class, $bulletin);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Unicité : un seul bulletin par niveau et par période (brouillon ou
            // validé). On redirige vers le bulletin existant plutôt que d'en créer
            // un doublon ; si celui-ci est validé, la saisie est en plus clôturée.
            $level = $bulletin->getLevel();
            $period = $bulletin->getPeriod();
            if ($level && $period) {
                $existing = $bulletinRepository->findOneFor(
                    $currentSchool->getId(),
                    $currentYear->getId(),
                    $level->getId(),
                    $period->getId()
                );
                if ($existing) {
                    if ($existing->isValidated()) {
                        $this->addFlash('error', sprintf(
                            'Un bulletin déjà validé existe pour le niveau « %s » et la période « %s ». '
                            . 'Impossible de créer un nouveau bulletin pour ce niveau à cette période.',
                            $level->getName(),
                            $period->getName()
                        ));

                        return $this->redirectToRoute('admin_bulletin_index');
                    }

                    $this->addFlash('warning', sprintf(
                        'Un bulletin existe déjà pour le niveau « %s » et la période « %s ». '
                        . 'En voici le contenu (un seul bulletin par niveau et par période).',
                        $level->getName(),
                        $period->getName()
                    ));

                    return $this->redirectToRoute('admin_bulletin_show', ['id' => $existing->getId()]);
                }
            }

            $bulletin->setSchool($currentSchool);
            $bulletin->setSchoolYear($currentYear);
            if ($this->getUser() instanceof User) {
                $bulletin->setCreatedBy($this->getUser());
            }

            $entityManager->persist($bulletin);
            $entityManager->flush();

            $this->addFlash('success', 'Bulletin créé. Voici les élèves du niveau sélectionné.');

            return $this->redirectToRoute('admin_bulletin_show', ['id' => $bulletin->getId()]);
        }

        return $this->render('bulletin/new.html.twig', [
            'form' => $form,
        ]);
    }

    /**
     * Page d'un bulletin : tableau de tous les élèves du niveau choisi, avec leur
     * moyenne générale pour la période (ramenée sur la base « moyenne sur »).
     */
    #[Route('/{id}', name: 'admin_bulletin_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(
        Bulletin $bulletin,
        StudentRepository $studentRepository,
        ClassroomRepository $classroomRepository
    ): Response {
        $base = max(1, (int) $bulletin->getMoyenneSur());

        if ($bulletin->isComputed()) {
            // Affiche le snapshot (moyennes / rangs / mentions figés au calcul).
            $rows = [];
            foreach ($bulletin->getLines() as $line) {
                $student = $line->getStudent();
                $rows[] = [
                    'student' => $student,
                    'classroom' => $student?->getClassroom(),
                    'average' => $line->getAverage() !== null ? (float) $line->getAverage() : null,
                    'rank' => $line->getRank(),
                    'mention' => $line->getMention(),
                    'annual_average' => $line->getAnnualAverage() !== null ? (float) $line->getAnnualAverage() : null,
                    'annual_rank' => $line->getAnnualRank(),
                    'has_grades' => $line->getAverage() !== null,
                ];
            }
            // Notés (par rang croissant) d'abord, non notés à la fin ; on retombe sur le
            // rang annuel si la moyenne de période n'a pas été calculée.
            usort($rows, fn ($a, $b) => ($a['rank'] ?? $a['annual_rank'] ?? PHP_INT_MAX) <=> ($b['rank'] ?? $b['annual_rank'] ?? PHP_INT_MAX));
        } else {
            // Pas encore calculé : on liste simplement les élèves du niveau.
            $level = $bulletin->getLevel();
            $school = $bulletin->getSchool();
            $students = ($level && $school)
                ? $studentRepository->findActiveBySchoolAndLevel($school->getId(), $level->getId(), $bulletin->getSchoolYear()?->getId())
                : [];
            $rows = array_map(fn ($s) => [
                'student' => $s,
                'classroom' => $s->getClassroom(),
                'average' => null,
                'rank' => null,
                'mention' => null,
                'annual_average' => null,
                'annual_rank' => null,
                'has_grades' => false,
            ], $students);
        }

        // Classes du niveau (année du bulletin) pour le choix avant impression.
        $classrooms = [];
        if ($bulletin->getLevel()) {
            $yearId = $bulletin->getSchoolYear()?->getId();
            $classrooms = array_filter(
                $classroomRepository->findByLevel($bulletin->getLevel()->getId()),
                static fn ($c) => $yearId === null || $c->getSchoolYear()?->getId() === $yearId
            );
        }

        return $this->render('bulletin/show.html.twig', [
            'bulletin' => $bulletin,
            'rows' => $rows,
            'base' => $base,
            'classrooms' => $classrooms,
        ]);
    }

    /**
     * Calcule les moyennes de tous les élèves du niveau pour la période et fige le
     * résultat (moyenne sur la base choisie, rang, mention) dans les lignes du bulletin.
     */
    #[Route('/{id}/calculer', name: 'admin_bulletin_compute', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function compute(
        Request $request,
        Bulletin $bulletin,
        StudentRepository $studentRepository,
        EntityManagerInterface $entityManager
    ): Response {
        if (!$this->isCsrfTokenValid('compute' . $bulletin->getId(), (string) $request->request->get('_token'))) {
            return $this->redirectToRoute('admin_bulletin_show', ['id' => $bulletin->getId()]);
        }

        if ($bulletin->isValidated()) {
            $this->addFlash('warning', 'Ce bulletin est validé : le recalcul est désactivé.');

            return $this->redirectToRoute('admin_bulletin_show', ['id' => $bulletin->getId()]);
        }

        $level = $bulletin->getLevel();
        $period = $bulletin->getPeriod();
        $school = $bulletin->getSchool();
        $base = max(1, (int) $bulletin->getMoyenneSur());

        if (!$level || !$period || !$school) {
            $this->addFlash('error', 'Bulletin incomplet (niveau ou période manquant).');

            return $this->redirectToRoute('admin_bulletin_show', ['id' => $bulletin->getId()]);
        }

        // Réinitialise les lignes existantes.
        $bulletin->clearLines();
        $entityManager->flush();

        $students = $studentRepository->findActiveBySchoolAndLevel($school->getId(), $level->getId(), $bulletin->getSchoolYear()?->getId());

        // Moyennes /20 puis rang (compétition) parmi les élèves notés.
        $avg20 = [];
        foreach ($students as $s) {
            $avg20[$s->getId()] = $this->gradeCalculationService->calculateStudentAveragesForPeriod($s, $period)['general_average'];
        }
        $graded = array_filter($avg20, fn ($v) => $v !== null);
        arsort($graded);

        foreach ($students as $s) {
            $a = $avg20[$s->getId()];
            $line = new BulletinLine();
            $line->setStudent($s);
            if ($a !== null) {
                $line->setAverage((string) round($a * $base / 20, 2));
                $line->setRank($this->competitionRank($graded, $a));
                $line->setMention($this->gradeCalculationService->getMention($a) ?? '');
            }
            $bulletin->addLine($line);
            $entityManager->persist($line);
        }

        $bulletin->setComputedAt(new \DateTime());
        $entityManager->flush();

        if ($students === []) {
            $this->addFlash('warning', sprintf(
                'Aucun élève inscrit dans une classe du niveau « %s » pour l\'année sélectionnée.',
                $level->getName()
            ));
        } elseif ($graded === []) {
            $this->addFlash('warning', sprintf(
                'Aucune moyenne calculée : aucun des %d élève(s) du niveau n\'a de note validée pour la période « %s ». '
                . 'Vérifiez que des évaluations ont bien été saisies ET validées pour cette période.',
                count($students),
                $period->getName()
            ));
        } else {
            $this->addFlash('success', sprintf(
                'Moyennes calculées : %d élève(s) noté(s) sur %d du niveau.',
                count($graded),
                count($students)
            ));
        }

        return $this->redirectToRoute('admin_bulletin_show', ['id' => $bulletin->getId()]);
    }

    /**
     * Calcule la moyenne annuelle (toutes les périodes de l'année) de chaque élève du
     * niveau et la fige dans les lignes du bulletin, sans écraser la moyenne de période.
     */
    #[Route('/{id}/calculer-annuelle', name: 'admin_bulletin_compute_annual', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function computeAnnual(
        Request $request,
        Bulletin $bulletin,
        StudentRepository $studentRepository,
        \App\Repository\GradeRepository $gradeRepository,
        EntityManagerInterface $entityManager
    ): Response {
        if (!$this->isCsrfTokenValid('computeannual' . $bulletin->getId(), (string) $request->request->get('_token'))) {
            return $this->redirectToRoute('admin_bulletin_show', ['id' => $bulletin->getId()]);
        }

        if ($bulletin->isValidated()) {
            $this->addFlash('warning', 'Ce bulletin est validé : le recalcul est désactivé.');

            return $this->redirectToRoute('admin_bulletin_show', ['id' => $bulletin->getId()]);
        }

        $level = $bulletin->getLevel();
        $school = $bulletin->getSchool();
        $base = max(1, (int) $bulletin->getMoyenneSur());
        $yearId = $bulletin->getSchoolYear()?->getId();

        if (!$level || !$school) {
            $this->addFlash('error', 'Bulletin incomplet (niveau manquant).');

            return $this->redirectToRoute('admin_bulletin_show', ['id' => $bulletin->getId()]);
        }

        $students = $studentRepository->findActiveBySchoolAndLevel($school->getId(), $level->getId(), $yearId);

        // Lignes existantes indexées par élève (pour ne pas écraser la moyenne de période).
        $linesByStudent = [];
        foreach ($bulletin->getLines() as $line) {
            if ($line->getStudent()) {
                $linesByStudent[$line->getStudent()->getId()] = $line;
            }
        }

        // Moyennes annuelles /20 (évaluations validées) puis rang « compétition ».
        $avg20 = [];
        foreach ($students as $s) {
            $avg20[$s->getId()] = $gradeRepository->calculateAnnualGeneralAverageByStudent($s->getId(), $yearId, true);
        }
        $graded = array_filter($avg20, fn ($v) => $v !== null);
        arsort($graded);

        foreach ($students as $s) {
            $a = $avg20[$s->getId()];
            $line = $linesByStudent[$s->getId()] ?? null;
            if ($line === null) {
                $line = new BulletinLine();
                $line->setStudent($s);
                $bulletin->addLine($line);
                $entityManager->persist($line);
            }
            if ($a !== null) {
                $line->setAnnualAverage((string) round($a * $base / 20, 2));
                $line->setAnnualRank($this->competitionRank($graded, $a));
            } else {
                $line->setAnnualAverage(null);
                $line->setAnnualRank(null);
            }
        }

        if (!$bulletin->isComputed()) {
            $bulletin->setComputedAt(new \DateTime());
        }
        $entityManager->flush();

        if ($students === []) {
            $this->addFlash('warning', sprintf(
                'Aucun élève inscrit dans une classe du niveau « %s » pour l\'année sélectionnée.',
                $level->getName()
            ));
        } elseif ($graded === []) {
            $this->addFlash('warning', sprintf(
                'Aucune moyenne annuelle calculée : aucun des %d élève(s) du niveau n\'a de note validée sur l\'année.',
                count($students)
            ));
        } else {
            $this->addFlash('success', sprintf(
                'Moyennes annuelles calculées : %d élève(s) noté(s) sur %d du niveau.',
                count($graded),
                count($students)
            ));
        }

        return $this->redirectToRoute('admin_bulletin_show', ['id' => $bulletin->getId()]);
    }

    /**
     * Valide le bulletin (fige le snapshot ; recalcul désactivé ensuite).
     */
    #[Route('/{id}/valider', name: 'admin_bulletin_validate', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function validate(
        Request $request,
        Bulletin $bulletin,
        EntityManagerInterface $entityManager
    ): Response {
        if (!$this->isCsrfTokenValid('validate' . $bulletin->getId(), (string) $request->request->get('_token'))) {
            return $this->redirectToRoute('admin_bulletin_show', ['id' => $bulletin->getId()]);
        }

        if (!$bulletin->isComputed()) {
            $this->addFlash('warning', 'Calculez d\'abord les moyennes avant de valider.');

            return $this->redirectToRoute('admin_bulletin_show', ['id' => $bulletin->getId()]);
        }

        $bulletin->setIsValidated(true);
        $bulletin->setValidatedAt(new \DateTime());
        if ($this->getUser() instanceof User) {
            $bulletin->setValidatedBy($this->getUser());
        }
        $entityManager->flush();

        $this->addFlash('success', 'Bulletin validé.');

        return $this->redirectToRoute('admin_bulletin_show', ['id' => $bulletin->getId()]);
    }

    /**
     * Impression groupée : un PDF contenant le bulletin de chaque élève du niveau.
     */
    #[Route('/{id}/imprimer', name: 'admin_bulletin_print_all', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function printAll(
        Request $request,
        Bulletin $bulletin,
        StudentRepository $studentRepository
    ): Response {
        $level = $bulletin->getLevel();
        $period = $bulletin->getPeriod();
        $school = $bulletin->getSchool();

        if (!$level || !$period || !$school) {
            throw $this->createNotFoundException();
        }

        // Élèves : ceux du snapshot si calculé, sinon tous les élèves du niveau.
        if ($bulletin->isComputed()) {
            $students = array_map(fn (BulletinLine $l) => $l->getStudent(), $bulletin->getLines()->toArray());
        } else {
            $students = $studentRepository->findActiveBySchoolAndLevel($school->getId(), $level->getId(), $bulletin->getSchoolYear()?->getId());
        }

        // Filtrage sur la classe choisie (le cas échéant) : on garde les élèves dont
        // la classe d'inscription pour l'année (repli sur la classe legacy) correspond.
        $classroomId = $request->query->getInt('classroom');
        if ($classroomId) {
            $year = $period->getSchoolYear();
            $students = array_filter($students, static function ($s) use ($classroomId, $year) {
                if (!$s) {
                    return false;
                }
                $registration = $year ? $s->getRegistrationForYear($year) : $s->getLatestRegistration();
                $cid = ($registration?->getClassroom() ?? $s->getClassroom())?->getId();
                return $cid === $classroomId;
            });
        }

        $pages = [];
        foreach ($students as $student) {
            if (!$student) {
                continue;
            }
            $year = $period->getSchoolYear();
            $registration = $year ? $student->getRegistrationForYear($year) : $student->getLatestRegistration();
            $classroomId = ($registration?->getClassroom() ?? $student->getClassroom())?->getId() ?? 0;
            $sheet = $this->gradeCalculationService->generateBulletinSheet($student, $period, $classroomId);
            // On rend le CORPS du bulletin (fragment), sans <html>/<head>, pour
            // pouvoir concaténer plusieurs élèves dans un seul document bien formé.
            $pages[] = $this->renderView('bulletin/_sheet_body.html.twig', array_merge($sheet, [
                'photo_data' => $this->imageDataUri($student->getPhoto()),
                'city' => $school->getAddress() ?: '',
                'director_role' => 'Directeur des études',
            ]));
        }

        if ($pages === []) {
            $this->addFlash('warning', 'Aucun élève à imprimer pour ce niveau.');

            return $this->redirectToRoute('admin_bulletin_show', ['id' => $bulletin->getId()]);
        }

        // Document unique et valide : styles du bulletin inclus UNE seule fois dans
        // le <head> (sinon Dompdf perd le CSS des <head> imbriqués → bulletin non
        // stylé et décentré). Chaque élève est séparé par un saut de page.
        $html = '<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8">'
            . $this->renderView('bulletin/_sheet_styles.html.twig')
            . '<style>.pp-break{page-break-after:always;}</style></head><body>'
            . implode('<div class="pp-break"></div>', $pages)
            . '</body></html>';

        $options = new Options();
        $options->set('defaultFont', 'Helvetica');
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return new Response($dompdf->output(), Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf('inline; filename="BULLETINS_%s.pdf"', $level->getName()),
        ]);
    }

    /**
     * Bordereau d'évaluation (« fiche de notes ») du niveau, une page par classe.
     * ?notes=1 → pré-rempli avec les notes ; sinon grille vierge à remplir.
     */
    #[Route('/{id}/fiche-notes', name: 'admin_bulletin_bordereau', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function bordereau(Request $request, Bulletin $bulletin): Response
    {
        $level = $bulletin->getLevel();
        $period = $bulletin->getPeriod();
        $school = $bulletin->getSchool();

        if (!$level || !$period || !$school) {
            throw $this->createNotFoundException();
        }

        $withNotes = $request->query->getBoolean('notes');
        $showCoef = $request->query->getBoolean('coef');
        $data = $this->gradeCalculationService->generateBordereaux($period, $level, $school);

        $html = $this->renderView('bulletin/bordereau_pdf.html.twig', array_merge($data, [
            'with_notes' => $withNotes,
            'show_coef' => $showCoef,
            'logo_data' => $this->imageDataUri($school->getLogo()),
        ]));

        $options = new Options();
        $options->set('defaultFont', 'Helvetica');
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        if ($showCoef) {
            $filename = sprintf('BORDEREAU_EVALUATION_%s.pdf', strtoupper((string) $level->getName()));
        } else {
            $filename = sprintf(
                'FICHE_DE_NOTES_%s%s.pdf',
                $withNotes ? '' : 'VIDE_',
                strtoupper((string) $level->getName())
            );
        }

        return new Response($dompdf->output(), Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf('inline; filename="%s"', $filename),
        ]);
    }

    /**
     * Rang « compétition » d'une moyenne parmi les moyennes notées (1 = meilleure).
     *
     * @param array<int, float> $graded moyennes /20 triées décroissantes
     */
    private function competitionRank(array $graded, float $value): int
    {
        $greater = 0;
        foreach ($graded as $v) {
            if ($v > $value) {
                $greater++;
            }
        }

        return $greater + 1;
    }

    /**
     * Suppression d'un bulletin.
     */
    #[Route('/{id}', name: 'admin_bulletin_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(
        Request $request,
        Bulletin $bulletin,
        EntityManagerInterface $entityManager
    ): Response {
        if ($this->isCsrfTokenValid('delete' . $bulletin->getId(), (string) $request->request->get('_token'))) {
            $this->deleteEntity($entityManager, $bulletin, 'Bulletin supprimé.');
        }

        return $this->redirectToRoute('admin_bulletin_index');
    }

    /**
     * Bulletin PDF (modèle officiel) d'un élève pour une période.
     */
    #[Route('/pdf/{studentId}/{periodId}', name: 'admin_bulletin_pdf', requirements: ['studentId' => '\d+', 'periodId' => '\d+'], methods: ['GET'])]
    public function generatePdf(
        int $studentId,
        int $periodId,
        StudentRepository $studentRepository,
        PeriodRepository $periodRepository
    ): Response {
        $student = $studentRepository->find($studentId);
        $period = $periodRepository->find($periodId);

        if (!$student || !$period) {
            throw $this->createNotFoundException('Élève ou période non trouvé');
        }

        return $this->bulletinPdfResponse($student, $period);
    }

    /**
     * Construit le bulletin PDF (modèle officiel) d'un élève pour une période.
     */
    private function bulletinPdfResponse(Student $student, Period $period): Response
    {
        // Classe de l'élève pour l'année de la période (via son inscription),
        // avec repli sur la classe legacy.
        $year = $period->getSchoolYear();
        $registration = $year ? $student->getRegistrationForYear($year) : $student->getLatestRegistration();
        $classroomId = ($registration?->getClassroom() ?? $student->getClassroom())?->getId() ?? 0;
        $sheet = $this->gradeCalculationService->generateBulletinSheet($student, $period, $classroomId);
        $school = $student->getSchool();

        $html = $this->renderView('bulletin/sheet_pdf.html.twig', array_merge($sheet, [
            'photo_data' => $this->imageDataUri($student->getPhoto()),
            'city' => $school && $school->getAddress() ? $school->getAddress() : '',
            'director_role' => 'Directeur des études',
        ]));

        $options = new Options();
        $options->set('defaultFont', 'Helvetica');
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $filename = sprintf('BULLETIN_%s_%s.pdf', strtoupper((string) $student->getLastName()), $period->getCode());

        return new Response($dompdf->output(), Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf('inline; filename="%s"', $filename),
        ]);
    }

    private function imageDataUri(?string $relativePath): ?string
    {
        if (!$relativePath) {
            return null;
        }
        $path = $this->getParameter('kernel.project_dir') . '/public/' . ltrim($relativePath, '/');
        if (!is_file($path)) {
            return null;
        }
        $mime = mime_content_type($path) ?: 'image/png';

        return 'data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($path));
    }
}
