<?php

namespace App\Controller;

use App\Entity\Classroom;
use App\Entity\School;
use App\Repository\ClassroomRepository;
use App\Repository\GradeRepository;
use App\Repository\SubjectRepository;
use App\Service\GradeCalculationService;
use App\Service\SchoolContextService;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Rapports du module « Académique » : effectifs, statistiques et résultats scolaires.
 */
#[Route('/admin/academic-reports', name: 'admin_academic_report_')]
#[IsGranted('ROLE_DIRECTEUR')]
class AcademicReportController extends AbstractController
{
    /** Seuil de réussite (moyenne d'admission). */
    private const PASS_MARK = 10.0;

    public function __construct(
        private SchoolContextService $schoolContextService,
        private ClassroomRepository $classroomRepository,
        private \App\Repository\StudentRepository $studentRepository,
        private GradeRepository $gradeRepository,
        private SubjectRepository $subjectRepository,
        private GradeCalculationService $gradeCalculationService,
    ) {}

    private function decision(?float $average): string
    {
        if ($average === null) {
            return '—';
        }

        return $average >= self::PASS_MARK ? 'Admis' : 'Redouble';
    }

    /**
     * Catalogue des rapports académiques (tableau nom / actions).
     *
     * `ready` = disponible ; `soon` = nécessite le calcul des résultats (à venir).
     */
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        $reports = [
            ['name' => 'EFFECTIFS PAR NIVEAU ET PAR GENRE', 'route' => 'admin_academic_report_effectifs_genre', 'status' => 'ready'],
            ['name' => 'RÉPARTITION DES ÉLÈVES PAR ANNÉE DE NAISSANCE', 'route' => 'admin_academic_report_repartition_naissance', 'status' => 'ready'],
            ['name' => 'RÉPARTITION DES ÉLÈVES PAR NIVEAU ET PAR ÂGE', 'route' => 'admin_academic_report_repartition_age', 'status' => 'ready'],
            ['name' => 'LISTE DES MAJORS DE CLASSE (PAR CLASSE)', 'route' => 'admin_academic_report_majors_classe', 'status' => 'ready'],
            ['name' => 'LISTE DES MAJORS DE CLASSE (PAR NIVEAU)', 'route' => 'admin_academic_report_majors_niveau', 'status' => 'ready'],
            ['name' => 'TABLEAUX STATISTIQUES DES RÉSULTATS SCOLAIRES PAR CLASSE', 'route' => 'admin_academic_report_stats_classe', 'status' => 'ready'],
            ['name' => 'TABLEAUX STATISTIQUES DES RÉSULTATS SCOLAIRES PAR NIVEAU', 'route' => 'admin_academic_report_stats_niveau', 'status' => 'ready'],
            ['name' => 'SYNTHÈSE GÉNÉRALE DES RÉSULTATS SCOLAIRES', 'route' => 'admin_academic_report_synthese', 'status' => 'ready'],
            ['name' => "STATISTIQUE DES ÉLÈVES EN SITUATION D'ADMISSION, DE REDOUBLEMENT ET D'EXCLUSION (APPROCHE GENRE)", 'route' => 'admin_academic_report_genre_decision', 'status' => 'ready'],
            ['name' => 'RÉSULTATS SCOLAIRES DES ÉLÈVES AFFECTÉS PAR NIVEAU ET PAR CLASSE', 'route' => 'admin_academic_report_affectes_classe', 'status' => 'ready'],
            ['name' => 'RÉSULTATS SCOLAIRES DES ÉLÈVES AFFECTÉS PAR NIVEAU', 'route' => 'admin_academic_report_affectes_niveau', 'status' => 'ready'],
            ['name' => 'RÉSULTATS SCOLAIRES DES ÉLÈVES NON-AFFECTÉS PAR NIVEAU ET PAR CLASSE', 'route' => 'admin_academic_report_non_affectes_classe', 'status' => 'ready'],
            ['name' => 'RÉSULTATS SCOLAIRES DES ÉLÈVES NON-AFFECTÉS PAR NIVEAU', 'route' => 'admin_academic_report_non_affectes_niveau', 'status' => 'ready'],
            ['name' => 'LISTE DES MOYENNES PAR MATIÈRE', 'route' => 'admin_academic_report_moyennes_matiere', 'status' => 'ready'],
            ['name' => 'LISTE DES CLASSES PAR ORDRE DE MÉRITE', 'route' => 'admin_academic_report_classes_merite', 'status' => 'ready'],
            ['name' => 'LISTE NOMINATIVE DES ÉLÈVES ET RÉSULTATS PAR NIVEAU ET PAR CLASSE', 'route' => 'admin_academic_report_nominative', 'status' => 'ready'],
            ['name' => "PROPORTION D'ÉLÈVES N'AYANT PAS OBTENU LA MOYENNE", 'route' => 'admin_academic_report_proportion', 'status' => 'ready'],
            ["name" => "LISTE DES ÉLÈVES AVEC DÉCISION DE FIN D'ANNÉE", 'route' => 'admin_academic_report_decisions', 'status' => 'ready'],
            ['name' => 'TABLEAU RÉCAPITULATIF DES MOYENNES PAR MATIÈRE', 'route' => 'admin_academic_report_recap_matiere', 'status' => 'ready'],
            ['name' => 'BORDEREAU ANNUEL', 'route' => 'admin_academic_report_classe_selection', 'route_args' => ['report' => 'bordereau'], 'status' => 'select'],
            ['name' => 'LISTE DES ÉLÈVES AYANT OBTENU UNE MOYENNE SUPÉRIEURE À…', 'route' => 'admin_academic_report_moyenne_sup', 'status' => 'ready'],
            ['name' => 'RAPPORT LIVRET SCOLAIRE', 'route' => 'admin_academic_report_classe_selection', 'route_args' => ['report' => 'livret'], 'status' => 'select'],
        ];

