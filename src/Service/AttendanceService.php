<?php

namespace App\Service;

use App\Entity\Student;
use App\Entity\Period;
use App\Entity\Absence;
use App\Entity\School;
use App\Repository\AbsenceRepository;
use App\Repository\StudentRepository;
use Doctrine\ORM\EntityManagerInterface;

class AttendanceService
{
    public function __construct(
        private AbsenceRepository $absenceRepository,
        private StudentRepository $studentRepository,
        private EntityManagerInterface $entityManager
    ) {
    }

    /**
     * Calcule les statistiques d'assiduité pour un élève sur une période
     */
    public function calculateStudentAttendanceStats(Student $student, Period $period): array
    {
        $totalDays = $this->calculateTotalSchoolDays($period);
        $absences = $this->absenceRepository->findByPeriod($period->getId());
        $studentAbsences = array_filter($absences, function($absence) use ($student) {
            return $absence->getStudent()->getId() === $student->getId();
        });

        $totalAbsences = count($studentAbsences);
        $justifiedAbsences = count(array_filter($studentAbsences, fn($a) => $a->isJustified()));
        $unjustifiedAbsences = count(array_filter($studentAbsences, fn($a) => $a->isUnjustified()));
        $pendingAbsences = count(array_filter($studentAbsences, fn($a) => $a->isPendingJustification()));

        // Calculer le pourcentage d'assiduité
        $attendanceRate = $totalDays > 0 ? (($totalDays - $totalAbsences) / $totalDays) * 100 : 100;
        $attendanceRate = max(0, min(100, $attendanceRate)); // Limiter entre 0 et 100

        return [
            'student' => $student,
            'period' => $period,
            'total_days' => $totalDays,
            'total_absences' => $totalAbsences,
            'justified_absences' => $justifiedAbsences,
            'unjustified_absences' => $unjustifiedAbsences,
            'pending_absences' => $pendingAbsences,
            'attendance_rate' => round($attendanceRate, 2),
            'absences' => $studentAbsences,
        ];
    }

    /**
     * Calcule les statistiques d'assiduité pour une classe sur une période
     */
    public function calculateClassroomAttendanceStats(int $classroomId, Period $period): array
    {
        $students = $this->studentRepository->findByClassroom($classroomId);
        $classStats = [];
        $totalStudents = count($students);
        $totalAbsences = 0;
        $totalJustified = 0;
        $totalUnjustified = 0;
        $totalPending = 0;
        $totalAttendanceRate = 0;

        foreach ($students as $student) {
            $studentStats = $this->calculateStudentAttendanceStats($student, $period);
            $classStats[] = $studentStats;
            
            $totalAbsences += $studentStats['total_absences'];
            $totalJustified += $studentStats['justified_absences'];
            $totalUnjustified += $studentStats['unjustified_absences'];
            $totalPending += $studentStats['pending_absences'];
            $totalAttendanceRate += $studentStats['attendance_rate'];
        }

        $averageAttendanceRate = $totalStudents > 0 ? $totalAttendanceRate / $totalStudents : 0;

        return [
            'classroom_id' => $classroomId,
            'period' => $period,
            'total_students' => $totalStudents,
            'total_absences' => $totalAbsences,
            'total_justified' => $totalJustified,
            'total_unjustified' => $totalUnjustified,
            'total_pending' => $totalPending,
            'average_attendance_rate' => round($averageAttendanceRate, 2),
            'student_stats' => $classStats,
        ];
    }

    /**
     * Calcule les statistiques d'assiduité pour un établissement
     */
    public function calculateSchoolAttendanceStats(School $school, Period $period): array
    {
        $students = $this->studentRepository->findBySchool($school->getId());
        $totalStudents = count($students);
        $totalAbsences = 0;
        $totalJustified = 0;
        $totalUnjustified = 0;
        $totalPending = 0;
        $totalAttendanceRate = 0;

        foreach ($students as $student) {
            $studentStats = $this->calculateStudentAttendanceStats($student, $period);
            
            $totalAbsences += $studentStats['total_absences'];
            $totalJustified += $studentStats['justified_absences'];
            $totalUnjustified += $studentStats['unjustified_absences'];
            $totalPending += $studentStats['pending_absences'];
            $totalAttendanceRate += $studentStats['attendance_rate'];
        }

        $averageAttendanceRate = $totalStudents > 0 ? $totalAttendanceRate / $totalStudents : 0;

        return [
            'school' => $school,
            'period' => $period,
            'total_students' => $totalStudents,
            'total_absences' => $totalAbsences,
            'total_justified' => $totalJustified,
            'total_unjustified' => $totalUnjustified,
            'total_pending' => $totalPending,
            'average_attendance_rate' => round($averageAttendanceRate, 2),
        ];
    }

