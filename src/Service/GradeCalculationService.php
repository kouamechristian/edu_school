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

        foreach ($subjects as $subject) {
            $average = $this->gradeRepository->calculateAverageByStudentSubjectAndPeriod(
                $student->getId(),
                $subject->getId(),
                $period->getId(),
                true
            );

            if ($average !== null) {
                $subjectCoef = (float) $subject->getCoefficient();
                
                $results['subjects'][] = [
                    'subject' => $subject,
                    'average' => $average,
                    'coefficient' => $subjectCoef,
                    'weighted_average' => $average * $subjectCoef,
                ];

                $totalPoints += $average * $subjectCoef;
                $totalCoefficient += $subjectCoef;
            }
        }

        if ($totalCoefficient > 0) {
            $results['general_average'] = round($totalPoints / $totalCoefficient, 2);
            $results['total_coefficient'] = $totalCoefficient;
        }

        return $results;
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

        // Pré-calcul des moyennes par matière (pour le rang) et générales.
        $subjectAverages = []; // [subjectId][studentId] => avg
        $generalAverages = []; // [studentId] => avg
        foreach ($classStudents as $cs) {
            $generalAverages[$cs->getId()] = $this->gradeRepository->calculateGeneralAverageByStudentAndPeriod($cs->getId(), $periodId, true);
            foreach ($subjects as $subject) {
                $avg = $this->gradeRepository->calculateAverageByStudentSubjectAndPeriod($cs->getId(), $subject->getId(), $periodId, true);
                if ($avg !== null) {
                    $subjectAverages[$subject->getId()][$cs->getId()] = $avg;
                }
            }
        }

        // Lignes du bulletin pour l'élève cible.
        $rows = [];
        $totalCoef = 0.0;
        $totalMoyCoef = 0.0;
        foreach ($subjects as $subject) {
            $sid = $subject->getId();
            $avg = $subjectAverages[$sid][$student->getId()] ?? null;

            if ($avg === null) {
                $rows[] = ['name' => $subject->getName(), 'nc' => true, 'teacher' => $teacherBySubject[$sid] ?? null];
                continue;
            }

            $coef = (float) $subject->getCoefficient();
            $moyCoef = round($avg * $coef, 2);
            $rankInfo = $this->rankAmong($subjectAverages[$sid] ?? [], $student->getId());

            $rows[] = [
                'name' => $subject->getName(),
                'nc' => false,
                'moy' => $avg,
                'coef' => $coef,
                'moy_coef' => $moyCoef,
                'rank' => $rankInfo['rank'],
                'ex' => $rankInfo['ex'],
                'teacher' => $teacherBySubject[$sid] ?? null,
                'appreciation' => $this->getAppreciation($avg),
            ];

            $totalCoef += $coef;
            $totalMoyCoef += $moyCoef;
        }

        $generalAverage = $totalCoef > 0 ? round($totalMoyCoef / $totalCoef, 2) : null;

        // Rang général + statistiques de classe.
        $validGenerals = array_filter($generalAverages, static fn ($v) => $v !== null);
        $generalRank = $generalAverage !== null ? $this->rankAmong($validGenerals, $student->getId()) : ['rank' => null, 'ex' => false];
        $classMoy = \count($validGenerals) > 0 ? round(array_sum($validGenerals) / \count($validGenerals), 2) : null;

        return [
            'student' => $student,
            'period' => $period,
            'school_year' => $period->getSchoolYear(),
            'effectif' => $effectif,
            'rows' => $rows,
            'total_coef' => $totalCoef,
            'total_moy_coef' => round($totalMoyCoef, 2),
            'general_average' => $generalAverage,
            'general_rank' => $generalRank['rank'],
            'general_rank_ex' => $generalRank['ex'],
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

