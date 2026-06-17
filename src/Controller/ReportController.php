<?php

namespace App\Controller;

use App\Entity\Classroom;
use App\Repository\ClassroomRepository;
use App\Repository\StudentRepository;
use App\Service\SchoolContextService;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Rapports du module « Gestion des Élèves » : effectifs, listes et fiches imprimables.
 */
#[Route('/admin/reports', name: 'admin_report_')]
#[IsGranted('ROLE_INSCRIPTION')]
class ReportController extends AbstractController
{
    public function __construct(
        private SchoolContextService $schoolContextService,
        private ClassroomRepository $classroomRepository,
        private StudentRepository $studentRepository,
    ) {}

    /**
     * Liste des rapports disponibles (tableau nom / actions).
     */
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('report/index.html.twig', [
            'reports' => [
                ['key' => 'effectif_classe_niveau', 'name' => 'EFFECTIF PAR CLASSE ET PAR NIVEAU', 'type' => 'pdf'],
                ['key' => 'effectif_niveau', 'name' => 'EFFECTIF PAR NIVEAU', 'type' => 'pdf'],
                ['key' => 'effectifs_affectes', 'name' => 'EFFECTIFS ELEVES AFFECTES', 'type' => 'pdf'],
                ['key' => 'liste_classe', 'name' => 'LISTE DES ELEVES PAR CLASSE', 'type' => 'select'],
                ['key' => 'fiche_notes', 'name' => 'FICHE PRÉPARATOIRE DES NOTES', 'type' => 'select'],
            ],
        ]);
    }

    #[Route('/effectif-classe-niveau/pdf', name: 'effectif_classe_niveau', methods: ['GET'])]
    public function effectifClasseNiveau(): Response
    {
        [$school, $year, $byLevel, $totals] = $this->collectEffectifs();
        if (!$school) {
            return $this->redirectToRoute('admin_report_index');
        }

        return $this->renderPdf('report/effectif_classe_niveau_pdf.html.twig', [
            'school' => $school,
            'school_year' => $year,
            'by_level' => $byLevel,
            'totals' => $totals,
            'logo_data' => $this->logoData($school),
            'generated_at' => new \DateTime(),
        ], 'EFFECTIF_CLASSE_NIVEAU.pdf');
    }

    #[Route('/effectif-niveau/pdf', name: 'effectif_niveau', methods: ['GET'])]
    public function effectifNiveau(): Response
    {
        [$school, $year, $byLevel, $totals] = $this->collectEffectifs();
        if (!$school) {
            return $this->redirectToRoute('admin_report_index');
        }

        return $this->renderPdf('report/effectif_niveau_pdf.html.twig', [
            'school' => $school,
            'school_year' => $year,
            'by_level' => $byLevel,
            'totals' => $totals,
            'logo_data' => $this->logoData($school),
            'generated_at' => new \DateTime(),
        ], 'EFFECTIF_NIVEAU.pdf');
    }

    #[Route('/effectifs-affectes/pdf', name: 'effectifs_affectes', methods: ['GET'])]
    public function effectifsAffectes(): Response
    {
        [$school, $year, $byLevel, $totals] = $this->collectEffectifs();
        if (!$school) {
            return $this->redirectToRoute('admin_report_index');
        }

        return $this->renderPdf('report/effectifs_affectes_pdf.html.twig', [
            'school' => $school,
            'school_year' => $year,
            'by_level' => $byLevel,
            'totals' => $totals,
            'logo_data' => $this->logoData($school),
            'generated_at' => new \DateTime(),
        ], 'EFFECTIFS_AFFECTES.pdf');
    }

    /**
     * Sélection d'une classe avant génération d'un rapport par classe.
     */
    #[Route('/classe-selection/{report}', name: 'classe_selection', methods: ['GET'])]
    public function classeSelection(string $report): Response
    {
        $school = $this->schoolContextService->getCurrentSchool();
        $year = $this->schoolContextService->getCurrentSchoolYear();

        if (!$school) {
            $this->addFlash('warning', 'Veuillez sélectionner un établissement pour générer ce rapport.');
            return $this->redirectToRoute('admin_report_index');
        }

        $titles = [
            'liste_classe' => 'LISTE DES ELEVES PAR CLASSE',
            'fiche_notes' => 'FICHE PRÉPARATOIRE DES NOTES',
        ];
        if (!isset($titles[$report])) {
            return $this->redirectToRoute('admin_report_index');
        }

        return $this->render('report/classe_selection.html.twig', [
            'report' => $report,
            'report_title' => $titles[$report],
            'classrooms' => $this->classroomRepository->findBySchoolAndYear($school->getId(), $year?->getId()),
        ]);
    }

    #[Route('/fiche-notes/classe/{id}/pdf', name: 'fiche_notes', methods: ['GET'])]
    public function ficheNotes(Classroom $classroom): Response
    {
        $school = $classroom->getSchool();

        return $this->renderPdf('report/fiche_notes_pdf.html.twig', [
            'classroom' => $classroom,
            'students' => $this->studentRepository->findActiveByClassroom($classroom->getId()),
            'school' => $school,
            'logo_data' => $this->logoData($school),
            'generated_at' => new \DateTime(),
        ], sprintf('FICHE_NOTES_%s.pdf', $classroom->getName()));
    }

    /**
     * Agrège les effectifs (garçons/filles, affectés/non affectés) par classe puis par niveau,
     * pour l'établissement et l'année scolaire courants.
     *
     * @return array{0: ?\App\Entity\School, 1: ?\App\Entity\SchoolYear, 2: array<int, array{level: ?\App\Entity\Level, label: string, order: int, classes: list<array<string, mixed>>, boys: int, girls: int, affected: int, non_affected: int, total: int}>, 3: array{classes: int, boys: int, girls: int, affected: int, non_affected: int, total: int}}
     */
    private function collectEffectifs(): array
    {
        $school = $this->schoolContextService->getCurrentSchool();
        $year = $this->schoolContextService->getCurrentSchoolYear();

        if (!$school) {
            $this->addFlash('warning', 'Veuillez sélectionner un établissement pour générer ce rapport.');
            return [null, null, [], ['classes' => 0, 'boys' => 0, 'girls' => 0, 'affected' => 0, 'non_affected' => 0, 'total' => 0]];
        }

        $classrooms = $this->classroomRepository->findBySchoolAndYear($school->getId(), $year?->getId());

        $byLevel = [];
        $totals = ['classes' => 0, 'boys' => 0, 'girls' => 0, 'affected' => 0, 'non_affected' => 0, 'total' => 0];

        foreach ($classrooms as $classroom) {
            $students = $this->studentRepository->findActiveByClassroom($classroom->getId());

            $boys = 0;
            $girls = 0;
            $affected = 0;
            $nonAffected = 0;
            foreach ($students as $student) {
                if ($student->getGender() === 'M') {
                    $boys++;
                } elseif ($student->getGender() === 'F') {
                    $girls++;
                }
                if ($student->getStatus() === 'affecte') {
                    $affected++;
                } else {
                    $nonAffected++;
                }
            }
            $total = \count($students);

            $level = $classroom->getLevel();
            $levelKey = $level?->getId() ?? 0;
            if (!isset($byLevel[$levelKey])) {
                $byLevel[$levelKey] = [
                    'level' => $level,
                    'label' => $level?->getName() ?? 'Sans niveau',
                    'order' => $level?->getOrderNumber() ?? 9999,
                    'classes' => [],
                    'boys' => 0,
                    'girls' => 0,
                    'affected' => 0,
                    'non_affected' => 0,
                    'total' => 0,
                ];
            }

            $byLevel[$levelKey]['classes'][] = [
                'classroom' => $classroom,
                'name' => $classroom->getName(),
                'boys' => $boys,
                'girls' => $girls,
                'affected' => $affected,
                'non_affected' => $nonAffected,
                'total' => $total,
            ];
            $byLevel[$levelKey]['boys'] += $boys;
            $byLevel[$levelKey]['girls'] += $girls;
            $byLevel[$levelKey]['affected'] += $affected;
            $byLevel[$levelKey]['non_affected'] += $nonAffected;
            $byLevel[$levelKey]['total'] += $total;

            $totals['classes']++;
            $totals['boys'] += $boys;
            $totals['girls'] += $girls;
            $totals['affected'] += $affected;
            $totals['non_affected'] += $nonAffected;
            $totals['total'] += $total;
        }

        // Tri par ordre de niveau puis par nom de classe.
        usort($byLevel, static fn (array $a, array $b) => $a['order'] <=> $b['order']);
        foreach ($byLevel as &$group) {
            usort($group['classes'], static fn (array $a, array $b) => strcmp((string) $a['name'], (string) $b['name']));
        }
        unset($group);

        return [$school, $year, $byLevel, $totals];
    }

    /**
     * Génère un logo embarqué en base64 (Dompdf lit ainsi l'image sans accès disque/URL).
     */
    private function logoData(?\App\Entity\School $school): ?string
    {
        if (!$school || !$school->getLogo()) {
            return null;
        }

        $logoPath = $this->getParameter('kernel.project_dir') . '/public/' . ltrim($school->getLogo(), '/');
        if (!is_file($logoPath)) {
            return null;
        }

        $mime = mime_content_type($logoPath) ?: 'image/png';

        return 'data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($logoPath));
    }

    /**
     * @param array<string, mixed> $context
     */
    private function renderPdf(string $template, array $context, string $filename): Response
    {
        $html = $this->renderView($template, $context);

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
            'Content-Disposition' => sprintf('inline; filename="%s"', $filename),
        ]);
    }
}
