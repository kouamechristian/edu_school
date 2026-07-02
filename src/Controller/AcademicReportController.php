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
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
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
        private \App\Repository\RegistrationRepository $registrationRepository,
        private \App\Repository\LevelRepository $levelRepository,
        private \App\Repository\PeriodRepository $periodRepository,
        private GradeRepository $gradeRepository,
        private SubjectRepository $subjectRepository,
        private GradeCalculationService $gradeCalculationService,
        private \App\Repository\CourseRepository $courseRepository,
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
            ['name' => 'RÉPARTITION DES ÉLÈVES PAR ANNÉE DE NAISSANCE', 'route' => 'admin_academic_report_repartition_naissance', 'status' => 'year_range'],
            ['name' => 'RÉPARTITION DES ÉLÈVES PAR NIVEAU ET PAR ÂGE', 'route' => 'admin_academic_report_repartition_age', 'status' => 'age_range'],
            ['name' => 'LISTE DES MAJORS DE CLASSE (PAR CLASSE)', 'route' => 'admin_academic_report_majors_classe', 'status' => 'top_period'],
            ['name' => 'LISTE DES MAJORS DE CLASSE (PAR NIVEAU)', 'route' => 'admin_academic_report_majors_niveau', 'status' => 'top_period'],
            ['name' => 'TABLEAUX STATISTIQUES DES RÉSULTATS SCOLAIRES PAR CLASSE', 'route' => 'admin_academic_report_stats_classe', 'status' => 'stats_period'],
            ['name' => 'TABLEAUX STATISTIQUES DES RÉSULTATS SCOLAIRES PAR NIVEAU', 'route' => 'admin_academic_report_stats_niveau', 'status' => 'stats_period'],
            ['name' => 'SYNTHÈSE GÉNÉRALE DES RÉSULTATS SCOLAIRES', 'route' => 'admin_academic_report_synthese', 'status' => 'stats_period'],
            ['name' => "STATISTIQUE DES ÉLÈVES EN SITUATION D'ADMISSION, DE REDOUBLEMENT ET D'EXCLUSION (APPROCHE GENRE)", 'route' => 'admin_academic_report_genre_decision', 'status' => 'period_only'],
            ['name' => 'RÉSULTATS SCOLAIRES DES ÉLÈVES AFFECTÉS PAR NIVEAU ET PAR CLASSE', 'route' => 'admin_academic_report_affectes_classe', 'status' => 'stats_period'],
            ['name' => 'RÉSULTATS SCOLAIRES DES ÉLÈVES AFFECTÉS PAR NIVEAU', 'route' => 'admin_academic_report_affectes_niveau', 'status' => 'stats_period'],
            ['name' => 'RÉSULTATS SCOLAIRES DES ÉLÈVES NON-AFFECTÉS PAR NIVEAU ET PAR CLASSE', 'route' => 'admin_academic_report_non_affectes_classe', 'status' => 'stats_period'],
            ['name' => 'RÉSULTATS SCOLAIRES DES ÉLÈVES NON-AFFECTÉS PAR NIVEAU', 'route' => 'admin_academic_report_non_affectes_niveau', 'status' => 'stats_period'],
            ['name' => 'LISTE DES MOYENNES PAR MATIÈRE', 'route' => 'admin_academic_report_moyennes_matiere', 'status' => 'period_excel'],
            ['name' => 'LISTE DES CLASSES PAR ORDRE DE MÉRITE', 'route' => 'admin_academic_report_classes_merite', 'status' => 'class_period'],
            ['name' => 'LISTE NOMINATIVE DES ÉLÈVES ET RÉSULTATS PAR NIVEAU ET PAR CLASSE', 'route' => 'admin_academic_report_nominative', 'status' => 'quality_period'],
            ['name' => "PROPORTION D'ÉLÈVES N'AYANT PAS OBTENU LA MOYENNE", 'route' => 'admin_academic_report_proportion', 'status' => 'period_only'],
            ["name" => "LISTE DES ÉLÈVES AVEC DÉCISION DE FIN D'ANNÉE", 'route' => 'admin_academic_report_decisions', 'status' => 'level_all'],
            ['name' => 'TABLEAU RÉCAPITULATIF DES MOYENNES PAR MATIÈRE', 'route' => 'admin_academic_report_recap_matiere', 'status' => 'level_period'],
            ['name' => 'BORDEREAU ANNUEL', 'route' => 'admin_academic_report_bordereau_niveau', 'status' => 'level_only'],
            ['name' => 'LISTE DES ÉLÈVES AYANT OBTENU UNE MOYENNE SUPÉRIEURE À…', 'route' => 'admin_academic_report_moyenne_sup', 'status' => 'sup_period'],
            ['name' => 'RAPPORT LIVRET SCOLAIRE', 'route' => 'admin_academic_report_livret_student', 'status' => 'livret'],
        ];

        // Périodes et classes de l'année courante (pour les rapports paramétrés).
        $school = $this->schoolContextService->getCurrentSchool();
        $year = $this->schoolContextService->getCurrentSchoolYear();
        $periods = $this->periodRepository->findBySchoolAndYear($school?->getId(), $year?->getId());
        $classrooms = $this->classroomRepository->findBySchoolAndYear($school?->getId(), $year?->getId());
        $levels = $school ? $this->levelRepository->findBySchool($school->getId()) : [];

        // Cascade niveau → classe → élève pour le modal du livret scolaire.
        $classesByLevel = [];
        $studentsByClass = [];
        foreach ($classrooms as $c) {
            $classesByLevel[$c->getLevel()?->getId() ?? 0][] = ['id' => $c->getId(), 'name' => $c->getName()];
            foreach ($this->studentRepository->findActiveByClassroom($c->getId()) as $s) {
                $studentsByClass[$c->getId()][] = [
                    'id' => $s->getId(),
                    'name' => $s->getLastName() . ' ' . $s->getFirstName() . ' (' . ($s->getMatriculeInterne() ?: '—') . ')',
                ];
            }
        }

        return $this->render('academic_report/index.html.twig', [
            'reports' => $reports,
            'periods' => $periods,
            'classrooms' => $classrooms,
            'levels' => $levels,
            'classes_by_level' => $classesByLevel,
            'students_by_class' => $studentsByClass,
        ]);
    }

    #[Route('/effectifs-genre/pdf', name: 'effectifs_genre', methods: ['GET'])]
    public function effectifsGenre(): Response
    {
        $school = $this->schoolContextService->getCurrentSchool();
        $year = $this->schoolContextService->getCurrentSchoolYear();
        if (!$school) {
            $this->addFlash('warning', 'Veuillez sélectionner un établissement pour générer ce rapport.');
            return $this->redirectToRoute('admin_academic_report_index');
        }
        $schoolId = $school->getId();
        $yearId = $year?->getId();

        // Un « seau » d'effectif par niveau de l'établissement (niveaux sans élève inclus,
        // afin de reproduire le modèle officiel qui liste tous les niveaux).
        $buckets = [];   // levelId => ventilation redoublement × nationalité × genre
        $meta = [];      // levelId => ['label', 'order', 'cycle']
        foreach ($this->levelRepository->findBySchool($schoolId) as $level) {
            $id = $level->getId();
            $buckets[$id] = $this->emptyEffectif();
            $meta[$id] = [
                'label' => $level->getName() ?? 'Sans niveau',
                'order' => $level->getOrderNumber() ?? 9999,
                'cycle' => $level->getCycle()?->getLibelle() ?: 'Autres',
            ];
        }

        // Nombre de classes par niveau (classes actives de l'année courante).
        foreach ($this->classroomRepository->findBySchoolAndYear($schoolId, $yearId) as $classroom) {
            $lid = $classroom->getLevel()?->getId();
            if ($lid !== null && isset($buckets[$lid])) {
                $buckets[$lid]['classes']++;
            }
        }

        // Ventilation des inscrits : niveau × (redoublant|non) × (national|étranger) × genre.
        foreach ($this->registrationRepository->findBySchoolAndYear($schoolId, $yearId) as $registration) {
            $student = $registration->getStudent();
            $gender = $student?->getGender();
            $col = $gender === 'M' ? 'g' : ($gender === 'F' ? 'f' : null);
            if ($col === null) {
                continue; // Genre inconnu : hors ventilation G/F du modèle.
            }

            $level = $registration->getLevel() ?? $registration->getPreRegistration()?->getRequestedLevel();
            $lid = $level?->getId() ?? 0;
            if (!isset($buckets[$lid])) {
                // Niveau hors référentiel courant (inactif ou autre école) : créé à la volée.
                $buckets[$lid] = $this->emptyEffectif();
                $meta[$lid] = [
                    'label' => $level?->getName() ?? 'Sans niveau',
                    'order' => $level?->getOrderNumber() ?? 9999,
                    'cycle' => $level?->getCycle()?->getLibelle() ?: 'Autres',
                ];
            }

            $national = $this->isNationalNationality($student?->getNationality());
            $group = ($registration->isRepeating() ? 'r' : 'nr') . ($national ? 'n' : 'e');
            $buckets[$lid][$group][$col]++;
        }

        // Regroupement par cycle (section) dans l'ordre des niveaux, avec sous-totaux.
        uksort($buckets, static fn ($a, $b) => $meta[$a]['order'] <=> $meta[$b]['order']);
        $sections = [];
        foreach ($buckets as $lid => $bucket) {
            $cycle = $meta[$lid]['cycle'];
            if (!isset($sections[$cycle])) {
                $sections[$cycle] = [
                    'label' => mb_strtoupper($cycle),
                    'order' => $meta[$lid]['order'],
                    'rows' => [],
                    'subtotal' => $this->emptyEffectif(),
                ];
            }
            $sections[$cycle]['rows'][] = $this->flattenEffectif($meta[$lid]['label'], $bucket);
            $this->mergeEffectif($sections[$cycle]['subtotal'], $bucket);
        }

        // Tri des sections, aplatissement des sous-totaux et calcul du total général.
        usort($sections, static fn ($a, $b) => $a['order'] <=> $b['order']);
        $grand = $this->emptyEffectif();
        foreach ($sections as &$section) {
            $this->mergeEffectif($grand, $section['subtotal']);
            $section['subtotal'] = $this->flattenEffectif('TOTAL ' . $section['label'], $section['subtotal']);
        }
        unset($section);

        return $this->renderPdf('academic_report/effectifs_genre_pdf.html.twig', [
            'school' => $school,
            'school_year' => $year,
            'sections' => $sections,
            'grand' => $this->flattenEffectif('TOTAL GÉNÉRAL', $grand),
            'logo_data' => $this->logoData($school),
            'generated_at' => new \DateTime(),
        ], 'EFFECTIFS_NIVEAU_GENRE.pdf', 'landscape');
    }

    /**
     * Un élève est « national » si sa nationalité est vide (par défaut, ressortissant
     * du pays de l'établissement) ou libellée « Ivoirien(ne) ». Tout le reste est
     * considéré « étranger ».
     */
    private function isNationalNationality(?string $nationality): bool
    {
        $n = trim((string) $nationality);
        if ($n === '') {
            return true;
        }
        $n = mb_strtolower($n);
        $n = strtr($n, ['à' => 'a', 'â' => 'a', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'î' => 'i', 'ï' => 'i', 'ô' => 'o', 'û' => 'u', 'ù' => 'u', 'ç' => 'c']);

        return str_contains($n, 'ivoir');
    }

    /** @return array<string, mixed> Ventilation vide : classes + 4 groupes (g/f). */
    private function emptyEffectif(): array
    {
        return [
            'classes' => 0,
            'nrn' => ['g' => 0, 'f' => 0], // non-redoublant national
            'nre' => ['g' => 0, 'f' => 0], // non-redoublant étranger
            'rn' => ['g' => 0, 'f' => 0],  // redoublant national
            're' => ['g' => 0, 'f' => 0],  // redoublant étranger
        ];
    }

    /**
     * @param array<string, mixed> $target
     * @param array<string, mixed> $src
     */
    private function mergeEffectif(array &$target, array $src): void
    {
        $target['classes'] += $src['classes'];
        foreach (['nrn', 'nre', 'rn', 're'] as $k) {
            $target[$k]['g'] += $src[$k]['g'];
            $target[$k]['f'] += $src[$k]['f'];
        }
    }

    /**
     * Aplatit une ventilation en cellules prêtes pour le tableau (avec les colonnes
     * Total = G+F par groupe et le Total général G/F/T).
     *
     * @param array<string, mixed> $b
     *
     * @return array<string, mixed>
     */
    private function flattenEffectif(string $label, array $b): array
    {
        $tg = $b['nrn']['g'] + $b['nre']['g'] + $b['rn']['g'] + $b['re']['g'];
        $tf = $b['nrn']['f'] + $b['nre']['f'] + $b['rn']['f'] + $b['re']['f'];

        return [
            'label' => $label,
            'classes' => $b['classes'],
            'nrn_g' => $b['nrn']['g'], 'nrn_f' => $b['nrn']['f'], 'nrn_t' => $b['nrn']['g'] + $b['nrn']['f'],
            'nre_g' => $b['nre']['g'], 'nre_f' => $b['nre']['f'], 'nre_t' => $b['nre']['g'] + $b['nre']['f'],
            'rn_g' => $b['rn']['g'], 'rn_f' => $b['rn']['f'], 'rn_t' => $b['rn']['g'] + $b['rn']['f'],
            're_g' => $b['re']['g'], 're_f' => $b['re']['f'], 're_t' => $b['re']['g'] + $b['re']['f'],
            'tg' => $tg, 'tf' => $tf, 'tt' => $tg + $tf,
        ];
    }

    #[Route('/repartition-naissance/pdf', name: 'repartition_naissance', methods: ['GET'])]
    public function repartitionNaissance(Request $request): Response
    {
        [$school, $year, $students] = $this->activeStudents();
        if (!$school) {
            return $this->redirectToRoute('admin_academic_report_index');
        }

        // Plage d'années demandée dans la fenêtre modale (avec garde-fous).
        $currentYear = (int) date('Y');
        $start = (int) $request->query->get('annee_debut', (string) ($currentYear - 25));
        $end = (int) $request->query->get('annee_fin', (string) $currentYear);
        if ($start < 1900) { $start = 1900; }
        if ($end > $currentYear + 5) { $end = $currentYear + 5; }
        if ($end < $start) { [$start, $end] = [$end, $start]; }
        // Borne le nombre de colonnes pour rester lisible sur une page paysage.
        if ($end - $start > 60) { $end = $start + 60; }

        $years = range($start, $end);

        // Comptage garçons/filles par année de naissance, dans la plage.
        $counts = ['g' => array_fill_keys($years, 0), 'f' => array_fill_keys($years, 0)];
        foreach ($students as $student) {
            $birthYear = (int) ($student->getDateOfBirth()?->format('Y') ?? 0);
            if ($birthYear < $start || $birthYear > $end) {
                continue;
            }
            $g = $student->getGender();
            $bucket = $g === 'M' ? 'g' : ($g === 'F' ? 'f' : null);
            if ($bucket !== null) {
                $counts[$bucket][$birthYear]++;
            }
        }

        // Lignes G / F / T alignées sur la liste des années + totaux de ligne.
        $rowG = ['label' => 'G', 'cells' => [], 'total' => 0];
        $rowF = ['label' => 'F', 'cells' => [], 'total' => 0];
        $rowT = ['label' => 'T', 'cells' => [], 'total' => 0];
        foreach ($years as $y) {
            $g = $counts['g'][$y];
            $f = $counts['f'][$y];
            $rowG['cells'][] = $g; $rowG['total'] += $g;
            $rowF['cells'][] = $f; $rowF['total'] += $f;
            $rowT['cells'][] = $g + $f; $rowT['total'] += $g + $f;
        }

        return $this->renderPdf('academic_report/repartition_naissance_pdf.html.twig', [
            'school' => $school,
            'school_year' => $year,
            'years' => $years,
            'rows' => [$rowG, $rowF, $rowT],
            'logo_data' => $this->logoData($school),
            'generated_at' => new \DateTime(),
        ], 'REPARTITION_ANNEE_NAISSANCE.pdf', 'landscape');
    }

    #[Route('/repartition-age/pdf', name: 'repartition_age', methods: ['GET'])]
    public function repartitionAge(Request $request): Response
    {
        $school = $this->schoolContextService->getCurrentSchool();
        $year = $this->schoolContextService->getCurrentSchoolYear();
        if (!$school) {
            $this->addFlash('warning', 'Veuillez sélectionner un établissement pour générer ce rapport.');
            return $this->redirectToRoute('admin_academic_report_index');
        }
        $schoolId = $school->getId();
        $yearId = $year?->getId();

        // Âge minimal / maximal demandé dans la fenêtre modale (avec garde-fous).
        $min = (int) $request->query->get('age_min', '16');
        $max = (int) $request->query->get('age_max', '19');
        if ($min < 0) { $min = 0; }
        if ($max > 120) { $max = 120; }
        if ($max < $min) { [$min, $max] = [$max, $min]; }
        if ($max - $min > 40) { $max = $min + 40; }

        // Bandes d'âge : « Moins de N ans », chaque âge de N à M, « Plus de M ans ».
        $bands = [['key' => 'lt', 'label' => 'Moins de ' . $min . ' ans']];
        for ($a = $min; $a <= $max; $a++) {
            $bands[] = ['key' => (string) $a, 'label' => $a . ' ans'];
        }
        $bands[] = ['key' => 'gt', 'label' => 'Plus de ' . $max . ' ans'];
        $bandKeys = array_column($bands, 'key');

        // Un seau par niveau de l'établissement (niveaux sans élève inclus).
        $buckets = [];
        $meta = [];
        foreach ($this->levelRepository->findBySchool($schoolId) as $level) {
            $id = $level->getId();
            $buckets[$id] = $this->emptyAgeBucket($bandKeys);
            $meta[$id] = ['label' => $level->getName() ?? 'Sans niveau', 'order' => $level->getOrderNumber() ?? 9999];
        }

        // Âge calculé à la fin de l'année civile de référence.
        $refYear = (int) ($year?->getEndDate()?->format('Y') ?? date('Y'));

        foreach ($this->registrationRepository->findBySchoolAndYear($schoolId, $yearId) as $registration) {
            $student = $registration->getStudent();
            $birthYear = (int) ($student?->getDateOfBirth()?->format('Y') ?? 0);
            if ($birthYear === 0) {
                continue; // Sans date de naissance : non ventilable par âge.
            }
            $age = $refYear - $birthYear;
            $band = $age < $min ? 'lt' : ($age > $max ? 'gt' : (string) $age);
            $girl = $student?->getGender() === 'F';

            $level = $registration->getLevel() ?? $registration->getPreRegistration()?->getRequestedLevel();
            $lid = $level?->getId() ?? 0;
            if (!isset($buckets[$lid])) {
                $buckets[$lid] = $this->emptyAgeBucket($bandKeys);
                $meta[$lid] = ['label' => $level?->getName() ?? 'Sans niveau', 'order' => $level?->getOrderNumber() ?? 9999];
            }

            $buckets[$lid]['bands'][$band]['total']++;
            $buckets[$lid]['total']++;
            if ($girl) {
                $buckets[$lid]['bands'][$band]['girls']++;
                $buckets[$lid]['girls']++;
            }
            if ($registration->isRepeating()) {
                $buckets[$lid]['redTotal']++;
                if ($girl) { $buckets[$lid]['redGirls']++; }
            }
        }

        // Tri des niveaux (colonnes) et colonne « Ensemble » (somme sur les niveaux).
        uksort($buckets, static fn ($a, $b) => $meta[$a]['order'] <=> $meta[$b]['order']);
        $levels = [];
        $ensemble = $this->emptyAgeBucket($bandKeys);
        foreach ($buckets as $lid => $bucket) {
            $bucket['label'] = $meta[$lid]['label'];
            $levels[] = $bucket;
            foreach ($bandKeys as $k) {
                $ensemble['bands'][$k]['total'] += $bucket['bands'][$k]['total'];
                $ensemble['bands'][$k]['girls'] += $bucket['bands'][$k]['girls'];
            }
            $ensemble['total'] += $bucket['total'];
            $ensemble['girls'] += $bucket['girls'];
            $ensemble['redTotal'] += $bucket['redTotal'];
            $ensemble['redGirls'] += $bucket['redGirls'];
        }

        return $this->renderPdf('academic_report/repartition_age_pdf.html.twig', [
            'school' => $school,
            'school_year' => $year,
            'bands' => $bands,
            'levels' => $levels,
            'ensemble' => $ensemble,
            'logo_data' => $this->logoData($school),
            'generated_at' => new \DateTime(),
        ], 'REPARTITION_NIVEAU_AGE.pdf', 'landscape');
    }

    /**
     * Seau vide pour un niveau : compteurs Total/Fille par bande d'âge, plus les
     * totaux du niveau et les redoublants.
     *
     * @param list<string> $bandKeys
     *
     * @return array<string, mixed>
     */
    private function emptyAgeBucket(array $bandKeys): array
    {
        $bandsInit = [];
        foreach ($bandKeys as $k) {
            $bandsInit[$k] = ['total' => 0, 'girls' => 0];
        }

        return ['label' => '', 'bands' => $bandsInit, 'total' => 0, 'girls' => 0, 'redTotal' => 0, 'redGirls' => 0];
    }

    /**
     * Paramètres communs des rapports « majors » : établissement/année courants,
     * nombre de majors (borné) et période demandée. Le 5ᵉ élément est une réponse de
     * redirection à retourner immédiatement si un paramètre est invalide, sinon null.
     *
     * @return array{0: ?School, 1: ?\App\Entity\SchoolYear, 2: int, 3: ?\App\Entity\Period, 4: ?Response}
     */
    private function resolveTopPeriod(Request $request): array
    {
        $school = $this->schoolContextService->getCurrentSchool();
        $year = $this->schoolContextService->getCurrentSchoolYear();
        if (!$school) {
            $this->addFlash('warning', 'Veuillez sélectionner un établissement pour générer ce rapport.');
            return [null, null, 0, null, $this->redirectToRoute('admin_academic_report_index')];
        }

        $top = (int) $request->query->get('top', '3');
        if ($top < 1) { $top = 1; }
        if ($top > 50) { $top = 50; }

        $periodId = (int) $request->query->get('periode', '0');
        $period = $periodId > 0 ? $this->periodRepository->find($periodId) : null;
        if (!$period || $period->getSchoolYear()?->getId() !== $year?->getId()) {
            $this->addFlash('warning', 'Veuillez sélectionner une période valide pour générer ce rapport.');
            return [$school, $year, $top, null, $this->redirectToRoute('admin_academic_report_index')];
        }

        return [$school, $year, $top, $period, null];
    }

    #[Route('/majors-classe/pdf', name: 'majors_classe', methods: ['GET'])]
    public function majorsClasse(Request $request): Response
    {
        [$school, $year, $top, $period, $error] = $this->resolveTopPeriod($request);
        if ($error !== null) {
            return $error;
        }
        $schoolId = $school->getId();
        $yearId = $year?->getId();
        $lvMap = $this->lvSubjectMap($schoolId);

        // Regroupe les inscriptions actives par classe (le major se calcule par classe).
        $byClassroom = [];
        foreach ($this->registrationRepository->findBySchoolAndYear($schoolId, $yearId) as $registration) {
            $classroom = $registration->getClassroom();
            if ($classroom === null) {
                continue; // Élève non affecté à une classe : hors « majors de classe ».
            }
            $byClassroom[$classroom->getId()]['classroom'] = $classroom;
            $byClassroom[$classroom->getId()]['registrations'][] = $registration;
        }

        $classes = [];
        foreach ($byClassroom as $group) {
            $classroom = $group['classroom'];

            // Moyenne de période de chaque élève noté, triée décroissante.
            $ranked = [];
            foreach ($group['registrations'] as $registration) {
                $student = $registration->getStudent();
                if ($student === null) {
                    continue;
                }
                $average = $this->gradeRepository->calculateGeneralAverageByStudentAndPeriod($student->getId(), $period->getId(), true);
                if ($average === null) {
                    continue; // Sans moyenne de période : ne peut pas être major.
                }
                $ranked[] = ['registration' => $registration, 'student' => $student, 'average' => $average];
            }
            usort($ranked, static fn ($a, $b) => $b['average'] <=> $a['average']);

            // Les N premiers, avec rang de classe (1-based sur la moyenne de période).
            $rows = [];
            foreach (array_slice($ranked, 0, $top) as $i => $entry) {
                $lv2 = $this->resolveStudentLv2($entry['student']->getId(), $period->getId(), $yearId, $lvMap);
                $rows[] = $this->buildMajorRow($i + 1, $entry['registration'], $entry['student'], $entry['average'], $lv2);
            }

            $level = $classroom->getLevel();
            $classes[] = [
                'label' => $classroom->getName(),
                'levelOrder' => $level?->getOrderNumber() ?? 9999,
                'rows' => $rows,
            ];
        }

        usort($classes, static fn ($a, $b) => [$a['levelOrder'], $a['label']] <=> [$b['levelOrder'], $b['label']]);

        return $this->renderPdf('academic_report/majors_classe_pdf.html.twig', [
            'school' => $school,
            'school_year' => $year,
            'period' => $period,
            'top' => $top,
            'classes' => $classes,
            'logo_data' => $this->logoData($school),
            'generated_at' => new \DateTime(),
        ], 'MAJORS_PAR_CLASSE.pdf', 'landscape');
    }

    /** Rang ordinal à la française : 1 → « 1er », n → « nè ». */
    private function formatRank(int $rank): string
    {
        return $rank === 1 ? '1er' : $rank . 'è';
    }

    /**
     * Construit une ligne « major » (colonnes du modèle officiel) à partir d'une
     * inscription, de son élève et de sa moyenne de période.
     *
     * @return array<string, mixed>
     */
    private function buildMajorRow(int $seq, \App\Entity\Registration $registration, \App\Entity\Student $student, float $average, string $lv2 = ''): array
    {
        return [
            'seq' => $seq,
            'matricule' => $student->getMatriculeNational() ?: ($student->getMatriculeInterne() ?: 'AUCUN'),
            'name' => trim($student->getLastName() . ' ' . $student->getFirstName()),
            'birthDate' => $student->getDateOfBirth(),
            'genre' => $student->getGender() === 'M' ? 'G' : ($student->getGender() === 'F' ? 'F' : ''),
            'nat' => $this->isNationalNationality($student->getNationality()) ? 'Iv' : 'E',
            'red' => $registration->isRepeating() ? 'Red' : 'NRed',
            'lv2' => $lv2,
            'average' => $average,
            'rank' => $this->formatRank($seq),
            'classe' => $registration->getClassroom()?->getName() ?? '',
        ];
    }

    #[Route('/majors-niveau/pdf', name: 'majors_niveau', methods: ['GET'])]
    public function majorsNiveau(Request $request): Response
    {
        [$school, $year, $top, $period, $error] = $this->resolveTopPeriod($request);
        if ($error !== null) {
            return $error;
        }
        $schoolId = $school->getId();
        $yearId = $year?->getId();
        $lvMap = $this->lvSubjectMap($schoolId);

        // Regroupe les inscriptions actives par niveau (le major se calcule par niveau).
        $byLevel = [];
        foreach ($this->registrationRepository->findBySchoolAndYear($schoolId, $yearId) as $registration) {
            if ($registration->getClassroom() === null) {
                continue; // Élève non affecté : hors « majors ».
            }
            $level = $registration->getLevel();
            $lid = $level?->getId() ?? 0;
            if (!isset($byLevel[$lid])) {
                $byLevel[$lid] = [
                    'label' => $level?->getName() ?? 'Sans niveau',
                    'order' => $level?->getOrderNumber() ?? 9999,
                    'registrations' => [],
                ];
            }
            $byLevel[$lid]['registrations'][] = $registration;
        }

        $levels = [];
        $totalRows = 0;
        foreach ($byLevel as $group) {
            // Moyenne de période de chaque élève noté du niveau, triée décroissante.
            $ranked = [];
            foreach ($group['registrations'] as $registration) {
                $student = $registration->getStudent();
                if ($student === null) {
                    continue;
                }
                $average = $this->gradeRepository->calculateGeneralAverageByStudentAndPeriod($student->getId(), $period->getId(), true);
                if ($average === null) {
                    continue;
                }
                $ranked[] = ['registration' => $registration, 'student' => $student, 'average' => $average];
            }
            usort($ranked, static fn ($a, $b) => $b['average'] <=> $a['average']);

            $rows = [];
            foreach (array_slice($ranked, 0, $top) as $i => $entry) {
                $lv2 = $this->resolveStudentLv2($entry['student']->getId(), $period->getId(), $yearId, $lvMap);
                $rows[] = $this->buildMajorRow($i + 1, $entry['registration'], $entry['student'], $entry['average'], $lv2);
            }

            $levels[] = ['label' => $group['label'], 'order' => $group['order'], 'rows' => $rows];
            // Lignes réellement rendues : les majors (ou 1 ligne « aucun ») + 1 séparateur.
            $totalRows += max(\count($rows), 1) + 1;
        }
        usort($levels, static fn ($a, $b) => $a['order'] <=> $b['order']);

        return $this->renderPdf('academic_report/majors_niveau_pdf.html.twig', [
            'school' => $school,
            'school_year' => $year,
            'period' => $period,
            'top' => $top,
            'levels' => $levels,
            'total_rows' => $totalRows,
            'logo_data' => $this->logoData($school),
            'generated_at' => new \DateTime(),
        ], 'MAJORS_PAR_NIVEAU.pdf', 'landscape');
    }

    #[Route('/classes-merite/pdf', name: 'classes_merite', methods: ['GET'])]
    public function classesMerite(Request $request): Response
    {
        $school = $this->schoolContextService->getCurrentSchool();
        $year = $this->schoolContextService->getCurrentSchoolYear();
        if (!$school) {
            $this->addFlash('warning', 'Veuillez sélectionner un établissement pour générer ce rapport.');
            return $this->redirectToRoute('admin_academic_report_index');
        }
        $schoolId = $school->getId();
        $yearId = $year?->getId();

        // Période obligatoire.
        $pid = (int) $request->query->get('periode', '0');
        $period = $pid > 0 ? $this->periodRepository->find($pid) : null;
        if (!$period || $period->getSchoolYear()?->getId() !== $yearId) {
            $this->addFlash('warning', 'Veuillez sélectionner une période valide pour générer ce rapport.');
            return $this->redirectToRoute('admin_academic_report_index');
        }

        // Classe : 0 = toutes les classes.
        $classeId = (int) $request->query->get('classe', '0');
        $classrooms = $this->classroomRepository->findBySchoolAndYear($schoolId, $yearId);
        if ($classeId > 0) {
            $classrooms = array_values(array_filter($classrooms, static fn ($c) => $c->getId() === $classeId));
        }
        usort($classrooms, static fn ($a, $b) => [$a->getLevel()?->getOrderNumber() ?? 9999, $a->getName()] <=> [$b->getLevel()?->getOrderNumber() ?? 9999, $b->getName()]);

        $classes = [];
        foreach ($classrooms as $classroom) {
            $entries = [];
            foreach ($this->registrationRepository->findBySchoolAndYear($schoolId, $yearId, $classroom->getId()) as $registration) {
                $student = $registration->getStudent();
                if ($student === null) {
                    continue;
                }
                $entries[] = [
                    'registration' => $registration,
                    'student' => $student,
                    'average' => $this->gradeRepository->calculateGeneralAverageByStudentAndPeriod($student->getId(), $pid, true),
                ];
            }

            // Élèves notés (classés par mérite) puis non classés (par nom).
            $classed = array_values(array_filter($entries, static fn ($e) => $e['average'] !== null));
            usort($classed, static fn ($a, $b) => $b['average'] <=> $a['average']);
            $nonClassed = array_values(array_filter($entries, static fn ($e) => $e['average'] === null));
            usort($nonClassed, static fn ($a, $b) => strcmp($a['student']->getLastName(), $b['student']->getLastName()));

            $rows = [];
            $seq = 0;
            $prev = null;
            $rankNum = 0;
            foreach ($classed as $i => $e) {
                if ($prev === null || $e['average'] < $prev) {
                    $rankNum = $i + 1; // Rang « competition » (ex-aequo partagent le rang).
                    $ex = false;
                } else {
                    $ex = true; // Même moyenne que le précédent → ex-aequo.
                }
                $prev = $e['average'];
                $rows[] = $this->buildMeriteRow(++$seq, $e['registration'], $e['student'], $e['average'], $this->formatRank($rankNum) . ($ex ? ' EX' : ''));
            }
            foreach ($nonClassed as $e) {
                $rows[] = $this->buildMeriteRow(++$seq, $e['registration'], $e['student'], null, 'NC');
            }

            $averages = array_column($classed, 'average');
            $classes[] = [
                'label' => $classroom->getName(),
                'rows' => $rows,
                'high' => $averages ? max($averages) : 0.0,
                'low' => $averages ? min($averages) : 0.0,
                'class_avg' => $averages ? round(array_sum($averages) / \count($averages), 2) : 0.0,
            ];
        }

        return $this->renderPdf('academic_report/classes_merite_pdf.html.twig', [
            'school' => $school,
            'school_year' => $year,
            'period' => $period,
            'classes' => $classes,
            'logo_data' => $this->logoData($school),
            'generated_at' => new \DateTime(),
        ], 'CLASSES_ORDRE_MERITE.pdf');
    }

    /**
     * Ligne « ordre de mérite » d'un élève.
     *
     * @return array<string, mixed>
     */
    private function buildMeriteRow(int $seq, \App\Entity\Registration $registration, \App\Entity\Student $student, ?float $average, string $rank, string $lv2 = ''): array
    {
        return [
            'seq' => $seq,
            'matric' => $student->getMatriculeNational() ?: ($student->getMatriculeInterne() ?: 'AUCUN'),
            'nom' => $student->getLastName(),
            'prenoms' => $student->getFirstName(),
            'genre' => $student->getGender() === 'M' ? 'G' : ($student->getGender() === 'F' ? 'F' : ''),
            'ne' => $student->getDateOfBirth(),
            'nat' => $this->nationalityCode($student->getNationality()),
            'qualite' => $student->getStatus() === 'affecte' ? 'Aff' : ($student->getStatus() === 'non_affecte' ? 'NAff' : ''),
            'red' => $registration->isRepeating() ? 'Red' : 'NRed',
            'lv2' => $lv2,
            'moy' => $average,
            'rank' => $rank,
        ];
    }

    /** Code nationalité court (4 lettres, sans accents) : « Ivoirienne » → « IVOI ». */
    private function nationalityCode(?string $nationality): string
    {
        $n = trim((string) $nationality);
        if ($n === '') {
            return '';
        }
        $n = strtr(mb_strtoupper($n), ['À' => 'A', 'Â' => 'A', 'É' => 'E', 'È' => 'E', 'Ê' => 'E', 'Ë' => 'E', 'Î' => 'I', 'Ï' => 'I', 'Ô' => 'O', 'Û' => 'U', 'Ù' => 'U', 'Ç' => 'C']);
        $n = preg_replace('/[^A-Z]/', '', $n) ?? '';

        return mb_substr($n, 0, 4);
    }

    /**
     * Carte des matières de langue vivante : [subjectId => abréviation LV2 (ALL/ESP)].
     *
     * @return array<int, string>
     */
    private function lvSubjectMap(int $schoolId): array
    {
        $map = [];
        foreach ($this->orderedSubjects($schoolId) as $subj) {
            $lv = $subj->getLv();
            if ($lv && $lv !== 'AUCUN') {
                $map[$subj->getId()] = $lv === 'ALLEMAND' ? 'ALL' : ($lv === 'ESPAGNOLE' ? 'ESP' : mb_strtoupper(mb_substr($lv, 0, 3)));
            }
        }

        return $map;
    }

    /**
     * LV2 d'un élève : matière de langue vivante suivie (notée sur la période si donnée,
     * sinon sur l'année). Chaîne vide si aucune.
     *
     * @param array<int, string> $lvMap
     */
    private function resolveStudentLv2(int $studentId, ?int $periodId, ?int $yearId, array $lvMap): string
    {
        if ($lvMap === []) {
            return '';
        }
        $subjectAverages = $periodId !== null
            ? $this->gradeRepository->periodSubjectAveragesByStudent($studentId, $periodId, true)
            : $this->gradeRepository->annualSubjectAveragesByStudent($studentId, $yearId, true);
        if ($subjectAverages === [] && $periodId !== null) {
            $subjectAverages = $this->gradeRepository->annualSubjectAveragesByStudent($studentId, $yearId, true);
        }
        foreach (array_keys($subjectAverages) as $subjectId) {
            if (isset($lvMap[$subjectId])) {
                return $lvMap[$subjectId];
            }
        }

        return '';
    }

    #[Route('/moyenne-superieure/pdf', name: 'moyenne_sup', methods: ['GET'])]
    public function moyenneSup(Request $request): Response
    {
        $school = $this->schoolContextService->getCurrentSchool();
        $year = $this->schoolContextService->getCurrentSchoolYear();
        if (!$school) {
            $this->addFlash('warning', 'Veuillez sélectionner un établissement pour générer ce rapport.');
            return $this->redirectToRoute('admin_academic_report_index');
        }

        // Période obligatoire.
        $pid = (int) $request->query->get('periode', '0');
        $period = $pid > 0 ? $this->periodRepository->find($pid) : null;
        if (!$period || $period->getSchoolYear()?->getId() !== $year?->getId()) {
            $this->addFlash('warning', 'Veuillez sélectionner une période valide pour générer ce rapport.');
            return $this->redirectToRoute('admin_academic_report_index');
        }

        // Seuil de moyenne.
        $min = (float) str_replace(',', '.', (string) $request->query->get('min', (string) self::PASS_MARK));
        if ($min < 0 || $min > 20) {
            $min = self::PASS_MARK;
        }

        [, , $classes] = $this->computeClassResults(null, $pid);

        // Matières « langue vivante » de l'établissement (pour déduire la LV2 de l'élève).
        $lvBySubject = [];
        foreach ($this->orderedSubjects($school->getId()) as $subj) {
            $lv = $subj->getLv();
            if ($lv && $lv !== 'AUCUN') {
                $lvBySubject[$subj->getId()] = $lv === 'ALLEMAND' ? 'ALL' : ($lv === 'ESPAGNOLE' ? 'ESP' : mb_strtoupper(mb_substr($lv, 0, 3)));
            }
        }

        // Élèves de l'établissement au-dessus du seuil, classés globalement par moyenne.
        $entries = [];
        foreach ($classes as $class) {
            foreach ($class['students'] as $e) {
                if ($e['average'] !== null && $e['average'] >= $min) {
                    $entries[] = ['student' => $e['student'], 'average' => $e['average'], 'classe' => $class['label']];
                }
            }
        }
        usort($entries, static fn ($a, $b) => $b['average'] <=> $a['average']);

        // Rang « competition » global (ex-aequo partagent le rang, marqués « EX »).
        $rows = [];
        $seq = 0;
        $prev = null;
        $rankNum = 0;
        foreach ($entries as $i => $e) {
            if ($prev === null || $e['average'] < $prev) {
                $rankNum = $i + 1;
                $ex = false;
            } else {
                $ex = true;
            }
            $prev = $e['average'];

            $student = $e['student'];
            $registration = $student->getRegistrationForYear($year) ?? $student->getLatestRegistration();
            $nationality = trim((string) $student->getNationality());

            // LV2 : matière de langue vivante suivie (notée pour la période, sinon sur l'année).
            $lv2 = '';
            if ($lvBySubject !== []) {
                $map = $this->gradeRepository->periodSubjectAveragesByStudent($student->getId(), $pid, true);
                if ($map === []) {
                    $map = $this->gradeRepository->annualSubjectAveragesByStudent($student->getId(), $year?->getId(), true);
                }
                foreach (array_keys($map) as $subjectId) {
                    if (isset($lvBySubject[$subjectId])) {
                        $lv2 = $lvBySubject[$subjectId];
                        break;
                    }
                }
            }

            $rows[] = [
                'seq' => ++$seq,
                'matric' => $student->getMatriculeNational() ?: ($student->getMatriculeInterne() ?: 'AUCUN'),
                'nom' => trim($student->getLastName() . ' ' . $student->getFirstName()),
                'ne' => $student->getDateOfBirth(),
                'genre' => $student->getGender() === 'M' ? 'G' : ($student->getGender() === 'F' ? 'F' : ''),
                'nat' => $nationality === '' ? '' : ($this->isNationalNationality($nationality) ? 'Iv' : 'E'),
                'red' => $registration && $registration->isRepeating() ? 'Red' : 'NRed',
                'lv2' => $lv2,
                'moy' => $e['average'],
                'rank' => $this->formatRank($rankNum) . ($ex ? ' EX' : ''),
                'classe' => $e['classe'],
            ];
        }

        return $this->renderPdf('academic_report/moyenne_sup_pdf.html.twig', [
            'school' => $school,
            'school_year' => $year,
            'period' => $period,
            'min' => $min,
            'rows' => $rows,
            'logo_data' => $this->logoData($school),
            'generated_at' => new \DateTime(),
        ], 'MOYENNE_SUPERIEURE.pdf');
    }

    /**
     * Portée du résultat pour les statistiques : « trimestriel » (une période) ou
     * « annuel ». Le 4ᵉ élément est une redirection à retourner immédiatement si la
     * période choisie est invalide, sinon null.
     *
     * @return array{0: ?int, 1: ?\App\Entity\Period, 2: string, 3: ?Response}
     */
    private function resolveResultScope(Request $request): array
    {
        $mode = $request->query->get('resultat', 'annuel') === 'trimestriel' ? 'trimestriel' : 'annuel';
        if ($mode !== 'trimestriel') {
            return [null, null, 'annuel', null];
        }

        $year = $this->schoolContextService->getCurrentSchoolYear();
        $pid = (int) $request->query->get('periode', '0');
        $period = $pid > 0 ? $this->periodRepository->find($pid) : null;
        if (!$period || $period->getSchoolYear()?->getId() !== $year?->getId()) {
            $this->addFlash('warning', 'Veuillez sélectionner une période valide pour un résultat trimestriel.');
            return [null, null, 'trimestriel', $this->redirectToRoute('admin_academic_report_index')];
        }

        return [$period->getId(), $period, 'trimestriel', null];
    }

    /** Libellé de la portée du résultat affiché dans le rapport. */
    private function resultScopeLabel(string $mode, ?\App\Entity\Period $period): string
    {
        return $mode === 'trimestriel'
            ? 'Résultat trimestriel — ' . ($period?->getName() ?? '')
            : 'Résultat annuel';
    }

    #[Route('/stats-classe/pdf', name: 'stats_classe', methods: ['GET'])]
    public function statsClasse(Request $request): Response
    {
        [$periodId, $period, $mode, $error] = $this->resolveResultScope($request);
        if ($error !== null) {
            return $error;
        }

        [$school, $year, $classes, , $overall] = $this->computeResultStats(null, $periodId);
        if (!$school) {
            return $this->redirectToRoute('admin_academic_report_index');
        }
        usort($classes, static fn ($a, $b) => [$a['levelOrder'], $a['label']] <=> [$b['levelOrder'], $b['label']]);

        return $this->renderPdf('academic_report/stats_classe_pdf.html.twig', [
            'school' => $school, 'school_year' => $year, 'classes' => $classes, 'overall' => $overall,
            'result_scope' => $this->resultScopeLabel($mode, $period),
            'logo_data' => $this->logoData($school), 'generated_at' => new \DateTime(),
        ], 'STATS_RESULTATS_CLASSE.pdf');
    }

    #[Route('/stats-niveau/pdf', name: 'stats_niveau', methods: ['GET'])]
    public function statsNiveau(Request $request): Response
    {
        [$periodId, $period, $mode, $error] = $this->resolveResultScope($request);
        if ($error !== null) {
            return $error;
        }

        [$school, $year, , $levels, $overall] = $this->computeResultStats(null, $periodId);
        if (!$school) {
            return $this->redirectToRoute('admin_academic_report_index');
        }

        return $this->renderPdf('academic_report/stats_niveau_pdf.html.twig', [
            'school' => $school, 'school_year' => $year, 'levels' => $levels, 'overall' => $overall,
            'result_scope' => $this->resultScopeLabel($mode, $period),
            'logo_data' => $this->logoData($school), 'generated_at' => new \DateTime(),
        ], 'STATS_RESULTATS_NIVEAU.pdf');
    }

    #[Route('/synthese/pdf', name: 'synthese', methods: ['GET'])]
    public function synthese(Request $request): Response
    {
        [$periodId, $period, $mode, $error] = $this->resolveResultScope($request);
        if ($error !== null) {
            return $error;
        }

        [$school, $year, , $levels, $overall] = $this->computeResultStats(null, $periodId);
        if (!$school) {
            return $this->redirectToRoute('admin_academic_report_index');
        }

        // Portée dans le titre : « ANNUELS » ou « DU {PÉRIODE} ».
        $scopeTitle = $mode === 'trimestriel'
            ? 'DU ' . mb_strtoupper($period?->getName() ?? '')
            : 'ANNUELS';

        return $this->renderPdf('academic_report/synthese_pdf.html.twig', [
            'school' => $school, 'school_year' => $year, 'levels' => $levels, 'overall' => $overall,
            'scope_title' => $scopeTitle,
            'logo_data' => $this->logoData($school), 'generated_at' => new \DateTime(),
        ], 'SYNTHESE_RESULTATS.pdf', 'landscape');
    }

    #[Route('/proportion-sous-moyenne/pdf', name: 'proportion', methods: ['GET'])]
    public function proportion(Request $request): Response
    {
        $school = $this->schoolContextService->getCurrentSchool();
        $year = $this->schoolContextService->getCurrentSchoolYear();
        if (!$school) {
            $this->addFlash('warning', 'Veuillez sélectionner un établissement pour générer ce rapport.');
            return $this->redirectToRoute('admin_academic_report_index');
        }
        $schoolId = $school->getId();
        $yearId = $year?->getId();

        // Période obligatoire.
        $pid = (int) $request->query->get('periode', '0');
        $period = $pid > 0 ? $this->periodRepository->find($pid) : null;
        if (!$period || $period->getSchoolYear()?->getId() !== $yearId) {
            $this->addFlash('warning', 'Veuillez sélectionner une période valide pour générer ce rapport.');
            return $this->redirectToRoute('admin_academic_report_index');
        }

        $subjects = $this->orderedSubjects($schoolId);
        $subjectById = [];
        foreach ($subjects as $s) {
            $subjectById[$s->getId()] = $s;
        }

        // Accumulateur : [levelId][genre 'g'|'f'][subjectId] => ['classe'=>n, 'below'=>n].
        $acc = [];
        $levelMeta = []; // levelId => ['label'=>, 'order'=>]
        $usedSubjects = [];

        foreach ($this->classroomRepository->findBySchoolAndYear($schoolId, $yearId) as $classroom) {
            $level = $classroom->getLevel();
            if ($level === null) {
                continue;
            }
            $lid = $level->getId();
            $levelMeta[$lid] = ['label' => $level->getName(), 'order' => $level->getOrderNumber() ?? 9999];

            foreach ($this->studentRepository->findActiveByClassroom($classroom->getId()) as $student) {
                $genre = $student->getGender() === 'M' ? 'g' : ($student->getGender() === 'F' ? 'f' : null);
                if ($genre === null) {
                    continue;
                }
                $map = $this->gradeRepository->periodSubjectAveragesByStudent($student->getId(), $pid, true);
                foreach ($map as $subjectId => $moy) {
                    if (!isset($subjectById[$subjectId])) {
                        continue;
                    }
                    $usedSubjects[$subjectId] = true;
                    if (!isset($acc[$lid][$genre][$subjectId])) {
                        $acc[$lid][$genre][$subjectId] = ['classe' => 0, 'below' => 0];
                    }
                    $acc[$lid][$genre][$subjectId]['classe']++;
                    if ($moy < self::PASS_MARK) {
                        $acc[$lid][$genre][$subjectId]['below']++;
                    }
                }
            }
        }

        // Colonnes = matières réellement notées, dans l'ordre du bulletin/nom.
        $columns = [];
        foreach ($subjects as $s) {
            if (isset($usedSubjects[$s->getId()])) {
                $columns[] = ['id' => $s->getId(), 'name' => $s->getName()];
            }
        }

        // Lignes par niveau (Masculin / Féminin / Ensemble).
        $cell = static function (array $a): array {
            $pct = $a['classe'] > 0 ? round($a['below'] / $a['classe'] * 100, 2) : 0.0;
            return ['classe' => $a['classe'], 'below' => $a['below'], 'pct' => $pct];
        };
        $rows = [];
        $levelIds = array_keys($levelMeta);
        usort($levelIds, static fn ($a, $b) => $levelMeta[$a]['order'] <=> $levelMeta[$b]['order']);
        foreach ($levelIds as $lid) {
            $genders = [];
            foreach (['g' => 'Masculin', 'f' => 'Féminin', 'e' => 'Ensemble'] as $key => $label) {
                $cells = [];
                foreach ($columns as $col) {
                    $sid = $col['id'];
                    if ($key === 'e') {
                        $g = $acc[$lid]['g'][$sid] ?? ['classe' => 0, 'below' => 0];
                        $f = $acc[$lid]['f'][$sid] ?? ['classe' => 0, 'below' => 0];
                        $cells[] = $cell(['classe' => $g['classe'] + $f['classe'], 'below' => $g['below'] + $f['below']]);
                    } else {
                        $cells[] = $cell($acc[$lid][$key][$sid] ?? ['classe' => 0, 'below' => 0]);
                    }
                }
                $genders[] = ['label' => $label, 'cells' => $cells];
            }
            $rows[] = ['level' => $levelMeta[$lid]['label'], 'genders' => $genders];
        }

        return $this->renderPdf('academic_report/proportion_pdf.html.twig', [
            'school' => $school,
            'school_year' => $year,
            'period' => $period,
            'columns' => $columns,
            'rows' => $rows,
            'logo_data' => $this->logoData($school),
            'generated_at' => new \DateTime(),
        ], 'PROPORTION_SOUS_MOYENNE.pdf', 'landscape');
    }

    #[Route('/genre-decision/pdf', name: 'genre_decision', methods: ['GET'])]
    public function genreDecision(Request $request): Response
    {
        $school = $this->schoolContextService->getCurrentSchool();
        $year = $this->schoolContextService->getCurrentSchoolYear();
        if (!$school) {
            $this->addFlash('warning', 'Veuillez sélectionner un établissement pour générer ce rapport.');
            return $this->redirectToRoute('admin_academic_report_index');
        }
        $schoolId = $school->getId();
        $yearId = $year?->getId();

        // Ce rapport est trimestriel : une période est obligatoire.
        $pid = (int) $request->query->get('periode', '0');
        $period = $pid > 0 ? $this->periodRepository->find($pid) : null;
        if (!$period || $period->getSchoolYear()?->getId() !== $yearId) {
            $this->addFlash('warning', 'Veuillez sélectionner une période valide pour générer ce rapport.');
            return $this->redirectToRoute('admin_academic_report_index');
        }

        // Un seau « situation » par niveau ayant au moins un élève noté sur la période.
        $buckets = [];
        $meta = [];
        foreach ($this->registrationRepository->findBySchoolAndYear($schoolId, $yearId) as $registration) {
            $student = $registration->getStudent();
            $gender = $student?->getGender();
            $col = $gender === 'M' ? 'g' : ($gender === 'F' ? 'f' : null);
            if ($col === null) {
                continue; // Genre inconnu : hors ventilation G/F.
            }

            $average = $this->gradeRepository->calculateGeneralAverageByStudentAndPeriod($student->getId(), $pid, true);
            if ($average === null) {
                continue; // Non noté sur la période : non classé.
            }

            $level = $registration->getLevel() ?? $registration->getPreRegistration()?->getRequestedLevel();
            $lid = $level?->getId() ?? 0;
            if (!isset($buckets[$lid])) {
                $buckets[$lid] = $this->emptySituation();
                $meta[$lid] = [
                    'label' => $level?->getName() ?? 'Sans niveau',
                    'order' => $level?->getOrderNumber() ?? 9999,
                    'cycle' => $level?->getCycle()?->getLibelle() ?: 'Autres',
                ];
            }

            $repeating = $registration->isRepeating();
            // Ventilation par situation (moyenne générale MG de la période).
            if ($average >= self::PASS_MARK) {
                $group = 'adm';                             // Non Red et Red, MG ≥ 10
            } elseif ($average >= 8.5) {
                $group = $repeating ? 'exc1' : 'red';       // 10 > MG ≥ 8,5
            } else {
                $group = $repeating ? 'exc3' : 'exc2';      // MG < 8,5
            }
            $buckets[$lid][$group][$col]++;
        }

        // Regroupement par cycle (section) avec sous-totaux, dans l'ordre des niveaux.
        uksort($buckets, static fn ($a, $b) => $meta[$a]['order'] <=> $meta[$b]['order']);
        $sections = [];
        foreach ($buckets as $lid => $bucket) {
            $cycle = $meta[$lid]['cycle'];
            if (!isset($sections[$cycle])) {
                $sections[$cycle] = [
                    'label' => mb_strtoupper($cycle),
                    'order' => $meta[$lid]['order'],
                    'rows' => [],
                    'subtotal' => $this->emptySituation(),
                ];
            }
            $sections[$cycle]['rows'][] = $this->flattenSituation($meta[$lid]['label'], $bucket);
            $this->mergeSituation($sections[$cycle]['subtotal'], $bucket);
        }

        usort($sections, static fn ($a, $b) => $a['order'] <=> $b['order']);
        $grand = $this->emptySituation();
        foreach ($sections as &$section) {
            $this->mergeSituation($grand, $section['subtotal']);
            $section['subtotal'] = $this->flattenSituation('Total ' . $section['label'], $section['subtotal']);
        }
        unset($section);

        return $this->renderPdf('academic_report/genre_decision_pdf.html.twig', [
            'school' => $school, 'school_year' => $year, 'period' => $period,
            'sections' => $sections,
            'grand' => $this->flattenSituation('Total', $grand),
            'logo_data' => $this->logoData($school), 'generated_at' => new \DateTime(),
        ], 'ADMISSION_REDOUBLEMENT_GENRE.pdf', 'landscape');
    }

    /** @return array<string, mixed> Ventilation « situation » vide (5 groupes g/f). */
    private function emptySituation(): array
    {
        return [
            'adm' => ['g' => 0, 'f' => 0],  // Admission : MG ≥ 10 (redoublant ou non)
            'red' => ['g' => 0, 'f' => 0],  // Redoublement : non-red, 8,5 ≤ MG < 10
            'exc1' => ['g' => 0, 'f' => 0], // Exclusion : red, 8,5 ≤ MG < 10
            'exc2' => ['g' => 0, 'f' => 0], // Exclusion : non-red, MG < 8,5
            'exc3' => ['g' => 0, 'f' => 0], // Exclusion : red, MG < 8,5
        ];
    }

    /**
     * @param array<string, mixed> $target
     * @param array<string, mixed> $src
     */
    private function mergeSituation(array &$target, array $src): void
    {
        foreach (['adm', 'red', 'exc1', 'exc2', 'exc3'] as $k) {
            $target[$k]['g'] += $src[$k]['g'];
            $target[$k]['f'] += $src[$k]['f'];
        }
    }

    /**
     * Aplatit une ventilation « situation » en cellules (totaux par groupe + total
     * général d'exclusion = somme des trois sous-totaux d'exclusion).
     *
     * @param array<string, mixed> $b
     *
     * @return array<string, mixed>
     */
    private function flattenSituation(string $label, array $b): array
    {
        $t = static fn (array $x) => $x['g'] + $x['f'];

        return [
            'label' => $label,
            'adm_g' => $b['adm']['g'], 'adm_f' => $b['adm']['f'], 'adm_t' => $t($b['adm']),
            'red_g' => $b['red']['g'], 'red_f' => $b['red']['f'], 'red_t' => $t($b['red']),
            'exc1_g' => $b['exc1']['g'], 'exc1_f' => $b['exc1']['f'], 'exc1_t' => $t($b['exc1']),
            'exc2_g' => $b['exc2']['g'], 'exc2_f' => $b['exc2']['f'], 'exc2_t' => $t($b['exc2']),
            'exc3_g' => $b['exc3']['g'], 'exc3_f' => $b['exc3']['f'], 'exc3_t' => $t($b['exc3']),
            'exc_total' => $t($b['exc1']) + $t($b['exc2']) + $t($b['exc3']),
        ];
    }

    #[Route('/affectes-classe/pdf', name: 'affectes_classe', methods: ['GET'])]
    public function affectesClasse(Request $request): Response
    {
        return $this->statusResultsByClass($request, 'affecte', 'RÉSULTATS SCOLAIRES DES ÉLÈVES AFFECTÉS PAR NIVEAU ET PAR CLASSE', 'RESULTATS_AFFECTES_CLASSE.pdf');
    }

    #[Route('/affectes-niveau/pdf', name: 'affectes_niveau', methods: ['GET'])]
    public function affectesNiveau(Request $request): Response
    {
        return $this->statusResultsByLevel($request, 'affecte', 'RÉSULTATS SCOLAIRES DES ÉLÈVES AFFECTÉS PAR NIVEAU', 'RESULTATS_AFFECTES_NIVEAU.pdf');
    }

    #[Route('/non-affectes-classe/pdf', name: 'non_affectes_classe', methods: ['GET'])]
    public function nonAffectesClasse(Request $request): Response
    {
        return $this->statusResultsByClass($request, 'non_affecte', 'RÉSULTATS SCOLAIRES DES ÉLÈVES NON-AFFECTÉS PAR NIVEAU ET PAR CLASSE', 'RESULTATS_NON_AFFECTES_CLASSE.pdf');
    }

    #[Route('/non-affectes-niveau/pdf', name: 'non_affectes_niveau', methods: ['GET'])]
    public function nonAffectesNiveau(Request $request): Response
    {
        return $this->statusResultsByLevel($request, 'non_affecte', 'RÉSULTATS SCOLAIRES DES ÉLÈVES NON-AFFECTÉS PAR NIVEAU', 'RESULTATS_NON_AFFECTES_NIVEAU.pdf');
    }

    private function statusResultsByClass(Request $request, string $status, string $title, string $filename): Response
    {
        [$periodId, $period, $mode, $error] = $this->resolveResultScope($request);
        if ($error !== null) {
            return $error;
        }

        [$school, $year, $levels] = $this->classResultsByLevel($status, $periodId);
        if (!$school) {
            return $this->redirectToRoute('admin_academic_report_index');
        }

        $scopeTitle = $mode === 'trimestriel'
            ? 'DU ' . mb_strtoupper($period?->getName() ?? '')
            : 'ANNUELS';

        return $this->renderPdf('academic_report/resultats_classe_pdf.html.twig', [
            'school' => $school, 'school_year' => $year, 'levels' => $levels,
            'report_title' => $title, 'scope_title' => $scopeTitle,
            'logo_data' => $this->logoData($school), 'generated_at' => new \DateTime(),
        ], $filename, 'landscape');
    }

    /**
     * Résultats par classe regroupés par niveau, avec ventilation par genre et par
     * tranche de moyenne (≥ 10 ; [8,5 ; 10[ ; < 8,5). Filtré sur le statut d'affectation
     * de l'élève et calculé en trimestriel (période) ou annuel.
     *
     * @return array{0: ?School, 1: ?\App\Entity\SchoolYear, 2: list<array<string, mixed>>}
     */
    private function classResultsByLevel(string $statusFilter, ?int $periodId): array
    {
        $school = $this->schoolContextService->getCurrentSchool();
        $year = $this->schoolContextService->getCurrentSchoolYear();
        if (!$school) {
            $this->addFlash('warning', 'Veuillez sélectionner un établissement pour générer ce rapport.');
            return [null, null, []];
        }
        $schoolId = $school->getId();
        $yearId = $year?->getId();

        $byLevel = [];
        foreach ($this->classroomRepository->findBySchoolAndYear($schoolId, $yearId) as $classroom) {
            $students = array_values(array_filter(
                $this->studentRepository->findActiveByClassroom($classroom->getId()),
                static fn ($s) => $s->getStatus() === $statusFilter
            ));

            $bucket = $this->emptyClassSituation();
            foreach ($students as $student) {
                $col = $student->getGender() === 'M' ? 'g' : ($student->getGender() === 'F' ? 'f' : null);
                if ($col === null) {
                    continue; // Genre inconnu : hors ventilation G/F.
                }
                $bucket['eff']++;

                $avg = $periodId !== null
                    ? $this->gradeRepository->calculateGeneralAverageByStudentAndPeriod($student->getId(), $periodId, true)
                    : $this->gradeRepository->calculateAnnualGeneralAverageByStudent($student->getId(), $yearId, true);
                if ($avg === null) {
                    continue; // Non classé.
                }

                $bucket['classe'][$col]++;
                $bucket['sum'] += $avg;
                $bucket['graded']++;
                if ($avg >= self::PASS_MARK) {
                    $bucket['b10'][$col]++;
                } elseif ($avg >= 8.5) {
                    $bucket['b85'][$col]++;
                } else {
                    $bucket['blow'][$col]++;
                }
            }

            if ($bucket['eff'] === 0) {
                continue; // Classe sans élève du statut demandé : ignorée.
            }

            $level = $classroom->getLevel();
            $key = ($level?->getOrderNumber() ?? 9999) . '|' . ($level?->getName() ?? 'Sans niveau');
            if (!isset($byLevel[$key])) {
                $byLevel[$key] = [
                    'label' => $level?->getName() ?? 'Sans niveau',
                    'order' => $level?->getOrderNumber() ?? 9999,
                    'classes' => [],
                    'total' => $this->emptyClassSituation(),
                ];
            }
            $bucket['label'] = $classroom->getName();
            $byLevel[$key]['classes'][] = $bucket;
            $this->mergeClassSituation($byLevel[$key]['total'], $bucket);
        }

        $levels = [];
        foreach ($byLevel as $grp) {
            $rows = [];
            foreach ($grp['classes'] as $c) {
                $rows[] = $this->flattenClassSituation($c['label'], $c);
            }
            usort($rows, static fn ($a, $b) => strcmp((string) $a['label'], (string) $b['label']));
            $levels[] = [
                'label' => $grp['label'],
                'order' => $grp['order'],
                'classes_count' => \count($grp['classes']),
                'rows' => $rows,
                'total' => $this->flattenClassSituation('TOTAL', $grp['total']),
            ];
        }
        usort($levels, static fn ($a, $b) => $a['order'] <=> $b['order']);

        return [$school, $year, $levels];
    }

    /** @return array<string, mixed> Seau vide « résultat de classe » (effectif + tranches g/f). */
    private function emptyClassSituation(): array
    {
        return [
            'label' => '', 'eff' => 0, 'sum' => 0.0, 'graded' => 0,
            'classe' => ['g' => 0, 'f' => 0],
            'b10' => ['g' => 0, 'f' => 0], 'b85' => ['g' => 0, 'f' => 0], 'blow' => ['g' => 0, 'f' => 0],
        ];
    }

    /**
     * @param array<string, mixed> $target
     * @param array<string, mixed> $src
     */
    private function mergeClassSituation(array &$target, array $src): void
    {
        $target['eff'] += $src['eff'];
        $target['sum'] += $src['sum'];
        $target['graded'] += $src['graded'];
        foreach (['classe', 'b10', 'b85', 'blow'] as $k) {
            $target[$k]['g'] += $src[$k]['g'];
            $target[$k]['f'] += $src[$k]['f'];
        }
    }

    /**
     * Aplatit un seau « résultat de classe » en cellules (totaux G+F, pourcentages sur
     * l'effectif classé, moyenne de l'établissement/classe).
     *
     * @param array<string, mixed> $b
     *
     * @return array<string, mixed>
     */
    private function flattenClassSituation(string $label, array $b): array
    {
        $ct = $b['classe']['g'] + $b['classe']['f'];
        $pct = static fn (int $t) => $ct > 0 ? round($t / $ct * 100, 2) : 0.0;
        $b10t = $b['b10']['g'] + $b['b10']['f'];
        $b85t = $b['b85']['g'] + $b['b85']['f'];
        $blowt = $b['blow']['g'] + $b['blow']['f'];

        return [
            'label' => $label,
            'eff' => $b['eff'],
            'cg' => $b['classe']['g'], 'cf' => $b['classe']['f'], 'ct' => $ct,
            'non_classe' => $b['eff'] - $ct,
            'b10g' => $b['b10']['g'], 'b10f' => $b['b10']['f'], 'b10t' => $b10t, 'b10p' => $pct($b10t),
            'b85g' => $b['b85']['g'], 'b85f' => $b['b85']['f'], 'b85t' => $b85t, 'b85p' => $pct($b85t),
            'blowg' => $b['blow']['g'], 'blowf' => $b['blow']['f'], 'blowt' => $blowt, 'blowp' => $pct($blowt),
            'moy_ets' => $b['graded'] > 0 ? round($b['sum'] / $b['graded'], 2) : null,
        ];
    }

    private function statusResultsByLevel(Request $request, string $status, string $title, string $filename): Response
    {
        [$periodId, $period, $mode, $error] = $this->resolveResultScope($request);
        if ($error !== null) {
            return $error;
        }

        [$school, $year, $sections, $grand] = $this->levelResultsGenre($status, $periodId);
        if (!$school) {
            return $this->redirectToRoute('admin_academic_report_index');
        }

        $scopeTitle = $mode === 'trimestriel'
            ? 'DU ' . mb_strtoupper($period?->getName() ?? '')
            : 'ANNUELS';

        return $this->renderPdf('academic_report/resultats_niveau_pdf.html.twig', [
            'school' => $school, 'school_year' => $year, 'sections' => $sections, 'grand' => $grand,
            'report_title' => $title, 'scope_title' => $scopeTitle,
            'logo_data' => $this->logoData($school), 'generated_at' => new \DateTime(),
        ], $filename, 'landscape');
    }

    /**
     * Résultats par niveau ventilés G/F/T, regroupés par cycle (sous-total) avec un
     * total général. Filtré sur le statut d'affectation ; trimestriel ou annuel.
     *
     * @return array{0: ?School, 1: ?\App\Entity\SchoolYear, 2: list<array<string, mixed>>, 3: array<string, mixed>}
     */
    private function levelResultsGenre(string $statusFilter, ?int $periodId): array
    {
        $school = $this->schoolContextService->getCurrentSchool();
        $year = $this->schoolContextService->getCurrentSchoolYear();
        if (!$school) {
            $this->addFlash('warning', 'Veuillez sélectionner un établissement pour générer ce rapport.');
            return [null, null, [], $this->emptyGenreBucket()];
        }
        $schoolId = $school->getId();
        $yearId = $year?->getId();

        $byLevel = [];
        $meta = [];
        foreach ($this->classroomRepository->findBySchoolAndYear($schoolId, $yearId) as $classroom) {
            $students = array_values(array_filter(
                $this->studentRepository->findActiveByClassroom($classroom->getId()),
                static fn ($s) => $s->getStatus() === $statusFilter
            ));

            $level = $classroom->getLevel();
            $lid = $level?->getId() ?? 0;
            if (!isset($byLevel[$lid])) {
                $byLevel[$lid] = $this->emptyGenreBucket();
                $meta[$lid] = [
                    'label' => $level?->getName() ?? 'Sans niveau',
                    'order' => $level?->getOrderNumber() ?? 9999,
                    'cycle' => $level?->getCycle()?->getLibelle() ?: 'Autres',
                ];
            }

            $hasStudent = false;
            foreach ($students as $student) {
                $col = $student->getGender() === 'M' ? 'g' : ($student->getGender() === 'F' ? 'f' : null);
                if ($col === null) {
                    continue;
                }
                $hasStudent = true;
                $byLevel[$lid][$col]['eff']++;

                $avg = $periodId !== null
                    ? $this->gradeRepository->calculateGeneralAverageByStudentAndPeriod($student->getId(), $periodId, true)
                    : $this->gradeRepository->calculateAnnualGeneralAverageByStudent($student->getId(), $yearId, true);
                if ($avg === null) {
                    continue;
                }

                $byLevel[$lid][$col]['classe']++;
                $byLevel[$lid][$col]['sum'] += $avg;
                $byLevel[$lid][$col]['graded']++;
                if ($avg >= self::PASS_MARK) {
                    $byLevel[$lid][$col]['b10']++;
                } elseif ($avg >= 8.5) {
                    $byLevel[$lid][$col]['b85']++;
                } else {
                    $byLevel[$lid][$col]['blow']++;
                }
            }
            if ($hasStudent) {
                $byLevel[$lid]['classes']++;
            }
        }

        // On ne garde que les niveaux ayant au moins un élève ; regroupement par cycle.
        uksort($byLevel, static fn ($a, $b) => $meta[$a]['order'] <=> $meta[$b]['order']);
        $sections = [];
        $grand = $this->emptyGenreBucket();
        foreach ($byLevel as $lid => $bucket) {
            if ($bucket['g']['eff'] + $bucket['f']['eff'] === 0) {
                continue;
            }
            $cycle = $meta[$lid]['cycle'];
            if (!isset($sections[$cycle])) {
                $sections[$cycle] = [
                    'label' => mb_strtoupper($cycle),
                    'order' => $meta[$lid]['order'],
                    'niveaux' => [],
                    'subtotal' => $this->emptyGenreBucket(),
                ];
            }
            $sections[$cycle]['niveaux'][] = $this->flattenGenreBucket($meta[$lid]['label'], $bucket);
            $this->mergeGenreBucket($sections[$cycle]['subtotal'], $bucket);
            $this->mergeGenreBucket($grand, $bucket);
        }

        usort($sections, static fn ($a, $b) => $a['order'] <=> $b['order']);
        foreach ($sections as &$section) {
            $section['subtotal'] = $this->flattenGenreBucket($section['label'], $section['subtotal']);
        }
        unset($section);

        return [$school, $year, $sections, $this->flattenGenreBucket('TOTAL', $grand)];
    }

    /** @return array<string, mixed> Seau vide « résultat par genre » (g/f + nombre de classes). */
    private function emptyGenreBucket(): array
    {
        $gender = static fn () => ['eff' => 0, 'classe' => 0, 'b10' => 0, 'b85' => 0, 'blow' => 0, 'sum' => 0.0, 'graded' => 0];

        return ['classes' => 0, 'g' => $gender(), 'f' => $gender()];
    }

    /**
     * @param array<string, mixed> $target
     * @param array<string, mixed> $src
     */
    private function mergeGenreBucket(array &$target, array $src): void
    {
        $target['classes'] += $src['classes'];
        foreach (['g', 'f'] as $c) {
            foreach (['eff', 'classe', 'b10', 'b85', 'blow', 'sum', 'graded'] as $k) {
                $target[$c][$k] += $src[$c][$k];
            }
        }
    }

    /**
     * Aplatit un seau « genre » en trois lignes G/F/T prêtes pour le tableau
     * (pourcentages sur l'effectif classé).
     *
     * @param array<string, mixed> $b
     *
     * @return array<string, mixed>
     */
    private function flattenGenreBucket(string $label, array $b): array
    {
        $line = static function (array $x): array {
            $pct = static fn (int $n) => $x['classe'] > 0 ? round($n / $x['classe'] * 100, 2) : 0.0;

            return [
                'eff' => $x['eff'], 'classe' => $x['classe'], 'non' => $x['eff'] - $x['classe'],
                'b10' => $x['b10'], 'b10p' => $pct($x['b10']),
                'b85' => $x['b85'], 'b85p' => $pct($x['b85']),
                'blow' => $x['blow'], 'blowp' => $pct($x['blow']),
            ];
        };

        // Ligne Total = Garçons + Filles.
        $t = [];
        foreach (['eff', 'classe', 'b10', 'b85', 'blow'] as $k) {
            $t[$k] = $b['g'][$k] + $b['f'][$k];
        }

        return [
            'label' => $label,
            'classes' => $b['classes'],
            'lines' => ['g' => $line($b['g']), 'f' => $line($b['f']), 't' => $line($t)],
        ];
    }

    #[Route('/nominative/pdf', name: 'nominative', methods: ['GET'])]
    public function nominative(Request $request): Response
    {
        $school = $this->schoolContextService->getCurrentSchool();
        $year = $this->schoolContextService->getCurrentSchoolYear();
        if (!$school) {
            $this->addFlash('warning', 'Veuillez sélectionner un établissement pour générer ce rapport.');
            return $this->redirectToRoute('admin_academic_report_index');
        }
        $schoolId = $school->getId();
        $yearId = $year?->getId();

        // Période obligatoire.
        $pid = (int) $request->query->get('periode', '0');
        $period = $pid > 0 ? $this->periodRepository->find($pid) : null;
        if (!$period || $period->getSchoolYear()?->getId() !== $yearId) {
            $this->addFlash('warning', 'Veuillez sélectionner une période valide pour générer ce rapport.');
            return $this->redirectToRoute('admin_academic_report_index');
        }

        // Qualité : '' = tous, sinon 'affecte' / 'non_affecte'.
        $qualite = $request->query->get('qualite', '');
        $qualite = \in_array($qualite, ['affecte', 'non_affecte'], true) ? $qualite : '';

        $lvMap = $this->lvSubjectMap($schoolId);

        $classrooms = $this->classroomRepository->findBySchoolAndYear($schoolId, $yearId);
        usort($classrooms, static fn ($a, $b) => [$a->getLevel()?->getOrderNumber() ?? 9999, $a->getName()] <=> [$b->getLevel()?->getOrderNumber() ?? 9999, $b->getName()]);

        $classes = [];
        foreach ($classrooms as $classroom) {
            $entries = [];
            foreach ($this->registrationRepository->findBySchoolAndYear($schoolId, $yearId, $classroom->getId()) as $registration) {
                $student = $registration->getStudent();
                if ($student === null) {
                    continue;
                }
                if ($qualite !== '' && $student->getStatus() !== $qualite) {
                    continue;
                }
                $entries[] = [
                    'registration' => $registration,
                    'student' => $student,
                    'average' => $this->gradeRepository->calculateGeneralAverageByStudentAndPeriod($student->getId(), $pid, true),
                ];
            }
            if ($entries === []) {
                continue;
            }

            // Rang de mérite (les notés, ex-aequo partagent le rang) — puis affichage par nom.
            $classed = array_values(array_filter($entries, static fn ($e) => $e['average'] !== null));
            usort($classed, static fn ($a, $b) => $b['average'] <=> $a['average']);
            $rankByKey = [];
            $prev = null;
            $rankNum = 0;
            foreach ($classed as $i => $e) {
                if ($prev === null || $e['average'] < $prev) {
                    $rankNum = $i + 1;
                    $ex = false;
                } else {
                    $ex = true;
                }
                $prev = $e['average'];
                $rankByKey[spl_object_id($e['registration'])] = $this->formatRank($rankNum) . ($ex ? ' EX' : '');
            }

            usort($entries, static fn ($a, $b) => strcmp($a['student']->getLastName() . ' ' . $a['student']->getFirstName(), $b['student']->getLastName() . ' ' . $b['student']->getFirstName()));

            $rows = [];
            $seq = 0;
            foreach ($entries as $e) {
                $rank = $rankByKey[spl_object_id($e['registration'])] ?? 'NC';
                $lv2 = $this->resolveStudentLv2($e['student']->getId(), $pid, $yearId, $lvMap);
                $row = $this->buildMeriteRow(++$seq, $e['registration'], $e['student'], $e['average'], $rank, $lv2);
                $row['classe'] = $classroom->getName();
                // Nat en code court Iv/E (vide si non renseignée) pour ce rapport.
                $nationality = trim((string) $e['student']->getNationality());
                $row['nat'] = $nationality === '' ? '' : ($this->isNationalNationality($nationality) ? 'Iv' : 'E');
                $rows[] = $row;
            }

            $classes[] = ['label' => $classroom->getName(), 'rows' => $rows, 'total' => \count($rows)];
        }

        return $this->renderPdf('academic_report/nominative_pdf.html.twig', [
            'school' => $school, 'school_year' => $year, 'period' => $period, 'classes' => $classes,
            'logo_data' => $this->logoData($school), 'generated_at' => new \DateTime(),
        ], 'LISTE_NOMINATIVE_RESULTATS.pdf', 'landscape');
    }

    #[Route('/decisions/pdf', name: 'decisions', methods: ['GET'])]
    public function decisions(Request $request): Response
    {
        $niveauId = (int) $request->query->get('niveau', '0');
        [$school, $year, $classes] = $this->computeNominalResults($niveauId > 0 ? $niveauId : null);
        if (!$school) {
            return $this->redirectToRoute('admin_academic_report_index');
        }
        usort($classes, static fn ($a, $b) => [$a['levelOrder'], $a['label']] <=> [$b['levelOrder'], $b['label']]);

        // Matières « langue vivante » pour déduire la LV2 de chaque élève.
        $lvBySubject = [];
        foreach ($this->orderedSubjects($school->getId()) as $subj) {
            $lv = $subj->getLv();
            if ($lv && $lv !== 'AUCUN') {
                $lvBySubject[$subj->getId()] = $lv === 'ALLEMAND' ? 'ALL' : ($lv === 'ESPAGNOLE' ? 'ESP' : mb_strtoupper(mb_substr($lv, 0, 3)));
            }
        }
        $yearId = $year?->getId();

        // Enrichit chaque ligne : redoublant + LV2 (matière de langue suivie).
        foreach ($classes as &$class) {
            foreach ($class['entries'] as &$e) {
                $student = $e['student'];
                $registration = $student->getRegistrationForYear($year) ?? $student->getLatestRegistration();
                $e['red'] = $registration && $registration->isRepeating() ? 'Oui' : 'Non';

                $lv2 = '';
                if ($lvBySubject !== []) {
                    $map = $this->gradeRepository->annualSubjectAveragesByStudent($student->getId(), $yearId, true);
                    foreach (array_keys($map) as $subjectId) {
                        if (isset($lvBySubject[$subjectId])) {
                            $lv2 = $lvBySubject[$subjectId];
                            break;
                        }
                    }
                }
                $e['lv2'] = $lv2;
            }
            unset($e);
        }
        unset($class);

        return $this->renderPdf('academic_report/decisions_pdf.html.twig', [
            'school' => $school, 'school_year' => $year, 'classes' => $classes,
            'logo_data' => $this->logoData($school), 'generated_at' => new \DateTime(),
        ], 'DECISIONS_FIN_ANNEE.pdf', 'landscape');
    }

    #[Route('/moyennes-matiere/xlsx', name: 'moyennes_matiere', methods: ['GET'])]
    public function moyennesMatiere(Request $request): Response
    {
        $year = $this->schoolContextService->getCurrentSchoolYear();

        // Ce rapport est trimestriel : une période est obligatoire.
        $pid = (int) $request->query->get('periode', '0');
        $period = $pid > 0 ? $this->periodRepository->find($pid) : null;
        if (!$period || $period->getSchoolYear()?->getId() !== $year?->getId()) {
            $this->addFlash('warning', 'Veuillez sélectionner une période valide pour générer ce rapport.');
            return $this->redirectToRoute('admin_academic_report_index');
        }

        [$school, , $classes, $subjects] = $this->computeSubjectAverages($period->getId());
        if (!$school) {
            return $this->redirectToRoute('admin_academic_report_index');
        }
        usort($classes, static fn ($a, $b) => [$a['levelOrder'], $a['label']] <=> [$b['levelOrder'], $b['label']]);

        // Feuille Excel : une ligne par classe, une colonne par matière.
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Moyennes');

        $sheet->setCellValue('A1', $school->getName());
        $sheet->setCellValue('A2', 'Moyennes par matière — ' . $period->getName() . ' — ' . ($year?->getName() ?? ''));
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);
        $sheet->getStyle('A2')->getFont()->setBold(true);

        // En-têtes de colonnes (Niveau, Classe, puis les matières).
        $header = ['Niveau', 'Classe'];
        foreach ($subjects as $subject) {
            $header[] = $subject->getName();
        }
        $sheet->fromArray($header, null, 'A4');
        $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(\count($header));
        $sheet->getStyle('A4:' . $lastCol . '4')->getFont()->setBold(true);

        $rowNum = 5;
        foreach ($classes as $class) {
            $line = [$class['levelLabel'], $class['label']];
            foreach ($subjects as $subject) {
                $avg = $class['subjectAverages'][$subject->getId()] ?? null;
                $line[] = $avg !== null ? $avg : '';
            }
            $sheet->fromArray($line, null, 'A' . $rowNum);
            $rowNum++;
        }

        foreach (range(1, \count($header)) as $i) {
            $sheet->getColumnDimension(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
        }

        $filename = 'moyennes_par_matiere_' . ($school->getCode() ?: 'export') . '_' . date('Ymd_His') . '.xlsx';

        return $this->streamSpreadsheet($spreadsheet, $filename);
    }

    private function streamSpreadsheet(Spreadsheet $spreadsheet, string $filename): StreamedResponse
    {
        $response = new StreamedResponse(static function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        });
        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
        $response->headers->set('Cache-Control', 'max-age=0');

        return $response;
    }

    #[Route('/recap-matiere/pdf', name: 'recap_matiere', methods: ['GET'])]
    public function recapMatiere(Request $request): Response
    {
        $school = $this->schoolContextService->getCurrentSchool();
        $year = $this->schoolContextService->getCurrentSchoolYear();
        if (!$school) {
            $this->addFlash('warning', 'Veuillez sélectionner un établissement pour générer ce rapport.');
            return $this->redirectToRoute('admin_academic_report_index');
        }
        $schoolId = $school->getId();
        $yearId = $year?->getId();

        // Période affichée dans la colonne « trimestre » : première période de l'année.
        $periods = $this->periodRepository->findBySchoolAndYear($schoolId, $yearId);
        $period = $periods[0] ?? null;
        if (!$period) {
            $this->addFlash('warning', "Aucune période scolaire n'est définie pour l'année en cours.");
            return $this->redirectToRoute('admin_academic_report_index');
        }
        $pid = $period->getId();

        // Niveau : 0 = tous les niveaux.
        $niveauId = (int) $request->query->get('niveau', '0');

        $subjects = $this->orderedSubjects($schoolId);

        $classrooms = $this->classroomRepository->findBySchoolAndYear($schoolId, $yearId);
        if ($niveauId > 0) {
            $classrooms = array_values(array_filter($classrooms, static fn ($c) => $c->getLevel()?->getId() === $niveauId));
        }
        usort($classrooms, static fn ($a, $b) => [$a->getLevel()?->getOrderNumber() ?? 9999, $a->getName()] <=> [$b->getLevel()?->getOrderNumber() ?? 9999, $b->getName()]);

        $classes = [];
        foreach ($classrooms as $classroom) {
            $students = $this->studentRepository->findActiveByClassroom($classroom->getId());
            usort($students, static fn ($a, $b) => [strcmp($a->getLastName(), $b->getLastName()), strcmp($a->getFirstName(), $b->getFirstName())] <=> [0, 0]);

            // Moyennes (période + annuelle) de chaque élève, en 2 requêtes par élève.
            $perStudent = [];
            foreach ($students as $student) {
                $perStudent[] = [
                    'student' => $student,
                    'period' => $this->gradeRepository->periodSubjectAveragesByStudent($student->getId(), $pid, true),
                    'annual' => $this->gradeRepository->annualSubjectAveragesByStudent($student->getId(), $yearId, true),
                ];
            }

            $subjectBlocks = [];
            foreach ($subjects as $subject) {
                $sid = $subject->getId();

                // Rang « competition » (ex-aequo partagent le rang) sur la moyenne de période.
                $classed = [];
                foreach ($perStudent as $ps) {
                    if (isset($ps['period'][$sid])) {
                        $classed[] = ['id' => $ps['student']->getId(), 'moy' => $ps['period'][$sid]];
                    }
                }
                usort($classed, static fn ($a, $b) => $b['moy'] <=> $a['moy']);
                $rankById = [];
                $prev = null;
                $rankNum = 0;
                foreach ($classed as $i => $c) {
                    if ($prev === null || $c['moy'] < $prev) {
                        $rankNum = $i + 1;
                        $ex = false;
                    } else {
                        $ex = true;
                    }
                    $prev = $c['moy'];
                    $rankById[$c['id']] = $this->formatRank($rankNum) . ($ex ? ' EX' : '');
                }

                $rows = [];
                $seq = 0;
                $hasData = false;
                foreach ($perStudent as $ps) {
                    $student = $ps['student'];
                    $moy = $ps['period'][$sid] ?? null;
                    $annual = $ps['annual'][$sid] ?? null;
                    if ($moy !== null || $annual !== null) {
                        $hasData = true;
                    }
                    $rows[] = [
                        'seq' => ++$seq,
                        'matric' => $student->getMatriculeNational() ?: ($student->getMatriculeInterne() ?: 'AUCUN'),
                        'nom' => trim($student->getLastName() . ' ' . $student->getFirstName()),
                        'moy' => $moy,
                        'rank' => $moy !== null ? ($rankById[$student->getId()] ?? 'NC') : 'NC',
                        'annual' => $annual,
                    ];
                }

                // On n'imprime pas les matières sans aucune note dans cette classe.
                if (!$hasData) {
                    continue;
                }

                $subjectBlocks[] = ['name' => $subject->getName(), 'rows' => $rows];
            }

            if ($subjectBlocks === []) {
                continue;
            }

            $classes[] = [
                'label' => $classroom->getName(),
                'subjects' => $subjectBlocks,
            ];
        }

        return $this->renderPdf('academic_report/recap_matiere_pdf.html.twig', [
            'school' => $school,
            'school_year' => $year,
            'period' => $period,
            'classes' => $classes,
            'logo_data' => $this->logoData($school),
            'generated_at' => new \DateTime(),
        ], 'RECAP_MOYENNES_MATIERE.pdf');
    }

    /**
     * Bordereau annuel par niveau : une section par classe du niveau, avec le total
     * des points, la moyenne générale annuelle, le rang et une colonne d'observation.
     */
    #[Route('/bordereau-niveau/pdf', name: 'bordereau_niveau', methods: ['GET'])]
    public function bordereauNiveau(Request $request): Response
    {
        $school = $this->schoolContextService->getCurrentSchool();
        $year = $this->schoolContextService->getCurrentSchoolYear();
        if (!$school) {
            $this->addFlash('warning', 'Veuillez sélectionner un établissement pour générer ce rapport.');
            return $this->redirectToRoute('admin_academic_report_index');
        }
        $yearId = $year?->getId();

        $niveauId = (int) $request->query->get('niveau', '0');
        if ($niveauId <= 0) {
            $this->addFlash('warning', 'Veuillez sélectionner un niveau pour générer ce rapport.');
            return $this->redirectToRoute('admin_academic_report_index');
        }
        $level = $this->levelRepository->find($niveauId);

        // Coefficient de chaque matière (pour le total des points).
        $coefBySubject = [];
        foreach ($this->orderedSubjects($school->getId()) as $subj) {
            $coefBySubject[$subj->getId()] = (float) $subj->getCoefficient();
        }

        $classrooms = array_values(array_filter(
            $this->classroomRepository->findBySchoolAndYear($school->getId(), $yearId),
            static fn ($c) => $c->getLevel()?->getId() === $niveauId
        ));
        usort($classrooms, static fn ($a, $b) => strcmp((string) $a->getName(), (string) $b->getName()));

        $classes = [];
        foreach ($classrooms as $classroom) {
            $entries = [];
            foreach ($this->studentRepository->findActiveByClassroom($classroom->getId()) as $student) {
                $map = $this->gradeRepository->annualSubjectAveragesByStudent($student->getId(), $yearId, true);
                $general = $this->gradeRepository->calculateAnnualGeneralAverageByStudent($student->getId(), $yearId, true);

                $total = null;
                if ($map !== []) {
                    $t = 0.0;
                    foreach ($map as $subjectId => $moy) {
                        $t += $moy * ($coefBySubject[$subjectId] ?? 1.0);
                    }
                    $total = round($t, 2);
                }
                $entries[] = ['student' => $student, 'general' => $general, 'total' => $total];
            }

            // Rang « competition » sur la moyenne générale (ex-aequo « EX »).
            $graded = array_values(array_filter($entries, static fn ($e) => $e['general'] !== null));
            usort($graded, static fn ($a, $b) => $b['general'] <=> $a['general']);
            $rankById = [];
            $prev = null;
            $rankNum = 0;
            foreach ($graded as $i => $e) {
                if ($prev === null || $e['general'] < $prev) {
                    $rankNum = $i + 1;
                    $ex = false;
                } else {
                    $ex = true;
                }
                $prev = $e['general'];
                $rankById[$e['student']->getId()] = $this->formatRank($rankNum) . ($ex ? ' EX' : '');
            }

            usort($entries, static fn ($a, $b) => strcmp((string) $a['student']->getLastName(), (string) $b['student']->getLastName()));

            $rows = [];
            $seq = 0;
            foreach ($entries as $e) {
                $rows[] = [
                    'seq' => ++$seq,
                    'nom' => trim($e['student']->getLastName() . ' ' . $e['student']->getFirstName()),
                    'total' => $e['total'],
                    'moy' => $e['general'],
                    'rank' => $e['general'] !== null ? ($rankById[$e['student']->getId()] ?? 'NC') : 'NC',
                    'obs' => $e['general'] !== null ? ($this->gradeCalculationService->getMention($e['general']) ?? '') : '',
                ];
            }

            $classes[] = ['label' => $classroom->getName(), 'rows' => $rows];
        }

        return $this->renderPdf('academic_report/bordereau_niveau_pdf.html.twig', [
            'school' => $school,
            'school_year' => $year,
            'level' => $level,
            'classes' => $classes,
            'logo_data' => $this->logoData($school),
            'generated_at' => new \DateTime(),
        ], sprintf('BORDEREAU_ANNUEL_%s.pdf', $level ? $level->getName() : 'niveau'));
    }

    /**
     * Livret scolaire individuel d'un élève (toutes périodes + annuel, par matière).
     * La sélection niveau → classe → élève se fait via un modal sur la page des rapports.
     */
    #[Route('/livret/eleve/{id}/pdf', name: 'livret_student', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function livretStudent(\App\Entity\Student $student): Response
    {
        $school = $this->schoolContextService->getCurrentSchool();
        if (!$school || $student->getSchool()?->getId() !== $school->getId()) {
            $this->addFlash('warning', 'Élève introuvable pour l\'établissement courant.');
            return $this->redirectToRoute('admin_academic_report_index');
        }

        $data = $this->buildLivret($student, $school);

        return $this->renderPdf('academic_report/livret_student_pdf.html.twig', array_merge([
            'school' => $school,
            'student' => $student,
            'logo_data' => $this->logoData($school),
            'generated_at' => new \DateTime(),
        ], $data), sprintf('LIVRET_%s.pdf', $student->getMatriculeInterne() ?: $student->getId()), 'landscape');
    }

    /**
     * Construit le livret scolaire d'un élève : matières groupées par type, avec
     * moyenne + rang par période et en annuel, bilans par groupe et totaux.
     *
     * @return array<string, mixed>
     */
    private function buildLivret(\App\Entity\Student $student, \App\Entity\School $school): array
    {
        $year = $this->schoolContextService->getCurrentSchoolYear();
        $yearId = $year?->getId();
        $classroom = $student->getClassroom();

        $periods = $this->periodRepository->findBySchoolAndYear($school->getId(), $yearId);
        $subjects = $this->orderedSubjects($school->getId());
        usort($subjects, static fn ($a, $b) => [
            $a->getType()?->getOrderNumber() ?? 9999, $a->getBulletinOrderNumber() ?? 9999, (string) $a->getName(),
        ] <=> [
            $b->getType()?->getOrderNumber() ?? 9999, $b->getBulletinOrderNumber() ?? 9999, (string) $b->getName(),
        ]);

        // Professeur de chaque matière pour la classe (depuis les cours / emploi du temps).
        $teacherBySubject = [];
        if ($classroom) {
            foreach ($this->courseRepository->findByClassroom($classroom->getId()) as $course) {
                $subjectId = $course->getSubject()?->getId();
                $teacher = $course->getTeacher();
                if ($subjectId !== null && $teacher !== null && !isset($teacherBySubject[$subjectId])) {
                    $teacherBySubject[$subjectId] = $teacher->getFullName();
                }
            }
        }

        // Élèves de la classe pour le calcul des rangs.
        $classStudents = $classroom ? $this->studentRepository->findActiveByClassroom($classroom->getId()) : [$student];

        // Cartes de moyennes par élève : période puis annuel.
        $periodMaps = [];   // [periodId][studentId] => [subjectId => moy]
        $periodGeneral = []; // [periodId][studentId] => moy générale
        foreach ($periods as $p) {
            foreach ($classStudents as $s) {
                $periodMaps[$p->getId()][$s->getId()] = $this->gradeRepository->periodSubjectAveragesByStudent($s->getId(), $p->getId(), true);
                $periodGeneral[$p->getId()][$s->getId()] = $this->gradeRepository->calculateGeneralAverageByStudentAndPeriod($s->getId(), $p->getId(), true);
            }
        }
        $annualMaps = [];
        $annualGeneral = [];
        foreach ($classStudents as $s) {
            $annualMaps[$s->getId()] = $this->gradeRepository->annualSubjectAveragesByStudent($s->getId(), $yearId, true);
            $annualGeneral[$s->getId()] = $this->gradeRepository->calculateAnnualGeneralAverageByStudent($s->getId(), $yearId, true);
        }

        // Rang « compétition » d'une valeur parmi une liste studentId => moy.
        $rankOf = static function (array $moys, int $studentId): ?string {
            if (!isset($moys[$studentId])) {
                return null;
            }
            $vals = array_values(array_filter($moys, static fn ($v) => $v !== null));
            rsort($vals);
            $target = $moys[$studentId];
            $rank = 1;
            foreach ($vals as $v) {
                if ($v > $target) {
                    $rank++;
                }
            }
            return self::formatRankStatic($rank);
        };

        $sid = $student->getId();

        // Regroupement par type de matière (bilans).
        $groups = [];
        foreach ($subjects as $subject) {
            $typeLabel = $subject->getType()?->getLabel() ?: 'Autres matières';
            if (!isset($groups[$typeLabel])) {
                $groups[$typeLabel] = ['label' => $typeLabel, 'rows' => [], 'bilan' => []];
            }

            $row = [
                'subject' => $subject->getName(),
                'coef' => (float) $subject->getCoefficient(),
                'teacher' => $teacherBySubject[$subject->getId()] ?? null,
                'periods' => [],
                'annual_moy' => $annualMaps[$sid][$subject->getId()] ?? null,
            ];
            foreach ($periods as $p) {
                $moy = $periodMaps[$p->getId()][$sid][$subject->getId()] ?? null;
                $subjectMoys = [];
                foreach ($classStudents as $s) {
                    if (isset($periodMaps[$p->getId()][$s->getId()][$subject->getId()])) {
                        $subjectMoys[$s->getId()] = $periodMaps[$p->getId()][$s->getId()][$subject->getId()];
                    }
                }
                $row['periods'][] = ['moy' => $moy, 'rank' => $moy !== null ? $rankOf($subjectMoys, $sid) : null];
            }
            // Rang annuel par matière.
            $annualSubjectMoys = [];
            foreach ($classStudents as $s) {
                if (isset($annualMaps[$s->getId()][$subject->getId()])) {
                    $annualSubjectMoys[$s->getId()] = $annualMaps[$s->getId()][$subject->getId()];
                }
            }
            $row['annual_rank'] = $row['annual_moy'] !== null ? $rankOf($annualSubjectMoys, $sid) : null;
            $row['appreciation'] = $row['annual_moy'] !== null ? $this->gradeCalculationService->getAppreciation($row['annual_moy']) : null;

            $groups[$typeLabel]['rows'][] = $row;
        }

        // Bilan par groupe et par période (moyenne simple des matières notées du groupe).
        foreach ($groups as $label => $g) {
            $bilan = [];
            foreach (array_keys($periods) as $i) {
                $vals = [];
                foreach ($g['rows'] as $r) {
                    if ($r['periods'][$i]['moy'] !== null) {
                        $vals[] = $r['periods'][$i]['moy'];
                    }
                }
                $bilan[] = $vals ? round(array_sum($vals) / \count($vals), 2) : null;
            }
            $groups[$label]['bilan'] = $bilan;
        }

        // Totaux par période : coefficient total et somme des moyennes.
        $totaux = [];
        $coefTotal = 0.0;
        foreach ($subjects as $subject) {
            $coefTotal += (float) $subject->getCoefficient();
        }
        foreach ($periods as $index => $p) {
            $sum = 0.0;
            foreach ($subjects as $subject) {
                $moy = $periodMaps[$p->getId()][$sid][$subject->getId()] ?? null;
                if ($moy !== null) {
                    $sum += $moy;
                }
            }
            $totaux[] = ['general' => $periodGeneral[$p->getId()][$sid] ?? null, 'sum' => round($sum, 2)];
        }

        // Rang général annuel et par période.
        $annualRankStr = $rankOf(array_map(static fn ($v) => $v, $annualGeneral), $sid);
        $periodGeneralRank = [];
        foreach ($periods as $p) {
            $periodGeneralRank[] = $rankOf($periodGeneral[$p->getId()], $sid);
        }

        $general = $annualGeneral[$sid] ?? null;

        return [
            'school_year' => $year,
            'classroom' => $classroom,
            'effectif' => \count($classStudents),
            'periods' => $periods,
            'groups' => array_values($groups),
            'totaux' => $totaux,
            'coef_total' => $coefTotal,
            'annual_general' => $general,
            'annual_rank' => $annualRankStr,
            'period_general_rank' => $periodGeneralRank,
            'mention' => $general !== null ? $this->gradeCalculationService->getMention($general) : null,
            'decision' => $this->decision($general),
        ];
    }

    /** Format de rang statique (« 1 er », « 3 è ») pour les closures. */
    private static function formatRankStatic(int $rank): string
    {
        return $rank === 1 ? '1 er' : $rank . ' è';
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
    private function computeNominalResults(?int $levelId = null): array
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
            if ($levelId !== null && $classroom->getLevel()?->getId() !== $levelId) {
                continue;
            }
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
     * Moyennes de classe par matière (moyenne des moyennes des élèves), en trimestriel
     * (période donnée) ou en annuel.
     *
     * @return array{0: ?School, 1: ?\App\Entity\SchoolYear, 2: list<array<string, mixed>>, 3: list<\App\Entity\Subject>}
     */
    private function computeSubjectAverages(?int $periodId = null): array
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
                $studentAverages = $periodId !== null
                    ? $this->gradeRepository->periodSubjectAveragesByStudent($student->getId(), $periodId, true)
                    : $this->gradeRepository->annualSubjectAveragesByStudent($student->getId(), $yearId, true);
                foreach ($studentAverages as $sid => $avg) {
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
    private function computeResultStats(?string $statusFilter = null, ?int $periodId = null): array
    {
        [$school, $year, $rawClasses] = $this->computeClassResults($statusFilter, $periodId);
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
            // Tranches de moyenne pour la synthèse : ≥ 10 ; [8,5 ; 10[ ; < 8,5.
            'b10' => 0, 'b85' => 0, 'blow' => 0,
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

        // Tranches de moyenne (synthèse générale).
        if ($average >= self::PASS_MARK) {
            $stat['b10']++;
        } elseif ($average >= 8.5) {
            $stat['b85']++;
        } else {
            $stat['blow']++;
        }

        $bucket = $gender === 'F' ? 'f' : ($gender === 'M' ? 'g' : null);
        if ($bucket !== null) {
            $stat[$bucket]['noted']++;
            $pass ? $stat[$bucket]['admis']++ : $stat[$bucket]['redouble']++;
        }
    }

    private function mergeStat(array &$target, array $src): void
    {
        foreach (['count', 'graded', 'admis', 'redouble', 'exclus', 'sum', 'b10', 'b85', 'blow'] as $k) {
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
    private function computeClassResults(?string $statusFilter = null, ?int $periodId = null): array
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
                // Moyenne trimestrielle (période donnée) ou annuelle selon le rapport.
                $average = $periodId !== null
                    ? $this->gradeRepository->calculateGeneralAverageByStudentAndPeriod($student->getId(), $periodId, true)
                    : $this->gradeRepository->calculateAnnualGeneralAverageByStudent($student->getId(), $yearId, true);
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
