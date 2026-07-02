<?php

namespace App\Service;

use App\Entity\Period;
use App\Entity\Student;
use App\Repository\CourseRepository;
use App\Repository\GradeRepository;
use App\Repository\SubjectRepository;
use Doctrine\ORM\EntityManagerInterface;

class GradeCalculationService
{
    /**
     * Abréviations des matières (colonnes du bordereau), par matière parente
     * (SubjectEquivalent::subjectParent). Repli sur le code de l'équivalent.
     */
    private const SUBJECT_ABBR = [
        'FRANÇAIS' => 'FR',
        'MATHÉMATIQUE' => 'MAT',
        'HISTOIRE-GÉOGRAPHIE' => 'HG',
        'PHYSIQUE-CHIMIE' => 'PC',
        'SVT' => 'SVT',
        'PHILOSOPHIE' => 'PHILO',
        'ANGLAIS' => 'ANG',
        'ESPAGNOL' => 'ESP',
        'ALLEMAND' => 'ALL',
        'EPS' => 'EPS',
        'MUSIQUE' => 'MUS',
        'EDHC' => 'EDHC',
        'CONDUITE' => 'COND',
        'ART-PLASTIQUE' => 'ART',
        'DICTÉE' => 'DICT',
        "ACTIVITÉ D'ÉVEIL AU MILIEU" => 'AEM',
        'EXPLOITATION DE TEXTE' => 'EDT',
        'COMPOSITION FRANÇAISE' => 'CF',
        'ORTHOGRAPHE' => 'ORTH',
        'EXPRESSION ORALE' => 'EO',
    ];

    public function __construct(
        private GradeRepository $gradeRepository,
        private SubjectRepository $subjectRepository,
        private EntityManagerInterface $entityManager,
        private ?CourseRepository $courseRepository = null
    ) {
    }

    /**
     * Calcule toutes les moyennes d'un élève pour une période
     */
    public function calculateStudentAveragesForPeriod(Student $student, Period $period): array
    {
        $results = [
            'subjects' => [],
            'general_average' => null,
            'total_coefficient' => 0,
            'class_rank' => null,
            'class_total' => null,
        ];

        // Récupérer toutes les matières de l'établissement
        $subjects = $this->subjectRepository->findBySchool($period->getSchool()->getId());

        $totalPoints = 0;
        $totalCoefficient = 0;

        // Pénalité d'absences (toutes heures), retirée à la matière de conduite.
        $penalty = $this->absenceSummary($student->getId(), $period->getId())['penalty'];

        foreach ($subjects as $subject) {
            $average = $this->gradeRepository->calculateAverageByStudentSubjectAndPeriod(
                $student->getId(),
                $subject->getId(),
                $period->getId(),
                true
            );

            $isConduite = $this->isConduiteSubject($subject);

            // Matière non conduite et non notée : ignorée. La conduite est toujours
            // prise en compte (elle part de sa moyenne, ou du barème si non notée).
            if ($average === null && !$isConduite) {
                continue;
            }

            $subjectCoef = (float) $subject->getCoefficient();
            $bareme = (int) ($subject->getNoteSurBulletin() ?: 20);
            $base = $average !== null ? $average * $bareme / 20 : (float) $bareme;
            $noteBareme = $isConduite ? max(0.0, $base - $penalty) : $base;

            $results['subjects'][] = [
                'subject' => $subject,
                'average' => $average,
                'bareme' => $bareme,
                'note_bareme' => round($noteBareme, 2),
                'coefficient' => $subjectCoef,
                'weighted_average' => round($noteBareme * $subjectCoef, 2),
                'is_conduite' => $isConduite,
            ];

            $totalPoints += $noteBareme * $subjectCoef;
            $totalCoefficient += $subjectCoef;
        }

        if ($totalCoefficient > 0) {
            $results['general_average'] = round($totalPoints / $totalCoefficient, 2);
            $results['total_coefficient'] = $totalCoefficient;
        }

        return $results;
    }