        return $this->render('academic_report/index.html.twig', ['reports' => $reports]);
    }

    #[Route('/effectifs-genre/pdf', name: 'effectifs_genre', methods: ['GET'])]
    public function effectifsGenre(): Response
    {
        [$school, $year, $students] = $this->activeStudents();
        if (!$school) {
            return $this->redirectToRoute('admin_academic_report_index');
        }

        // Regroupement par niveau → genre.
        $byLevel = [];
        $totals = ['boys' => 0, 'girls' => 0, 'unknown' => 0, 'total' => 0];
        foreach ($students as $student) {
            $level = $student->getLevel() ?? $student->getClassroom()?->getLevel();
            $key = $level?->getId() ?? 0;
            if (!isset($byLevel[$key])) {
                $byLevel[$key] = [
                    'label' => $level?->getName() ?? 'Sans niveau',
                    'order' => $level?->getOrderNumber() ?? 9999,
                    'boys' => 0, 'girls' => 0, 'unknown' => 0, 'total' => 0,
                ];
            }
            $g = $student->getGender();
            $bucket = $g === 'M' ? 'boys' : ($g === 'F' ? 'girls' : 'unknown');
            $byLevel[$key][$bucket]++;
            $byLevel[$key]['total']++;
            $totals[$bucket]++;
            $totals['total']++;
        }
        usort($byLevel, static fn ($a, $b) => $a['order'] <=> $b['order']);

        return $this->renderPdf('academic_report/effectifs_genre_pdf.html.twig', [
            'school' => $school,
            'school_year' => $year,
            'by_level' => $byLevel,
            'totals' => $totals,
            'logo_data' => $this->logoData($school),
            'generated_at' => new \DateTime(),
        ], 'EFFECTIFS_NIVEAU_GENRE.pdf');
    }

    #[Route('/repartition-naissance/pdf', name: 'repartition_naissance', methods: ['GET'])]
    public function repartitionNaissance(): Response
    {
        [$school, $year, $students] = $this->activeStudents();
        if (!$school) {
            return $this->redirectToRoute('admin_academic_report_index');
        }

        // Regroupement par année de naissance → genre.
        $byYear = [];
        $totals = ['boys' => 0, 'girls' => 0, 'total' => 0];
        foreach ($students as $student) {
            $birthYear = $student->getDateOfBirth()?->format('Y') ?: 'Inconnue';
            if (!isset($byYear[$birthYear])) {
                $byYear[$birthYear] = ['year' => $birthYear, 'boys' => 0, 'girls' => 0, 'total' => 0];
            }
            $g = $student->getGender();
            if ($g === 'M') { $byYear[$birthYear]['boys']++; $totals['boys']++; }
            elseif ($g === 'F') { $byYear[$birthYear]['girls']++; $totals['girls']++; }
            $byYear[$birthYear]['total']++;
            $totals['total']++;
        }
        krsort($byYear);

        return $this->renderPdf('academic_report/repartition_naissance_pdf.html.twig', [
            'school' => $school,
            'school_year' => $year,
            'by_year' => array_values($byYear),
            'totals' => $totals,
            'logo_data' => $this->logoData($school),
            'generated_at' => new \DateTime(),
        ], 'REPARTITION_ANNEE_NAISSANCE.pdf');
    }

    #[Route('/repartition-age/pdf', name: 'repartition_age', methods: ['GET'])]
    public function repartitionAge(): Response
    {
        [$school, $year, $students] = $this->activeStudents();
        if (!$school) {
            return $this->redirectToRoute('admin_academic_report_index');
        }

        // Âge calculé à la fin de l'année civile de référence.
        $refYear = (int) ($year?->getEndDate()?->format('Y') ?? date('Y'));

        $byLevel = [];
        $ages = [];
        foreach ($students as $student) {
            $birthYear = $student->getDateOfBirth()?->format('Y');
            if (!$birthYear) {
                continue;
            }
            $age = $refYear - (int) $birthYear;
            $ages[$age] = true;

            $level = $student->getLevel() ?? $student->getClassroom()?->getLevel();
            $key = $level?->getId() ?? 0;
            if (!isset($byLevel[$key])) {
                $byLevel[$key] = ['label' => $level?->getName() ?? 'Sans niveau', 'order' => $level?->getOrderNumber() ?? 9999, 'ages' => [], 'total' => 0];
            }
            $byLevel[$key]['ages'][$age] = ($byLevel[$key]['ages'][$age] ?? 0) + 1;
            $byLevel[$key]['total']++;
        }
        usort($byLevel, static fn ($a, $b) => $a['order'] <=> $b['order']);
        ksort($ages);
        $ageList = array_keys($ages);

        return $this->renderPdf('academic_report/repartition_age_pdf.html.twig', [
            'school' => $school,
            'school_year' => $year,
            'by_level' => $byLevel,
            'age_list' => $ageList,
            'logo_data' => $this->logoData($school),
            'generated_at' => new \DateTime(),
        ], 'REPARTITION_NIVEAU_AGE.pdf');
    }

    #[Route('/majors-classe/pdf', name: 'majors_classe', methods: ['GET'])]
    public function majorsClasse(): Response
    {
        [$school, $year, $classes] = $this->computeClassResults();
        if (!$school) {
            return $this->redirectToRoute('admin_academic_report_index');
        }

        usort($classes, static fn ($a, $b) => [$a['levelOrder'], $a['label']] <=> [$b['levelOrder'], $b['label']]);

        return $this->renderPdf('academic_report/majors_classe_pdf.html.twig', [
            'school' => $school,
            'school_year' => $year,
            'classes' => $classes,
            'logo_data' => $this->logoData($school),
            'generated_at' => new \DateTime(),
        ], 'MAJORS_PAR_CLASSE.pdf');
    }

    #[Route('/majors-niveau/pdf', name: 'majors_niveau', methods: ['GET'])]
    public function majorsNiveau(): Response
    {
        [$school, $year, $classes] = $this->computeClassResults();
        if (!$school) {
            return $this->redirectToRoute('admin_academic_report_index');
        }

        // Regroupement des majors de classe par niveau.
        $byLevel = [];
        foreach ($classes as $class) {
            $key = $class['levelOrder'] . '|' . $class['levelLabel'];
            if (!isset($byLevel[$key])) {
                $byLevel[$key] = ['label' => $class['levelLabel'], 'order' => $class['levelOrder'], 'classes' => []];
            }
            $byLevel[$key]['classes'][] = $class;
        }
        usort($byLevel, static fn ($a, $b) => $a['order'] <=> $b['order']);
        foreach ($byLevel as &$group) {
            usort($group['classes'], static fn ($a, $b) => strcmp((string) $a['label'], (string) $b['label']));
        }
        unset($group);

        return $this->renderPdf('academic_report/majors_niveau_pdf.html.twig', [
            'school' => $school,
            'school_year' => $year,
            'by_level' => $byLevel,
            'logo_data' => $this->logoData($school),
            'generated_at' => new \DateTime(),
        ], 'MAJORS_PAR_NIVEAU.pdf');
    }

    #[Route('/classes-merite/pdf', name: 'classes_merite', methods: ['GET'])]
    public function classesMerite(): Response
    {
        [$school, $year, $classes] = $this->computeClassResults();
        if (!$school) {
            return $this->redirectToRoute('admin_academic_report_index');
        }

        // Tri par moyenne de classe décroissante ; classes sans moyenne en fin.
        usort($classes, static function ($a, $b) {
            if ($a['classAverage'] === null && $b['classAverage'] === null) {
                return 0;
            }
            if ($a['classAverage'] === null) {
                return 1;
            }
            if ($b['classAverage'] === null) {
                return -1;
            }
            return $b['classAverage'] <=> $a['classAverage'];
        });

        return $this->renderPdf('academic_report/classes_merite_pdf.html.twig', [
            'school' => $school,
            'school_year' => $year,
            'classes' => $classes,
            'logo_data' => $this->logoData($school),
            'generated_at' => new \DateTime(),
        ], 'CLASSES_ORDRE_MERITE.pdf');
    }

    #[Route('/moyenne-superieure/pdf', name: 'moyenne_sup', methods: ['GET'])]
    public function moyenneSup(Request $request): Response
    {
        $min = (float) str_replace(',', '.', (string) $request->query->get('min', (string) self::PASS_MARK));
        if ($min < 0 || $min > 20) {
            $min = self::PASS_MARK;
        }

        [$school, $year, $classes] = $this->computeClassResults();
        if (!$school) {
            return $this->redirectToRoute('admin_academic_report_index');
        }

        $rows = [];
        foreach ($classes as $class) {
            foreach ($class['students'] as $entry) {
                if ($entry['average'] >= $min) {
                    $rows[] = [
                        'student' => $entry['student'],
                        'classroom' => $class['label'],
                        'level' => $class['levelLabel'],
                        'average' => $entry['average'],
                    ];
                }
            }
        }
        usort($rows, static fn ($a, $b) => $b['average'] <=> $a['average']);

        return $this->renderPdf('academic_report/moyenne_sup_pdf.html.twig', [
            'school' => $school,
            'school_year' => $year,
            'min' => $min,
            'rows' => $rows,
            'logo_data' => $this->logoData($school),
            'generated_at' => new \DateTime(),
        ], 'MOYENNE_SUPERIEURE.pdf');
    }

    #[Route('/stats-classe/pdf', name: 'stats_classe', methods: ['GET'])]
    public function statsClasse(): Response
    {
        [$school, $year, $classes, , $overall] = $this->computeResultStats();
        if (!$school) {
            return $this->redirectToRoute('admin_academic_report_index');
        }
        usort($classes, static fn ($a, $b) => [$a['levelOrder'], $a['label']] <=> [$b['levelOrder'], $b['label']]);

        return $this->renderPdf('academic_report/stats_classe_pdf.html.twig', [
            'school' => $school, 'school_year' => $year, 'classes' => $classes, 'overall' => $overall,
            'logo_data' => $this->logoData($school), 'generated_at' => new \DateTime(),
        ], 'STATS_RESULTATS_CLASSE.pdf');
    }

    #[Route('/stats-niveau/pdf', name: 'stats_niveau', methods: ['GET'])]
    public function statsNiveau(): Response
    {
        [$school, $year, , $levels, $overall] = $this->computeResultStats();
        if (!$school) {
            return $this->redirectToRoute('admin_academic_report_index');
        }

        return $this->renderPdf('academic_report/stats_niveau_pdf.html.twig', [
            'school' => $school, 'school_year' => $year, 'levels' => $levels, 'overall' => $overall,
            'logo_data' => $this->logoData($school), 'generated_at' => new \DateTime(),
        ], 'STATS_RESULTATS_NIVEAU.pdf');
    }

    #[Route('/synthese/pdf', name: 'synthese', methods: ['GET'])]
    public function synthese(): Response
    {
        [$school, $year, , $levels, $overall] = $this->computeResultStats();
        if (!$school) {
            return $this->redirectToRoute('admin_academic_report_index');
        }

        return $this->renderPdf('academic_report/synthese_pdf.html.twig', [
            'school' => $school, 'school_year' => $year, 'levels' => $levels, 'overall' => $overall,
            'logo_data' => $this->logoData($school), 'generated_at' => new \DateTime(),
        ], 'SYNTHESE_RESULTATS.pdf');
    }

    #[Route('/proportion-sous-moyenne/pdf', name: 'proportion', methods: ['GET'])]
    public function proportion(): Response
    {
        [$school, $year, $classes, $levels, $overall] = $this->computeResultStats();
        if (!$school) {
            return $this->redirectToRoute('admin_academic_report_index');
        }
        usort($classes, static fn ($a, $b) => [$a['levelOrder'], $a['label']] <=> [$b['levelOrder'], $b['label']]);

        return $this->renderPdf('academic_report/proportion_pdf.html.twig', [
            'school' => $school, 'school_year' => $year, 'classes' => $classes, 'levels' => $levels, 'overall' => $overall,
            'logo_data' => $this->logoData($school), 'generated_at' => new \DateTime(),
        ], 'PROPORTION_SOUS_MOYENNE.pdf');
    }

    #[Route('/genre-decision/pdf', name: 'genre_decision', methods: ['GET'])]
    public function genreDecision(): Response
    {
        [$school, $year, , $levels, $overall] = $this->computeResultStats();
        if (!$school) {
            return $this->redirectToRoute('admin_academic_report_index');
        }

        return $this->renderPdf('academic_report/genre_decision_pdf.html.twig', [
            'school' => $school, 'school_year' => $year, 'levels' => $levels, 'overall' => $overall,
            'logo_data' => $this->logoData($school), 'generated_at' => new \DateTime(),
        ], 'ADMISSION_REDOUBLEMENT_GENRE.pdf');
    }

    #[Route('/affectes-classe/pdf', name: 'affectes_classe', methods: ['GET'])]
    public function affectesClasse(): Response
    {
        return $this->statusResultsByClass('affecte', 'RÉSULTATS SCOLAIRES DES ÉLÈVES AFFECTÉS PAR NIVEAU ET PAR CLASSE', 'RESULTATS_AFFECTES_CLASSE.pdf');
    }

    #[Route('/affectes-niveau/pdf', name: 'affectes_niveau', methods: ['GET'])]
    public function affectesNiveau(): Response
    {
        return $this->statusResultsByLevel('affecte', 'RÉSULTATS SCOLAIRES DES ÉLÈVES AFFECTÉS PAR NIVEAU', 'RESULTATS_AFFECTES_NIVEAU.pdf');
    }

    #[Route('/non-affectes-classe/pdf', name: 'non_affectes_classe', methods: ['GET'])]
    public function nonAffectesClasse(): Response
    {
        return $this->statusResultsByClass('non_affecte', 'RÉSULTATS SCOLAIRES DES ÉLÈVES NON-AFFECTÉS PAR NIVEAU ET PAR CLASSE', 'RESULTATS_NON_AFFECTES_CLASSE.pdf');
    }

    #[Route('/non-affectes-niveau/pdf', name: 'non_affectes_niveau', methods: ['GET'])]
    public function nonAffectesNiveau(): Response
    {
        return $this->statusResultsByLevel('non_affecte', 'RÉSULTATS SCOLAIRES DES ÉLÈVES NON-AFFECTÉS PAR NIVEAU', 'RESULTATS_NON_AFFECTES_NIVEAU.pdf');
    }

    private function statusResultsByClass(string $status, string $title, string $filename): Response
    {
        [$school, $year, $classes, , $overall] = $this->computeResultStats($status);
        if (!$school) {
            return $this->redirectToRoute('admin_academic_report_index');
        }
        usort($classes, static fn ($a, $b) => [$a['levelOrder'], $a['label']] <=> [$b['levelOrder'], $b['label']]);

        return $this->renderPdf('academic_report/stats_classe_pdf.html.twig', [
            'school' => $school, 'school_year' => $year, 'classes' => $classes, 'overall' => $overall,
            'report_title' => $title,
            'logo_data' => $this->logoData($school), 'generated_at' => new \DateTime(),
        ], $filename);
    }

    private function statusResultsByLevel(string $status, string $title, string $filename): Response
    {
        [$school, $year, , $levels, $overall] = $this->computeResultStats($status);
        if (!$school) {
            return $this->redirectToRoute('admin_academic_report_index');
        }

        return $this->renderPdf('academic_report/stats_niveau_pdf.html.twig', [
            'school' => $school, 'school_year' => $year, 'levels' => $levels, 'overall' => $overall,
            'report_title' => $title,
            'logo_data' => $this->logoData($school), 'generated_at' => new \DateTime(),
        ], $filename);
    }

    #[Route('/nominative/pdf', name: 'nominative', methods: ['GET'])]
    public function nominative(): Response
    {
        [$school, $year, $classes] = $this->computeNominalResults();
        if (!$school) {
            return $this->redirectToRoute('admin_academic_report_index');
        }

        // Regroupement par niveau.
        $byLevel = [];
        foreach ($classes as $class) {
            $key = $class['levelOrder'] . '|' . $class['levelLabel'];
            if (!isset($byLevel[$key])) {
                $byLevel[$key] = ['label' => $class['levelLabel'], 'order' => $class['levelOrder'], 'classes' => []];
            }
            $byLevel[$key]['classes'][] = $class;
        }
        usort($byLevel, static fn ($a, $b) => $a['order'] <=> $b['order']);

        return $this->renderPdf('academic_report/nominative_pdf.html.twig', [
            'school' => $school, 'school_year' => $year, 'by_level' => $byLevel,
            'logo_data' => $this->logoData($school), 'generated_at' => new \DateTime(),
        ], 'LISTE_NOMINATIVE_RESULTATS.pdf');
    }

    #[Route('/decisions/pdf', name: 'decisions', methods: ['GET'])]
    public function decisions(): Response
    {
        [$school, $year, $classes] = $this->computeNominalResults();
        if (!$school) {
            return $this->redirectToRoute('admin_academic_report_index');
        }
        usort($classes, static fn ($a, $b) => [$a['levelOrder'], $a['label']] <=> [$b['levelOrder'], $b['label']]);

        return $this->renderPdf('academic_report/decisions_pdf.html.twig', [
            'school' => $school, 'school_year' => $year, 'classes' => $classes,
            'logo_data' => $this->logoData($school), 'generated_at' => new \DateTime(),
        ], 'DECISIONS_FIN_ANNEE.pdf');
    }

    #[Route('/moyennes-matiere/pdf', name: 'moyennes_matiere', methods: ['GET'])]
    public function moyennesMatiere(): Response
    {
        [$school, $year, $classes, $subjects] = $this->computeSubjectAverages();
        if (!$school) {
            return $this->redirectToRoute('admin_academic_report_index');
        }
        usort($classes, static fn ($a, $b) => [$a['levelOrder'], $a['label']] <=> [$b['levelOrder'], $b['label']]);

        return $this->renderPdf('academic_report/moyennes_matiere_pdf.html.twig', [
            'school' => $school, 'school_year' => $year, 'classes' => $classes, 'subjects' => $subjects,
            'logo_data' => $this->logoData($school), 'generated_at' => new \DateTime(),
        ], 'MOYENNES_PAR_MATIERE.pdf');
    }

    #[Route('/recap-matiere/pdf', name: 'recap_matiere', methods: ['GET'])]
    public function recapMatiere(): Response
    {
        [$school, $year, $classes, $subjects] = $this->computeSubjectAverages();
        if (!$school) {
            return $this->redirectToRoute('admin_academic_report_index');
        }
        usort($classes, static fn ($a, $b) => [$a['levelOrder'], $a['label']] <=> [$b['levelOrder'], $b['label']]);

        return $this->renderPdf('academic_report/recap_matiere_pdf.html.twig', [
            'school' => $school, 'school_year' => $year, 'classes' => $classes, 'subjects' => $subjects,
            'logo_data' => $this->logoData($school), 'generated_at' => new \DateTime(),
        ], 'RECAP_MOYENNES_MATIERE.pdf', 'landscape');
    }

    /**
     * Sélection d'une classe avant le bordereau annuel ou le livret scolaire.
     */
    #[Route('/selection/{report}', name: 'classe_selection', methods: ['GET'], requirements: ['report' => 'bordereau|livret'])]
    public function classeSelection(string $report): Response
    {
        $school = $this->schoolContextService->getCurrentSchool();
        if (!$school) {
            $this->addFlash('warning', 'Veuillez sélectionner un établissement pour générer ce rapport.');
            return $this->redirectToRoute('admin_academic_report_index');
        }
        $year = $this->schoolContextService->getCurrentSchoolYear();

        return $this->render('academic_report/classe_selection.html.twig', [
            'report' => $report,
            'report_title' => $report === 'bordereau' ? 'BORDEREAU ANNUEL' : 'RAPPORT LIVRET SCOLAIRE',
            'classrooms' => $this->classroomRepository->findBySchoolAndYear($school->getId(), $year?->getId()),
        ]);
    }

    #[Route('/bordereau/classe/{id}/pdf', name: 'bordereau', methods: ['GET'])]
    public function bordereau(Classroom $classroom): Response
    {
        $school = $this->schoolContextService->getCurrentSchool();
        if (!$school || $classroom->getSchool()?->getId() !== $school->getId()) {
            $this->addFlash('warning', 'Classe introuvable pour l\'établissement courant.');
            return $this->redirectToRoute('admin_academic_report_index');
        }
        $yearId = $this->schoolContextService->getCurrentSchoolYear()?->getId();

        $students = $this->studentRepository->findActiveByClassroom($classroom->getId());
        $subjects = $this->orderedSubjects($school->getId());

        $rows = [];
        $usedSubjects = [];
        foreach ($students as $student) {
            $map = $this->gradeRepository->annualSubjectAveragesByStudent($student->getId(), $yearId, true);
            foreach (array_keys($map) as $sid) {
                $usedSubjects[$sid] = true;
            }
            $general = $this->gradeRepository->calculateAnnualGeneralAverageByStudent($student->getId(), $yearId, true);
            $rows[] = ['student' => $student, 'subjects' => $map, 'general' => $general];
        }

        // Rang sur la moyenne générale.
        $ranked = array_values(array_filter($rows, static fn ($r) => $r['general'] !== null));
        usort($ranked, static fn ($a, $b) => $b['general'] <=> $a['general']);
        $rankById = [];
        foreach ($ranked as $i => $r) {
            $rankById[$r['student']->getId()] = $i + 1;
        }
        foreach ($rows as &$r) {
            $r['rank'] = $rankById[$r['student']->getId()] ?? null;
        }
        unset($r);
        usort($rows, static fn ($a, $b) => strcmp($a['student']->getLastName(), $b['student']->getLastName()));

        $subjects = array_values(array_filter($subjects, static fn ($s) => isset($usedSubjects[$s->getId()])));

        return $this->renderPdf('academic_report/bordereau_pdf.html.twig', [
            'school' => $school, 'classroom' => $classroom, 'subjects' => $subjects, 'rows' => $rows,
            'logo_data' => $this->logoData($school), 'generated_at' => new \DateTime(),
        ], sprintf('BORDEREAU_%s.pdf', $classroom->getName()), 'landscape');
    }

    #[Route('/livret/classe/{id}/pdf', name: 'livret', methods: ['GET'])]
    public function livret(Classroom $classroom): Response
    {
        $school = $this->schoolContextService->getCurrentSchool();
        if (!$school || $classroom->getSchool()?->getId() !== $school->getId()) {
            $this->addFlash('warning', 'Classe introuvable pour l\'établissement courant.');
            return $this->redirectToRoute('admin_academic_report_index');
        }
        $year = $this->schoolContextService->getCurrentSchoolYear();
        $yearId = $year?->getId();

        $students = $this->studentRepository->findActiveByClassroom($classroom->getId());
        $subjects = $this->orderedSubjects($school->getId());

        // Moyennes générales pour le rang.
        $generals = [];
        foreach ($students as $student) {
            $g = $this->gradeRepository->calculateAnnualGeneralAverageByStudent($student->getId(), $yearId, true);
            if ($g !== null) {
                $generals[$student->getId()] = $g;
            }
        }
        arsort($generals);
        $rankById = [];
        $rank = 1;
        foreach ($generals as $sid => $g) {
            $rankById[$sid] = $rank++;
        }

        $booklets = [];
        foreach ($students as $student) {
            $map = $this->gradeRepository->annualSubjectAveragesByStudent($student->getId(), $yearId, true);
            $lines = [];
            foreach ($subjects as $subject) {
                if (isset($map[$subject->getId()])) {
                    $avg = $map[$subject->getId()];
                    $lines[] = [
                        'subject' => $subject->getName(),
                        'coefficient' => (float) $subject->getCoefficient(),
                        'average' => $avg,
                        'appreciation' => $this->gradeCalculationService->getAppreciation($avg),
                    ];
                }
            }
            $general = $generals[$student->getId()] ?? null;
            $booklets[] = [
                'student' => $student,
                'lines' => $lines,
                'general' => $general,
                'rank' => $rankById[$student->getId()] ?? null,
                'total' => \count($generals),
                'mention' => $general !== null ? $this->gradeCalculationService->getMention($general) : null,
                'decision' => $this->decision($general),
            ];
        }
        usort($booklets, static fn ($a, $b) => strcmp($a['student']->getLastName(), $b['student']->getLastName()));

        return $this->renderPdf('academic_report/livret_pdf.html.twig', [
            'school' => $school, 'classroom' => $classroom, 'school_year' => $year, 'booklets' => $booklets,
            'logo_data' => $this->logoData($school), 'generated_at' => new \DateTime(),
        ], sprintf('LIVRET_%s.pdf', $classroom->getName()));
    }

    /**
     * Matières de l'établissement triées par nom.
     *
     * @return list<\App\Entity\Subject>
     */
    private function orderedSubjects(int $schoolId): array
    {
        $subjects = $this->subjectRepository->findBySchool($schoolId);
        usort($subjects, static fn ($a, $b) => strcmp((string) $a->getName(), (string) $b->getName()));

        return $subjects;
    }

    /**
     * Liste nominative par classe : tous les élèves actifs avec moyenne générale
     * annuelle (nullable), rang (parmi les notés) et décision.
     *
     * @return array{0: ?School, 1: ?\App\Entity\SchoolYear, 2: list<array<string, mixed>>}
     */
    private function computeNominalResults(): array
    {
        $school = $this->schoolContextService->getCurrentSchool();
        $year = $this->schoolContextService->getCurrentSchoolYear();
        if (!$school) {
            $this->addFlash('warning', 'Veuillez sélectionner un établissement pour générer ce rapport.');
            return [null, null, []];
        }
        $yearId = $year?->getId();

        $classes = [];
        foreach ($this->classroomRepository->findBySchoolAndYear($school->getId(), $yearId) as $classroom) {
            $entries = [];
            foreach ($this->studentRepository->findActiveByClassroom($classroom->getId()) as $student) {
                $entries[] = [
                    'student' => $student,
                    'average' => $this->gradeRepository->calculateAnnualGeneralAverageByStudent($student->getId(), $yearId, true),
                ];
            }

            // Rang parmi les notés.
            $graded = array_values(array_filter($entries, static fn ($e) => $e['average'] !== null));
            usort($graded, static fn ($a, $b) => $b['average'] <=> $a['average']);
            $rankById = [];
            foreach ($graded as $i => $e) {
                $rankById[$e['student']->getId()] = $i + 1;
            }
            foreach ($entries as &$e) {
                $e['rank'] = $rankById[$e['student']->getId()] ?? null;
                $e['decision'] = $this->decision($e['average']);
            }
            unset($e);
            // Tri : notés par moyenne décroissante puis non notés par nom.
            usort($entries, static function ($a, $b) {
                if ($a['average'] === null && $b['average'] === null) {
                    return strcmp($a['student']->getLastName(), $b['student']->getLastName());
                }
                if ($a['average'] === null) {
                    return 1;
                }
                if ($b['average'] === null) {
                    return -1;
                }
                return $b['average'] <=> $a['average'];
            });

            $level = $classroom->getLevel();
            $classes[] = [
                'label' => $classroom->getName(),
                'levelLabel' => $level?->getName() ?? 'Sans niveau',
                'levelOrder' => $level?->getOrderNumber() ?? 9999,
                'entries' => $entries,
                'count' => \count($entries),
                'graded' => \count($graded),
            ];
        }

        return [$school, $year, $classes];
    }

    /**
     * Moyennes de classe par matière (moyenne des moyennes annuelles des élèves).
     *
     * @return array{0: ?School, 1: ?\App\Entity\SchoolYear, 2: list<array<string, mixed>>, 3: list<\App\Entity\Subject>}
     */
    private function computeSubjectAverages(): array
    {
        $school = $this->schoolContextService->getCurrentSchool();
        $year = $this->schoolContextService->getCurrentSchoolYear();
        if (!$school) {
            $this->addFlash('warning', 'Veuillez sélectionner un établissement pour générer ce rapport.');
            return [null, null, [], []];
        }
        $yearId = $year?->getId();
        $subjects = $this->orderedSubjects($school->getId());

        $classes = [];
        $usedSubjects = [];
        foreach ($this->classroomRepository->findBySchoolAndYear($school->getId(), $yearId) as $classroom) {
            $sums = [];
            $counts = [];
            foreach ($this->studentRepository->findActiveByClassroom($classroom->getId()) as $student) {
                foreach ($this->gradeRepository->annualSubjectAveragesByStudent($student->getId(), $yearId, true) as $sid => $avg) {
                    $sums[$sid] = ($sums[$sid] ?? 0) + $avg;
                    $counts[$sid] = ($counts[$sid] ?? 0) + 1;
                    $usedSubjects[$sid] = true;
                }
            }
            $subjectAverages = [];
            foreach ($sums as $sid => $sum) {
                $subjectAverages[$sid] = round($sum / $counts[$sid], 2);
            }

            $level = $classroom->getLevel();
            $classes[] = [
                'label' => $classroom->getName(),
                'levelLabel' => $level?->getName() ?? 'Sans niveau',
                'levelOrder' => $level?->getOrderNumber() ?? 9999,
                'subjectAverages' => $subjectAverages,
            ];
        }

        $subjects = array_values(array_filter($subjects, static fn ($s) => isset($usedSubjects[$s->getId()])));

        return [$school, $year, $classes, $subjects];
    }

    /**
     * Statistiques de résultats (admission/redoublement, moyennes, approche genre)
     * par classe, agrégées par niveau et au global. Décision calculée : moyenne
     * annuelle ≥ seuil = Admis, sinon Redouble (l'exclusion n'étant pas déductible
     * automatiquement, elle est laissée à zéro).
     *
     * @return array{0: ?School, 1: ?\App\Entity\SchoolYear, 2: list<array<string, mixed>>, 3: list<array<string, mixed>>, 4: array<string, mixed>}
     */
    private function computeResultStats(?string $statusFilter = null): array
    {
        [$school, $year, $rawClasses] = $this->computeClassResults($statusFilter);
        if (!$school) {
            return [null, null, [], [], $this->emptyStatBucket()];
        }

        $classes = [];
        foreach ($rawClasses as $c) {
            $stat = $this->emptyStatBucket();
            $stat['count'] = $c['count'];
            foreach ($c['students'] as $entry) {
                $this->accumulate($stat, (float) $entry['average'], $entry['student']->getGender());
            }
            $stat['label'] = $c['label'];
            $stat['levelLabel'] = $c['levelLabel'];
            $stat['levelOrder'] = $c['levelOrder'];
            $classes[] = $stat;
        }

        // Agrégation par niveau.
        $levels = [];
        foreach ($classes as $stat) {
            $key = $stat['levelOrder'] . '|' . $stat['levelLabel'];
            if (!isset($levels[$key])) {
                $levels[$key] = $this->emptyStatBucket();
                $levels[$key]['label'] = $stat['levelLabel'];
                $levels[$key]['levelOrder'] = $stat['levelOrder'];
                $levels[$key]['classes'] = 0;
            }
            $this->mergeStat($levels[$key], $stat);
            $levels[$key]['classes']++;
        }
        usort($levels, static fn ($a, $b) => $a['levelOrder'] <=> $b['levelOrder']);

        // Global.
        $overall = $this->emptyStatBucket();
        $overall['classes'] = \count($classes);
        foreach ($classes as $stat) {
            $this->mergeStat($overall, $stat);
        }

        $this->finalizeStat($overall);
        foreach ($levels as &$lvl) {
            $this->finalizeStat($lvl);
        }
        unset($lvl);
        foreach ($classes as &$cl) {
            $this->finalizeStat($cl);
        }
        unset($cl);

        return [$school, $year, $classes, array_values($levels), $overall];
    }

    /** @return array<string, mixed> */
    private function emptyStatBucket(): array
    {
        return [
            'count' => 0, 'graded' => 0, 'admis' => 0, 'redouble' => 0, 'exclus' => 0,
            'sum' => 0.0, 'min' => null, 'max' => null, 'pct' => 0.0, 'average' => null,
            'g' => ['noted' => 0, 'admis' => 0, 'redouble' => 0],
            'f' => ['noted' => 0, 'admis' => 0, 'redouble' => 0],
        ];
    }

    private function accumulate(array &$stat, float $average, ?string $gender): void
    {
        $stat['graded']++;
        $stat['sum'] += $average;
        $stat['min'] = $stat['min'] === null ? $average : min($stat['min'], $average);
        $stat['max'] = $stat['max'] === null ? $average : max($stat['max'], $average);

        $pass = $average >= self::PASS_MARK;
        $pass ? $stat['admis']++ : $stat['redouble']++;

        $bucket = $gender === 'F' ? 'f' : ($gender === 'M' ? 'g' : null);
        if ($bucket !== null) {
            $stat[$bucket]['noted']++;
            $pass ? $stat[$bucket]['admis']++ : $stat[$bucket]['redouble']++;
        }
    }

    private function mergeStat(array &$target, array $src): void
    {
        foreach (['count', 'graded', 'admis', 'redouble', 'exclus', 'sum'] as $k) {
            $target[$k] += $src[$k];
        }
        if ($src['min'] !== null) {
            $target['min'] = $target['min'] === null ? $src['min'] : min($target['min'], $src['min']);
        }
        if ($src['max'] !== null) {
            $target['max'] = $target['max'] === null ? $src['max'] : max($target['max'], $src['max']);
        }
        foreach (['g', 'f'] as $b) {
            foreach (['noted', 'admis', 'redouble'] as $k) {
                $target[$b][$k] += $src[$b][$k];
            }
        }
    }

    private function finalizeStat(array &$stat): void
    {
        $stat['pct'] = $stat['graded'] > 0 ? round($stat['admis'] / $stat['graded'] * 100, 1) : 0.0;
        $stat['average'] = $stat['graded'] > 0 ? round($stat['sum'] / $stat['graded'], 2) : null;
    }

    /**
     * Calcule, pour chaque classe de l'établissement / année courants, la moyenne
     * annuelle de chaque élève, la moyenne de classe et le major.
     *
     * @return array{0: ?School, 1: ?\App\Entity\SchoolYear, 2: list<array<string, mixed>>}
     */
    private function computeClassResults(?string $statusFilter = null): array
    {
        $school = $this->schoolContextService->getCurrentSchool();
        $year = $this->schoolContextService->getCurrentSchoolYear();

        if (!$school) {
            $this->addFlash('warning', 'Veuillez sélectionner un établissement pour générer ce rapport.');
            return [null, null, []];
        }

        $yearId = $year?->getId();
        $classes = [];

        foreach ($this->classroomRepository->findBySchoolAndYear($school->getId(), $yearId) as $classroom) {
            $students = $this->studentRepository->findActiveByClassroom($classroom->getId());
            if ($statusFilter !== null) {
                $students = array_values(array_filter($students, static fn ($s) => $s->getStatus() === $statusFilter));
            }

            $graded = [];
            foreach ($students as $student) {
                $average = $this->gradeRepository->calculateAnnualGeneralAverageByStudent($student->getId(), $yearId, true);
                if ($average !== null) {
                    $graded[] = ['student' => $student, 'average' => $average];
                }
            }
            usort($graded, static fn ($a, $b) => $b['average'] <=> $a['average']);

            $classAverage = null;
            if (\count($graded) > 0) {
                $classAverage = round(array_sum(array_column($graded, 'average')) / \count($graded), 2);
            }

            $level = $classroom->getLevel();
            $classes[] = [
                'classroom' => $classroom,
                'label' => $classroom->getName(),
                'levelLabel' => $level?->getName() ?? 'Sans niveau',
                'levelOrder' => $level?->getOrderNumber() ?? 9999,
                'students' => $graded,
                'count' => \count($students),
                'graded' => \count($graded),
                'classAverage' => $classAverage,
                'major' => $graded[0] ?? null,
            ];
        }

        return [$school, $year, $classes];
    }

    /**
     * Élèves actifs de l'établissement / année courants (via leurs classes).
     *
     * @return array{0: ?School, 1: ?\App\Entity\SchoolYear, 2: list<\App\Entity\Student>}
     */
    private function activeStudents(): array
    {
        $school = $this->schoolContextService->getCurrentSchool();
        $year = $this->schoolContextService->getCurrentSchoolYear();

        if (!$school) {
            $this->addFlash('warning', 'Veuillez sélectionner un établissement pour générer ce rapport.');
            return [null, null, []];
        }

        $students = [];
        foreach ($this->classroomRepository->findBySchoolAndYear($school->getId(), $year?->getId()) as $classroom) {
            foreach ($this->studentRepository->findActiveByClassroom($classroom->getId()) as $student) {
                $students[] = $student;
            }
        }

        return [$school, $year, $students];
    }

    private function logoData(?School $school): ?string
    {
        if (!$school || !$school->getLogo()) {
            return null;
        }
        $path = $this->getParameter('kernel.project_dir') . '/public/' . ltrim($school->getLogo(), '/');
        if (!is_file($path)) {
            return null;
        }
        $mime = mime_content_type($path) ?: 'image/png';

        return 'data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($path));
    }

    /**
     * @param array<string, mixed> $context
     */
    private function renderPdf(string $template, array $context, string $filename, string $orientation = 'portrait'): Response
    {
        $html = $this->renderView($template, $context);

        $options = new Options();
        $options->set('defaultFont', 'Helvetica');
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', $orientation);
        $dompdf->render();

        return new Response($dompdf->output(), Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf('inline; filename="%s"', $filename),
        ]);
    }
}