    /**
     * Génère un rapport d'assiduité détaillé
     */
    public function generateAttendanceReport(int $schoolId, ?int $classroomId = null, ?int $periodId = null, ?\DateTimeInterface $startDate = null, ?\DateTimeInterface $endDate = null): array
    {
        $absences = [];

        if ($periodId) {
            $period = $this->entityManager->getRepository(Period::class)->find($periodId);
            if ($period) {
                $absences = $classroomId 
                    ? $this->absenceRepository->findByPeriodAndClassroom($periodId, $classroomId)
                    : $this->absenceRepository->findByPeriod($periodId);
            }
        } elseif ($startDate && $endDate) {
            $absences = $this->absenceRepository->findByDateRange($startDate, $endDate, $schoolId);
        } else {
            $absences = $this->absenceRepository->findBySchool($schoolId);
        }

        // Grouper par élève
        $studentAbsences = [];
        foreach ($absences as $absence) {
            $studentId = $absence->getStudent()->getId();
            if (!isset($studentAbsences[$studentId])) {
                $studentAbsences[$studentId] = [
                    'student' => $absence->getStudent(),
                    'absences' => [],
                    'total_absences' => 0,
                    'justified_absences' => 0,
                    'unjustified_absences' => 0,
                    'pending_absences' => 0,
                ];
            }
            
            $studentAbsences[$studentId]['absences'][] = $absence;
            $studentAbsences[$studentId]['total_absences']++;
            
            if ($absence->isJustified()) {
                $studentAbsences[$studentId]['justified_absences']++;
            } elseif ($absence->isUnjustified()) {
                $studentAbsences[$studentId]['unjustified_absences']++;
            } else {
                $studentAbsences[$studentId]['pending_absences']++;
            }
        }

        return [
            'student_absences' => $studentAbsences,
            'total_students_with_absences' => count($studentAbsences),
            'total_absences' => count($absences),
            'report_generated_at' => new \DateTime(),
        ];
    }

    /**
     * Trouve les élèves avec un taux d'assiduité critique
     */
    public function findStudentsWithCriticalAttendance(int $schoolId, float $threshold = 75.0): array
    {
        $students = $this->studentRepository->findBySchool($schoolId);
        $criticalStudents = [];

        foreach ($students as $student) {
            // Calculer pour la période actuelle ou la dernière période
            $currentPeriod = $this->getCurrentPeriod($schoolId);
            if ($currentPeriod) {
                $stats = $this->calculateStudentAttendanceStats($student, $currentPeriod);
                
                if ($stats['attendance_rate'] < $threshold) {
                    $criticalStudents[] = $stats;
                }
            }
        }

        // Trier par taux d'assiduité croissant (les plus critiques en premier)
        usort($criticalStudents, function($a, $b) {
            return $a['attendance_rate'] <=> $b['attendance_rate'];
        });

        return $criticalStudents;
    }

    /**
     * Calcule le nombre total de jours d'école dans une période
     */
    private function calculateTotalSchoolDays(Period $period): int
    {
        // Logique simplifiée - en réalité, il faudrait prendre en compte
        // les jours fériés, les vacances, etc.
        $startDate = $period->getStartDate();
        $endDate = $period->getEndDate();
        
        if (!$startDate || !$endDate) {
            return 0;
        }

        $totalDays = 0;
        $currentDate = clone $startDate;
        
        while ($currentDate <= $endDate) {
            // Compter seulement les jours de semaine (lundi = 1, dimanche = 7)
            if ($currentDate->format('N') < 6) { // 1-5 = lundi à vendredi
                $totalDays++;
            }
            $currentDate->modify('+1 day');
        }
        
        return $totalDays;
    }

    /**
     * Obtient la période actuelle pour un établissement
     */
    private function getCurrentPeriod(int $schoolId): ?Period
    {
        // Logique simplifiée - récupérer la période actuelle
        // En réalité, il faudrait vérifier la date actuelle
        $periodRepository = $this->entityManager->getRepository(Period::class);
        return $periodRepository->findCurrentBySchool($schoolId);
    }

    /**
     * Calcule les tendances d'assiduité (amélioration/détérioration)
     */
    public function calculateAttendanceTrends(Student $student): array
    {
        // Comparer les statistiques entre différentes périodes
        // Logique à implémenter selon les besoins
        return [
            'trend' => 'stable', // 'improving', 'declining', 'stable'
            'change_percentage' => 0.0,
            'previous_period_rate' => 0.0,
            'current_period_rate' => 0.0,
        ];
    }
}