    /**
     * Synthèse des absences d'un élève sur une période : heures totales / justifiées /
     * non justifiées, et pénalité de conduite = Σ (penaltyPoints du type) — un retrait
     * FIXE par absence, appliqué UNIQUEMENT aux absences NON justifiées. Les absences
     * justifiées ne génèrent aucune pénalité.
     *
     * @return array{total: float, justified: float, unjustified: float, penalty: float, count: int}
     */
    public function absenceSummary(int $studentId, int $periodId): array
    {
        $absences = $this->entityManager->getRepository(\App\Entity\Absence::class)
            ->findActiveByStudentAndPeriod($studentId, $periodId);

        $total = 0.0;
        $justified = 0.0;
        $unjustified = 0.0;
        $penalty = 0.0;

        foreach ($absences as $absence) {
            $hours = (float) ($absence->getDurationInHours() ?? 0);
            $total += $hours;

            if ($absence->getJustificationStatus() === 'justified') {
                // Absence justifiée : aucune pénalité de conduite.
                $justified += $hours;
            } else {
                $unjustified += $hours; // non justifiée + en attente
                // Pénalité (retrait fixe configuré sur le type d'absence) appliquée
                // UNIQUEMENT aux absences non justifiées.
                $penalty += (float) ($absence->getAbsenceType()?->getPenaltyPoints() ?? 0);
            }
        }

        return [
            'total' => round($total, 2),
            'justified' => round($justified, 2),
            'unjustified' => round($unjustified, 2),
            'penalty' => round($penalty, 2),
            'count' => \count($absences),
        ];
    }

    /**
     * Une matière est « de conduite » lorsqu'elle est rattachée à une matière
     * équivalente dont la matière parente est CONDUITE. L'ancien indicateur
     * Subject::matiereConduite = OUI est conservé en repli (compatibilité).
     * C'est sur la note de cette matière qu'est déduite la pénalité d'absences.
     */
    private function isConduiteSubject(\App\Entity\Subject $subject): bool
    {
        if (strtoupper((string) $subject->getSubjectEquivalent()?->getSubjectParent()) === 'CONDUITE') {
            return true;
        }

        return strtoupper((string) $subject->getMatiereConduite()) === 'OUI';
    }

    /**
     * Calcule le classement d'un élève dans sa classe pour une période
     */
    public function calculateClassRanking(Student $student, Period $period, int $classroomId): array
    {
        // Récupérer tous les élèves (actifs) de la classe
        $students = $this->entityManager->getRepository(Student::class)
            ->findActiveByClassroom($classroomId);

        $averages = [];

        foreach ($students as $classStudent) {
            $average = $this->gradeRepository->calculateGeneralAverageByStudentAndPeriod(
                $classStudent->getId(),
                $period->getId(),
                true
            );

            if ($average !== null) {
                $averages[$classStudent->getId()] = $average;
            }
        }

        // Trier les moyennes par ordre décroissant
        arsort($averages);

        // Trouver le rang de l'élève
        $rank = 1;
        foreach ($averages as $studentId => $average) {
            if ($studentId == $student->getId()) {
                return [
                    'rank' => $rank,
                    'total' => count($averages),
                    'class_average' => count($averages) > 0 ? round(array_sum($averages) / count($averages), 2) : null,
                    'best_average' => count($averages) > 0 ? reset($averages) : null,
                    'worst_average' => count($averages) > 0 ? end($averages) : null,
                ];
            }
            $rank++;
        }

        return [
            'rank' => null,
            'total' => count($averages),
            'class_average' => null,
            'best_average' => null,
            'worst_average' => null,
        ];
    }

    /**
     * Récupère toutes les notes d'un élève pour une période
     */
    public function getStudentGradesForPeriod(Student $student, Period $period): array
    {
        return $this->gradeRepository->findByStudentAndPeriod(
            $student->getId(),
            $period->getId()
        );
    }

    /**
     * Génère les données complètes pour un bulletin
     */
    public function generateBulletinData(Student $student, Period $period, int $classroomId): array
    {
        $averages = $this->calculateStudentAveragesForPeriod($student, $period);
        $ranking = $this->calculateClassRanking($student, $period, $classroomId);
        $grades = $this->getStudentGradesForPeriod($student, $period);

        // Organiser les notes par matière
        $gradesBySubject = [];
        foreach ($grades as $grade) {
            $subjectId = $grade->getEvaluation()->getSubject()->getId();
            if (!isset($gradesBySubject[$subjectId])) {
                $gradesBySubject[$subjectId] = [];
            }
            $gradesBySubject[$subjectId][] = $grade;
        }

        return [
            'student' => $student,
            'period' => $period,
            'averages' => $averages,
            'ranking' => $ranking,
            'grades_by_subject' => $gradesBySubject,
            'generated_at' => new \DateTime(),
        ];
    }

