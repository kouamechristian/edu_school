<?php

namespace App\Service;

use App\Entity\Period;
use App\Entity\User;
use App\Repository\GradeRepository;
use App\Repository\SubjectRepository;
use Doctrine\ORM\EntityManagerInterface;

class GradeCalculationService
{
    public function __construct(
        private GradeRepository $gradeRepository,
        private SubjectRepository $subjectRepository,
        private EntityManagerInterface $entityManager
    ) {
    }

    /**
     * Calcule toutes les moyennes d'un élève pour une période
     */
    public function calculateStudentAveragesForPeriod(User $student, Period $period): array
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
                $period->getId()
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
    public function calculateClassRanking(User $student, Period $period, int $classroomId): array
    {
        // Récupérer tous les élèves de la classe
        $students = $this->entityManager->getRepository(User::class)
            ->findByClassroom($classroomId);

        $averages = [];

        foreach ($students as $classStudent) {
            $average = $this->gradeRepository->calculateGeneralAverageByStudentAndPeriod(
                $classStudent->getId(),
                $period->getId()
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
    public function getStudentGradesForPeriod(User $student, Period $period): array
    {
        return $this->gradeRepository->findByStudentAndPeriod(
            $student->getId(),
            $period->getId()
        );
    }

    /**
     * Génère les données complètes pour un bulletin
     */
    public function generateBulletinData(User $student, Period $period, int $classroomId): array
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

