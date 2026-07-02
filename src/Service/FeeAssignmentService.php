<?php

namespace App\Service;

use App\Entity\Fee;
use App\Entity\Registration;
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

    public function assignFeeToStudent(Fee $fee, Student $student, ?Registration $registration = null): ?StudentFee
    {
        // À défaut d'inscription explicite, rattacher le frais à l'inscription courante
        // de l'élève : sinon un frais affecté à la main (ex. « article ») reste avec
        // registration = null et n'est pas compté dans Registration::getTotalTuition()
        // (le « montant total » de la page de paiement).
        if ($registration === null) {
            $registration = $student->getLatestRegistration();
        }

        // Frais déjà affecté à l'élève (ex. lors de la validation de la préinscription,
        // sans inscription). On ne le duplique pas, MAIS s'il n'est pas encore rattaché à
        // une inscription, on le relie à celle fournie : sinon les frais « disparaîtraient »
        // de l'inscription (Registration::getTotalTuition ne compte que ses propres frais).
        $existing = $this->studentFeeRepository->findOneForStudentAndFee($student->getId(), $fee->getId());
        if ($existing !== null) {
            if ($registration !== null && $existing->getRegistration() === null) {
                $existing->setRegistration($registration);
                $registration->addStudentFee($existing);
            }

            return null;
        }

        $studentFee = new StudentFee();
        $studentFee->setStudent($student);
        $studentFee->setFee($fee);
        $studentFee->setAmount((string) $fee->getFinalAmount());

        // Rattachement à l'inscription (année concernée), le cas échéant.
        if ($registration) {
            $studentFee->setRegistration($registration);
            $registration->addStudentFee($studentFee);
        }

        // Garder les deux côtés de la relation synchronisés en mémoire
        // (utile pour les getters comme Student::getTotalTuition() juste après affectation)
        $student->addStudentFee($studentFee);
        $fee->addStudentFee($studentFee);

        $this->entityManager->persist($studentFee);

        return $studentFee;
    }

    /**
     * Affecte les frais de scolarité applicables au statut de l'inscription
     * (= à l'élève pour l'année concernée). À utiliser lors de l'inscription.
     */
    public function assignScolariteFeesForRegistration(Registration $registration): int
    {
        $student = $registration->getStudent();
        $count = 0;

        foreach ($this->applicableScolariteFees($registration) as $fee) {
            if ($this->assignFeeToStudent($fee, $student, $registration)) {
                $count++;
            }
        }

        if ($count > 0) {
            $this->logger->info('Frais scolarité auto-affectés (inscription)', [
                'student' => $student?->getFullName(),
                'year' => $registration->getSchoolYear()?->getName(),
                'count' => $count,
            ]);
        }

        return $count;
    }

    /**
     * Réadapte les frais de scolarité d'une inscription en fonction de son statut
     * (affecté / non affecté) : ajoute les frais désormais applicables et retire ceux
     * qui ne le sont plus — uniquement s'ils ne sont pas (partiellement) payés.
     *
     * @return array{added: int, removed: int, kept_paid: int}
     */
    public function syncScolariteFeesForRegistration(Registration $registration): array
    {
        $student = $registration->getStudent();
        if (!$student) {
            return ['added' => 0, 'removed' => 0, 'kept_paid' => 0];
        }

        $applicable = $this->applicableScolariteFees($registration);
        $applicableIds = [];
        foreach ($applicable as $fee) {
            $applicableIds[$fee->getId()] = $fee;
        }

        $added = 0;
        $removed = 0;
        $keptPaid = 0;

        // Retrait des frais de scolarité non applicables et non payés.
        foreach ($registration->getStudentFees() as $studentFee) {
            $fee = $studentFee->getFee();
            if (!$fee || $fee->getCategory() !== 'scolarite') {
                continue;
            }
            if (isset($applicableIds[$fee->getId()])) {
                continue; // toujours applicable → on garde
            }

            if ((float) $studentFee->getPaidAmount() > 0) {
                $keptPaid++; // déjà (partiellement) payé → on ne retire pas
                continue;
            }

            $registration->removeStudentFee($studentFee);
            $student->removeStudentFee($studentFee);
            $this->entityManager->remove($studentFee);
            $removed++;
        }

        // Ajout des frais applicables manquants.
        foreach ($applicable as $fee) {
            if ($this->assignFeeToStudent($fee, $student, $registration)) {
                $added++;
            }
        }

        $this->logger->info('Frais scolarité réadaptés (changement de statut)', [
            'student' => $student->getFullName(),
            'status' => $student->getStatus(),
            'added' => $added,
            'removed' => $removed,
            'kept_paid' => $keptPaid,
        ]);

        return ['added' => $added, 'removed' => $removed, 'kept_paid' => $keptPaid];
    }

    /**
     * Affecte les frais de scolarité d'un NIVEAU directement à un élève, sans
     * exiger d'inscription/classe. Utilisé à la validation d'une préinscription en
     * ligne (espace parent) : les frais du niveau souhaité sont liés à l'élève et
     * deviennent immédiatement payables.
     *
     * @return int Nombre de frais effectivement affectés
     */
    public function assignScolariteFeesForStudentByLevel(Student $student, \App\Entity\Level $level): int
    {
        $school = $student->getSchool() ?? $level->getSchool();
        if (!$school) {
            return 0;
        }

        $status = $student->getStatus();
        $fees = [];

        foreach ($this->feeRepository->findScolariteFeesForLevel($school, $level) as $fee) {
            if (in_array($fee->getType(), ['pour_tous', $status], true)) {
                $fees[$fee->getId()] = $fee;
            }
        }
        foreach ($this->feeRepository->findScolariteFeesSchoolWide($school) as $fee) {
            if (in_array($fee->getType(), ['pour_tous', $status], true)) {
                $fees[$fee->getId()] = $fee;
            }
        }

        $count = 0;
        foreach ($fees as $fee) {
            if ($this->assignFeeToStudent($fee, $student, $student->getLatestRegistration())) {
                $count++;
            }
        }

        if ($count > 0) {
            $this->logger->info('Frais scolarité affectés par niveau (validation préinscription parent)', [
                'student' => $student->getFullName(),
                'level' => $level->getName(),
                'count' => $count,
            ]);
        }

        return $count;
    }

    /**
     * Frais de scolarité applicables à une inscription : frais du niveau + frais
     * « établissement », restreints aux types compatibles avec le statut de l'élève
     * (« pour_tous » + le statut affecté/non affecté).
     *
     * @return Fee[]
     */
    private function applicableScolariteFees(Registration $registration): array
    {
        $student = $registration->getStudent();
        $level = $registration->getLevel();
        $school = $student?->getSchool();

        if (!$student || !$level || !$school) {
            return [];
        }

        $status = $student->getStatus();
        $fees = [];

        foreach ($this->feeRepository->findScolariteFeesForLevel($school, $level) as $fee) {
            if (in_array($fee->getType(), ['pour_tous', $status], true)) {
                $fees[$fee->getId()] = $fee;
            }
        }
        foreach ($this->feeRepository->findScolariteFeesSchoolWide($school) as $fee) {
            if (in_array($fee->getType(), ['pour_tous', $status], true)) {
                $fees[$fee->getId()] = $fee;
            }
        }

        return array_values($fees);
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
            // Rattachement à l'inscription courante de l'élève.
            if ($this->assignFeeToStudent($fee, $student, $student->getLatestRegistration())) {
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