    /**
     * Données complètes pour le bulletin au modèle officiel : une ligne par matière
     * (moyenne, coef, moy×coef, rang, professeur, appréciation), totaux, statistiques
     * de classe et mention.
     *
     * @return array<string, mixed>
     */
    public function generateBulletinSheet(Student $student, Period $period, int $classroomId): array
    {
        $schoolId = $period->getSchool()?->getId();
        $periodId = $period->getId();

        // Matières du niveau de la classe, triées pour le bulletin.
        $classroom = $this->entityManager->getRepository(\App\Entity\Classroom::class)->find($classroomId);
        $levelId = $classroom?->getLevel()?->getId();
        $subjects = $levelId
            ? $this->subjectRepository->findBySchoolAndLevel($schoolId, $levelId)
            : $this->subjectRepository->findBySchool($schoolId);
        usort($subjects, static function ($a, $b) {
            $oa = $a->getBulletinOrderNumber() ?? 9999;
            $ob = $b->getBulletinOrderNumber() ?? 9999;
            return $oa === $ob ? strcmp((string) $a->getName(), (string) $b->getName()) : $oa <=> $ob;
        });

        // Élèves de la classe.
        $classStudents = $this->entityManager->getRepository(Student::class)->findActiveByClassroom($classroomId);
        $effectif = \count($classStudents);

        // Professeur par matière (à partir des cours de la classe).
        $teacherBySubject = [];
        if ($this->courseRepository) {
            foreach ($this->courseRepository->findByClassroom($classroomId) as $course) {
                if ($course->getSubject() && $course->getTeacher()) {
                    $teacherBySubject[$course->getSubject()->getId()] = $course->getTeacher()->getFullName();
                }
            }
        }

        // Barème par matière (« note sur bulletin », 20 par défaut), matières de conduite,
        // et regroupement par matière équivalente partagée (ex. Composition / Expression
        // écrite / orale → FRANÇAIS) pour les fusionner en une matière parente.
        $baremeBySubject = [];
        $conduiteSids = [];
        $subjectById = [];
        $equivGroup = []; // equivId => [subjectId, ...] dans l'ordre d'affichage
        foreach ($subjects as $subject) {
            $sid = $subject->getId();
            $subjectById[$sid] = $subject;
            $baremeBySubject[$sid] = (int) ($subject->getNoteSurBulletin() ?: 20);
            if ($this->isConduiteSubject($subject)) {
                $conduiteSids[$sid] = true;
            }
            $eq = $subject->getSubjectEquivalent();
            if ($eq) {
                $equivGroup[$eq->getId()][] = $sid;
            }
        }
        // Un équivalent partagé par plusieurs matières => matière parente fusionnée.
        $mergedEquivIds = [];
        foreach ($equivGroup as $eqId => $sids) {
            if (\count($sids) > 1) {
                $mergedEquivIds[$eqId] = true;
            }
        }

        // Pré-calcul des notes par matière (sur barème, pénalité d'absences déduite de la
        // conduite) et des moyennes générales : Σ(note_sur_barème × coef) / Σ(coef).
        $subjectNotes = []; // [subjectId][studentId] => note sur barème
        $generalAverages = [];
        foreach ($classStudents as $cs) {
            $penaltyCs = $this->absenceSummary($cs->getId(), $periodId)['penalty'];
            $tp = 0.0;
            $tc = 0.0;
            foreach ($subjects as $subject) {
                $sid = $subject->getId();
                $b = $baremeBySubject[$sid];
                $coef = (float) $subject->getCoefficient();
                $avg = $this->gradeRepository->calculateAverageByStudentSubjectAndPeriod($cs->getId(), $sid, $periodId, true);

                if (isset($conduiteSids[$sid])) {
                    // Conduite : part de la moyenne (ou du barème si non notée), moins la pénalité.
                    $base = $avg !== null ? $avg * $b / 20 : (float) $b;
                    $note = max(0.0, $base - $penaltyCs);
                } elseif ($avg !== null) {
                    $note = $avg * $b / 20;
                } else {
                    continue;
                }

                $subjectNotes[$sid][$cs->getId()] = round($note, 2);
                $tp += $note * $coef;
                $tc += $coef;
            }
            $generalAverages[$cs->getId()] = $tc > 0 ? round($tp / $tc, 2) : null;
        }

        // Note « fusionnée » par matière parente (moyenne pondérée des composantes)
        // pour chaque élève de la classe — sert au classement de la matière parente.
        $equivUnitNotes = []; // equivId => [studentId => note sur barème]
        foreach (array_keys($mergedEquivIds) as $eqId) {
            foreach ($classStudents as $cs) {
                $mc = 0.0;
                $cf = 0.0;
                foreach ($equivGroup[$eqId] as $sidc) {
                    $n = $subjectNotes[$sidc][$cs->getId()] ?? null;
                    if ($n === null) {
                        continue;
                    }
                    $cfc = (float) $subjectById[$sidc]->getCoefficient();
                    $mc += $n * $cfc;
                    $cf += $cfc;
                }
                if ($cf > 0) {
                    $equivUnitNotes[$eqId][$cs->getId()] = round($mc / $cf, 2);
                }
            }
        }

        // Lignes du bulletin pour l'élève cible, regroupées par type de matière
        // (avec un sous-total par type). Les matières conservent leur ordre
        // d'affichage à l'intérieur de chaque groupe ; les groupes sont triés
        // selon l'ordre du type de matière.
        $rows = [];
        $groupsByKey = []; // typeKey => ['label','order','rows','coef','moy_coef']
        $totalCoef = 0.0;
        $totalMoyCoef = 0.0;
        $emittedEquiv = [];
        foreach ($subjects as $subject) {
            $sid = $subject->getId();
            $eq = $subject->getSubjectEquivalent();
            $eqId = $eq?->getId();
            $isMerged = $eqId !== null && isset($mergedEquivIds[$eqId]);

            // Composante d'une matière parente déjà rendue : on l'ignore (affichée
            // en sous-ligne sous le parent).
            if ($isMerged && isset($emittedEquiv[$eqId])) {
                continue;
            }

            $type = $subject->getType();
            $typeKey = $type?->getId() ?? 0;

            if (!isset($groupsByKey[$typeKey])) {
                $groupsByKey[$typeKey] = [
                    'label' => $type?->getLabel() ?: 'Autres matières',
                    'order' => $type?->getOrderNumber() ?? 9999,
                    'rows' => [],
                    'coef' => 0.0,
                    'moy_coef' => 0.0,
                ];
            }

            // ── Matière parente fusionnée (ex. FRANÇAIS = Composition + Expression…) ──
            if ($isMerged) {
                $emittedEquiv[$eqId] = true;
                $parentName = $eq->getSubjectParent() ?: ($eq->getLibelle() ?: $subject->getName());

                $subs = [];
                $mc = 0.0;
                $cf = 0.0;
                $baremeP = null;
                foreach ($equivGroup[$eqId] as $sidc) {
                    $comp = $subjectById[$sidc];
                    $bC = $baremeBySubject[$sidc] ?? 20;
                    $baremeP = $baremeP ?? $bC;
                    $nb = $subjectNotes[$sidc][$student->getId()] ?? null;
                    if ($nb === null) {
                        $subs[] = ['name' => $comp->getName(), 'nc' => true];
                        continue;
                    }
                    $coefC = (float) $comp->getCoefficient();
                    $subs[] = ['name' => $comp->getName(), 'nc' => false, 'moy_bareme' => $nb, 'bareme' => $bC];
                    $mc += $nb * $coefC;
                    $cf += $coefC;
                }

                if ($cf > 0) {
                    $baremeP = $baremeP ?: 20;
                    $noteP = round($mc / $cf, 2);
                    $avg20 = $baremeP > 0 ? round($noteP * 20 / $baremeP, 2) : $noteP;
                    $rankInfo = $this->rankAmong($equivUnitNotes[$eqId] ?? [], $student->getId());
                    $row = [
                        'name' => $parentName,
                        'nc' => false,
                        'moy' => $avg20,
                        'bareme' => $baremeP,
                        'moy_bareme' => $noteP,
                        'coef' => $cf,
                        'moy_coef' => round($mc, 2),
                        'rank' => $rankInfo['rank'],
                        'ex' => $rankInfo['ex'],
                        'teacher' => null,
                        'appreciation' => $this->getAppreciation($avg20),
                        'conduite' => false,
                        'subs' => $subs,
                    ];
                    $rows[] = $row;
                    $groupsByKey[$typeKey]['rows'][] = $row;
                    $groupsByKey[$typeKey]['coef'] += $cf;
                    $groupsByKey[$typeKey]['moy_coef'] += $mc;
                    $totalCoef += $cf;
                    $totalMoyCoef += $mc;
                } else {
                    $row = ['name' => $parentName, 'nc' => true, 'teacher' => null, 'subs' => $subs];
                    $rows[] = $row;
                    $groupsByKey[$typeKey]['rows'][] = $row;
                }

                continue;
            }

            // ── Matière simple (équivalent 1:1 ou sans équivalent) ──
            $noteBareme = $subjectNotes[$sid][$student->getId()] ?? null;

            if ($noteBareme === null) {
                $row = ['name' => $subject->getName(), 'nc' => true, 'teacher' => $teacherBySubject[$sid] ?? null];
                $rows[] = $row;
                $groupsByKey[$typeKey]['rows'][] = $row;
                continue;
            }

            $coef = (float) $subject->getCoefficient();
            $bareme = $baremeBySubject[$sid] ?? 20;
            $avg20 = $bareme > 0 ? round($noteBareme * 20 / $bareme, 2) : $noteBareme; // équivalent /20 (appréciation)
            $moyCoef = round($noteBareme * $coef, 2);
            $rankInfo = $this->rankAmong($subjectNotes[$sid] ?? [], $student->getId());

            $row = [
                'name' => $subject->getName(),
                'nc' => false,
                'moy' => $avg20,
                'bareme' => $bareme,
                'moy_bareme' => $noteBareme,
                'coef' => $coef,
                'moy_coef' => $moyCoef,
                'rank' => $rankInfo['rank'],
                'ex' => $rankInfo['ex'],
                'teacher' => $teacherBySubject[$sid] ?? null,
                'appreciation' => $this->getAppreciation($avg20),
                'conduite' => isset($conduiteSids[$sid]),
            ];
            $rows[] = $row;
            $groupsByKey[$typeKey]['rows'][] = $row;
            $groupsByKey[$typeKey]['coef'] += $coef;
            $groupsByKey[$typeKey]['moy_coef'] += $moyCoef;

            $totalCoef += $coef;
            $totalMoyCoef += $moyCoef;
        }

        // Tri des groupes (ordre du type, puis libellé) et finalisation des sous-totaux.
        $groups = array_values($groupsByKey);
        usort($groups, static fn ($a, $b) => $a['order'] === $b['order']
            ? strcmp((string) $a['label'], (string) $b['label'])
            : $a['order'] <=> $b['order']);
        foreach ($groups as &$g) {
            $g['coef'] = round($g['coef'], 2);
            $g['moy_coef'] = round($g['moy_coef'], 2);
            $g['average'] = $g['coef'] > 0 ? round($g['moy_coef'] / $g['coef'], 2) : null;
        }
        unset($g);

        $generalAverage = $totalCoef > 0 ? round($totalMoyCoef / $totalCoef, 2) : null;

        // Rang général + statistiques de classe.
        $validGenerals = array_filter($generalAverages, static fn ($v) => $v !== null);
        $generalRank = $generalAverage !== null ? $this->rankAmong($validGenerals, $student->getId()) : ['rank' => null, 'ex' => false];
        $classMoy = \count($validGenerals) > 0 ? round(array_sum($validGenerals) / \count($validGenerals), 2) : null;

        // Moyenne générale ANNUELLE (toutes périodes de l'année) + rang dans la classe.
        $yearId = $period->getSchoolYear()?->getId();
        $annualAverages = [];
        foreach ($classStudents as $cs) {
            $av = $this->gradeRepository->calculateAnnualGeneralAverageByStudent($cs->getId(), $yearId, true);
            if ($av !== null) {
                $annualAverages[$cs->getId()] = $av;
            }
        }
        $annualAverage = $annualAverages[$student->getId()] ?? null;
        $annualRank = $annualAverage !== null ? $this->rankAmong($annualAverages, $student->getId()) : ['rank' => null, 'ex' => false];

        // Heures d'absence (et pénalité de conduite) de l'élève cible.
        $absence = $this->absenceSummary($student->getId(), $periodId);

        return [
            'student' => $student,
            'period' => $period,
            'school_year' => $period->getSchoolYear(),
            'effectif' => $effectif,
            'absence_total' => $absence['total'],
            'absence_justified' => $absence['justified'],
            'absence_unjustified' => $absence['unjustified'],
            'absence_penalty' => $absence['penalty'],
            'rows' => $rows,
            'groups' => $groups,
            'total_coef' => $totalCoef,
            'total_moy_coef' => round($totalMoyCoef, 2),
            'general_average' => $generalAverage,
            'general_rank' => $generalRank['rank'],
            'general_rank_ex' => $generalRank['ex'],
            'annual_average' => $annualAverage,
            'annual_rank' => $annualRank['rank'],
            'annual_rank_ex' => $annualRank['ex'],
            'class_average' => $classMoy,
            'class_min' => \count($validGenerals) > 0 ? min($validGenerals) : null,
            'class_max' => \count($validGenerals) > 0 ? max($validGenerals) : null,
            'graded_count' => \count($validGenerals),
            'mention' => $generalAverage !== null ? $this->getMention($generalAverage) : null,
            'honneur' => $generalAverage !== null && $generalAverage >= 12,
            'encouragement' => $generalAverage !== null && $generalAverage >= 14,
            'felicitations' => $generalAverage !== null && $generalAverage >= 16,
            'generated_at' => new \DateTime(),
        ];
    }

