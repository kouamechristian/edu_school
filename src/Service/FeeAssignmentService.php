<?php

namespace App\Service;

use App\Entity\Fee;
use App\Entity\Student;
use App\Entity\StudentFee;
use App\Repository\FeeRepository;
use App\Repository\StudentFeeRepository;
use App\Repository\StudentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class FeeAssignmentService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private StudentFeeRepository $studentFeeRepository,
        private StudentRepository $studentRepository,
        private FeeRepository $feeRepository,
        private LoggerInterface $logger
    ) {
    }

    public function assignFeeToStudent(Fee $fee, Student $student): ?StudentFee
    {
        if ($this->studentFeeRepository->existsForStudentAndFee($student->getId(), $fee->getId())) {
            return null;
        }

        $studentFee = new StudentFee();
        $studentFee->setStudent($student);
        $studentFee->setFee($fee);
        $studentFee->setAmount((string) $fee->getFinalAmount());

        // Garder les deux côtés de la relation synchronisés en mémoire
        // (utile pour les getters comme Student::getTotalTuition() juste après affectation)
        $student->addStudentFee($studentFee);
        $fee->addStudentFee($studentFee);

        $this->entityManager->persist($studentFee);

        return $studentFee;
    }

    /**
     * Affecte automatiquement les frais de catégorie "scolarite" du niveau à un élève.
     * Appelé lors de l'inscription d'un élève.
     */
    public function assignScolariteFeesForStudent(Student $student): int
    {
        $level = $student->getLevel();
        $school = $student->getSchool();

        if (!$level || !$school) {
            return 0;
        }

        $count = 0;

        $levelFees = $this->feeRepository->findScolariteFeesForLevel($school, $level);
        foreach ($levelFees as $fee) {
            if ($this->assignFeeToStudent($fee, $student)) {
                $count++;
            }
        }

        $schoolWideFees = $this->feeRepository->findScolariteFeesSchoolWide($school);
        foreach ($schoolWideFees as $fee) {
            if ($this->assignFeeToStudent($fee, $student)) {
                $count++;
            }
        }

        if ($count > 0) {
            $this->logger->info('Frais scolarité auto-affectés', [
                'student' => $student->getFullName(),
                'count' => $count,
            ]);
        }

        return $count;
    }

    /**
     * Quand un frais de catégorie "scolarite" est créé, l'affecter à tous les élèves du niveau.
     */
    public function assignScolariteFeeToAllStudents(Fee $fee): int
    {
        if ($fee->getCategory() !== 'scolarite') {
            return 0;
        }

        $students = [];

        if ($fee->getLevel()) {
            $students = $this->studentRepository->findActiveBySchoolAndLevel(
                $fee->getSchool()->getId(),
                $fee->getLevel()->getId()
            );
        } else {
            $students = $this->studentRepository->findBySchool($fee->getSchool()->getId());
        }

        $count = 0;
        foreach ($students as $student) {
            if ($this->assignFeeToStudent($fee, $student)) {
                $count++;
            }
        }

        if ($count > 0) {
            $this->logger->info('Frais scolarité affecté en masse', [
                'fee' => $fee->getName(),
                'students_count' => $count,
            ]);
        }

        return $count;
    }

    /**
     * Affecte manuellement un frais à des élèves spécifiques.
     */
    public function assignFeeToStudents(Fee $fee, array $students): int
    {
        $count = 0;
        foreach ($students as $student) {
            if ($this->assignFeeToStudent($fee, $student)) {
                $count++;
            }
        }

        return $count;
    }

    public function unassignFeeFromStudent(StudentFee $studentFee): void
    {
        if ($studentFee->getStatus() !== 'non_paye') {
            throw new \LogicException('Impossible de retirer un frais déjà payé ou partiellement payé.');
        }

        $this->entityManager->remove($studentFee);
    }

    public function getStudentTuitionSummary(Student $student): array
    {
        $studentFees = $this->studentFeeRepository->findByStudent($student->getId());
        $totalAmount = 0;
        $totalPaid = 0;

        foreach ($studentFees as $sf) {
            $totalAmount += (float) $sf->getAmount();
            $totalPaid += (float) $sf->getPaidAmount();
        }

        return [
            'student_fees' => $studentFees,
            'total_amount' => $totalAmount,
            'total_paid' => $totalPaid,
            'remaining' => max(0, $totalAmount - $totalPaid),
            'payment_percentage' => $totalAmount > 0 ? round(($totalPaid / $totalAmount) * 100, 2) : 0,
        ];
    }
}