    /**
     * Bordereau d'évaluation (« fiche de notes ») d'un niveau : une page par
     * classe, en lignes les élèves et en colonnes les matières (fusionnées par
     * matière équivalente, ex. FRANÇAIS), avec TOTAL / MOY / RANG.
     *
     * @return array<string, mixed>
     */
    public function generateBordereaux(Period $period, \App\Entity\Level $level, \App\Entity\School $school): array
    {
        $periodId = $period->getId();

        // Matières du niveau, regroupées en colonnes par matière équivalente.
        $subjects = $this->subjectRepository->findBySchoolAndLevel($school->getId(), $level->getId());
        usort($subjects, static function ($a, $b) {
            $oa = $a->getBulletinOrderNumber() ?? 9999;
            $ob = $b->getBulletinOrderNumber() ?? 9999;
            return $oa === $ob ? strcmp((string) $a->getName(), (string) $b->getName()) : $oa <=> $ob;
        });

        $coefBySid = [];
        $baremeBySid = [];
        $conduiteSid = [];
        $columns = [];   // [colKey => ['code','order','subjectIds','conduite']]
        foreach ($subjects as $subject) {
            $sid = $subject->getId();
            $coefBySid[$sid] = (float) $subject->getCoefficient();
            $baremeBySid[$sid] = (int) ($subject->getNoteSurBulletin() ?: 20);
            $conduiteSid[$sid] = $this->isConduiteSubject($subject);

            $eq = $subject->getSubjectEquivalent();
            $colKey = $eq ? 'e' . $eq->getId() : 's' . $sid;
            if (!isset($columns[$colKey])) {
                $parent = $eq?->getSubjectParent();
                $code = ($parent !== null ? (self::SUBJECT_ABBR[mb_strtoupper($parent)] ?? null) : null)
                    ?: ($eq?->getCode() ?: ($subject->getCode() ?: $subject->getName()));
                $columns[$colKey] = [
                    'code' => $code,
                    'order' => $subject->getBulletinOrderNumber() ?? 9999,
                    'subjectIds' => [],
                    'conduite' => false,
                ];
            }
            $columns[$colKey]['subjectIds'][] = $sid;
            $columns[$colKey]['conduite'] = $columns[$colKey]['conduite'] || $conduiteSid[$sid];
        }
        $columns = array_values($columns);
        usort($columns, static fn ($a, $b) => $a['order'] <=> $b['order']);

        // Classes du niveau pour l'année de la période.
        $year = $period->getSchoolYear();
        $classrooms = $this->entityManager->getRepository(\App\Entity\Classroom::class)->findByLevel($level->getId());
        $classrooms = array_filter(
            $classrooms,
            static fn ($c) => $year === null || $c->getSchoolYear()?->getId() === $year->getId()
        );

        $pages = [];
        foreach ($classrooms as $classroom) {
            $students = $this->entityManager->getRepository(Student::class)->findActiveByClassroom($classroom->getId());
            usort($students, static fn ($a, $b) => strcmp(
                mb_strtoupper($a->getLastName() . ' ' . $a->getFirstName()),
                mb_strtoupper($b->getLastName() . ' ' . $b->getFirstName())
            ));

            // Note par colonne + moyenne générale de chaque élève.
            $rows = [];
            foreach ($students as $st) {
                $penalty = $this->absenceSummary($st->getId(), $periodId)['penalty'];
                $cells = [];
                $tp = 0.0;
                $tc = 0.0;
                foreach ($columns as $col) {
                    $mc = 0.0;
                    $cf = 0.0;
                    foreach ($col['subjectIds'] as $sidc) {
                        $b = $baremeBySid[$sidc];
                        $avg = $this->gradeRepository->calculateAverageByStudentSubjectAndPeriod($st->getId(), $sidc, $periodId, true);
                        if ($conduiteSid[$sidc]) {
                            $note = max(0.0, ($avg !== null ? $avg * $b / 20 : (float) $b) - $penalty);
                        } elseif ($avg !== null) {
                            $note = $avg * $b / 20;
                        } else {
                            continue;
                        }
                        $mc += $note * $coefBySid[$sidc];
                        $cf += $coefBySid[$sidc];
                    }
                    $cells[$col['code']] = $cf > 0 ? round($mc / $cf, 2) : null;
                    if ($cf > 0) {
                        $tp += $mc;
                        $tc += $cf;
                    }
                }

                $rows[] = [
                    'student' => $st,
                    'cells' => $cells,
                    'total' => $tc > 0 ? round($tp, 2) : null,
                    'coef' => $tc > 0 ? round($tc, 2) : null,
                    'moy' => $tc > 0 ? round($tp / $tc, 2) : null,
                ];
            }

            // Rang (compétition) par moyenne décroissante, avec ex-aequo.
            $moys = [];
            foreach ($rows as $i => $r) {
                if ($r['moy'] !== null) {
                    $moys[$i] = $r['moy'];
                }
            }
            arsort($moys);
            foreach ($rows as $i => &$r) {
                if ($r['moy'] === null) {
                    $r['rang'] = null;
                    $r['ex'] = false;
                    continue;
                }
                $greater = 0;
                $equal = 0;
                foreach ($moys as $v) {
                    if ($v > $r['moy']) {
                        $greater++;
                    } elseif ($v == $r['moy']) {
                        $equal++;
                    }
                }
                $r['rang'] = $greater + 1;
                $r['ex'] = $equal > 1;
            }
            unset($r);

            $pages[] = [
                'classroom' => $classroom,
                'rows' => $rows,
            ];
        }

        return [
            'school' => $school,
            'level' => $level,
            'period' => $period,
            'school_year' => $year,
            'columns' => $columns,
            'pages' => $pages,
            'generated_at' => new \DateTime(),
        ];
    }

    /**
     * Rang (classement par compétition) d'un élève parmi une liste de moyennes,
     * avec indicateur ex-aequo.
     *
     * @param array<int, float> $values [studentId => moyenne]
     * @return array{rank: int, ex: bool, total: int}
     */
    private function rankAmong(array $values, int $studentId): array
    {
        $mine = $values[$studentId] ?? null;
        if ($mine === null) {
            return ['rank' => 0, 'ex' => false, 'total' => \count($values)];
        }

        $greater = 0;
        $equal = 0;
        foreach ($values as $v) {
            if ($v > $mine) {
                $greater++;
            } elseif ($v == $mine) {
                $equal++;
            }
        }

        return ['rank' => $greater + 1, 'ex' => $equal > 1, 'total' => \count($values)];
    }

    /**
     * Calcule l'appréciation générale basée sur la moyenne
     */
    public function getAppreciation(float $average): string
    {
        if ($average >= 18) {
            return 'Excellent';
        } elseif ($average >= 16) {
            return 'Très bien';
        } elseif ($average >= 14) {
            return 'Bien';
        } elseif ($average >= 12) {
            return 'Assez bien';
        } elseif ($average >= 10) {
            return 'Passable';
        } elseif ($average >= 8) {
            return 'Insuffisant';
        } else {
            return 'Très insuffisant';
        }
    }

    /**
     * Calcule les mentions pour le bulletin
     */
    public function getMention(float $average): ?string
    {
        if ($average >= 18) {
            return 'Félicitations du conseil de classe';
        } elseif ($average >= 16) {
            return 'Compliments du conseil de classe';
        } elseif ($average >= 14) {
            return 'Encouragements';
        } elseif ($average >= 12) {
            return 'Tableau d\'honneur';
        }
        
        return null;
    }
}

