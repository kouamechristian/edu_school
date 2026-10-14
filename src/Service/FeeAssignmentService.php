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
     * Réadapte les frais de scolarité d'une inscription à sa situation actuelle —
     * après un changement de STATUT (affecté / non affecté) ou de NIVEAU (la classe
     * doit déjà pointer sur le nouveau niveau) :
     *  - les frais désormais applicables sont ajoutés ;
     *  - ceux qui ne le sont plus sont SUPPRIMÉS, même s'ils ont déjà été payés ;
     *  - les paiements déjà faits sur ces anciens frais sont REPORTÉS sur les nouveaux
     *    (l'élève ne paie que la différence) : chaque paiement va au premier nouveau
     *    frais ayant un reste à payer, et est découpé s'il en couvre plusieurs.
     * S'il n'y a aucun nouveau frais sur lequel reporter, les anciens frais déjà
     * (partiellement) payés sont conservés tels quels.
     * Les arriérés antérieurs ne sont jamais touchés. Ne flush pas.
     *
     * @return array{added: int, removed: int, moved_payments: int, moved_amount: float, kept_paid: int, overpaid: float}
     */
    public function syncScolariteFeesForRegistration(Registration $registration): array
    {
        $result = ['added' => 0, 'removed' => 0, 'moved_payments' => 0, 'moved_amount' => 0.0, 'kept_paid' => 0, 'overpaid' => 0.0];
        $student = $registration->getStudent();
        if (!$student) {
            return $result;
        }

        $applicableIds = [];
        $applicable = $this->applicableScolariteFees($registration);
        foreach ($applicable as $fee) {
            $applicableIds[$fee->getId()] = true;
        }

        // Frais de scolarité de l'ancien niveau (plus applicables).
        $obsolete = [];
        foreach ($registration->getStudentFees() as $studentFee) {
            $fee = $studentFee->getFee();
            if (!$fee || $fee->getCategory() !== 'scolarite' || $studentFee->isArriereAnterieur()) {
                continue;
            }
            if (!isset($applicableIds[$fee->getId()])) {
                $obsolete[] = $studentFee;
            }
        }

        // Frais du nouveau niveau.
        $targets = [];
        foreach ($applicable as $fee) {
            if ($studentFee = $this->assignFeeToStudent($fee, $student, $registration)) {
                $targets[] = $studentFee;
                $result['added']++;
            }
        }
        // Puis les frais de scolarité toujours applicables (ex. « pour tous ») : un
        // excédent y est reporté plutôt que de rester en trop-perçu.
        foreach ($registration->getStudentFees() as $studentFee) {
            $fee = $studentFee->getFee();
            if ($fee && isset($applicableIds[$fee->getId()]) && !$studentFee->isArriereAnterieur() && !in_array($studentFee, $targets, true)) {
                $targets[] = $studentFee;
            }
        }

        foreach ($obsolete as $old) {
            $payments = $old->getPayments()->toArray();

            if ($payments !== [] && $targets === []) {
                $result['kept_paid']++; // rien sur quoi reporter : on conserve
                continue;
            }

            usort($payments, static fn ($a, $b) => ($a->getPaymentDate() <=> $b->getPaymentDate()) ?: ($a->getId() <=> $b->getId()));
            foreach ($payments as $payment) {
                $old->removePayment($payment);
                $result['moved_payments']++;

                // Paiement non encaissé (annulé, en attente) : simple rattachement, sans impact sur le payé.
                if ($payment->getStatus() !== 'payé') {
                    $this->attachPayment($payment, $this->firstOpenTarget($targets));
                    continue;
                }

                // Paiement encaissé : réparti sur les nouveaux frais selon leur reste à payer.
                // S'il couvre plusieurs frais, il est découpé en lignes partageant le même
                // numéro de reçu (même modèle que l'imputation multiple au guichet).
                $remaining = (float) $payment->getAmount();
                $line = $payment;
                while (true) {
                    $target = $this->firstOpenTarget($targets);
                    $open = $target->getRemainingAmount();
                    $part = ($open > 0 && $open < $remaining) ? $open : $remaining;

                    $line->setAmount(number_format($part, 2, '.', ''));
                    $this->attachPayment($line, $target);
                    $target->setPaidAmount(number_format((float) $target->getPaidAmount() + $part, 2, '.', ''));
                    $result['moved_amount'] += $part;

                    $remaining = round($remaining - $part, 2);
                    if ($remaining <= 0) {
                        break;
                    }
                    $line = $this->splitPayment($payment);
                }
            }

            $registration->removeStudentFee($old);
            $student->removeStudentFee($old);
            $this->entityManager->remove($old);
            $result['removed']++;
        }

        foreach ($targets as $target) {
            $result['overpaid'] += max(0.0, (float) $target->getPaidAmount() - (float) $target->getAmount());
        }

        $this->logger->info('Frais scolarité réadaptés (changement de statut / niveau)', [
            'student' => $student->getFullName(),
            'status' => $student->getStatus(),
            'level' => $registration->getLevel()?->getName(),
        ] + $result);

        return $result;
    }

    /**
     * Premier frais ayant encore un reste à payer, à défaut le dernier (trop-perçu).
     *
     * @param StudentFee[] $targets
     */
    private function firstOpenTarget(array $targets): StudentFee
    {
        foreach ($targets as $target) {
            if ($target->getRemainingAmount() > 0) {
                return $target;
            }
        }

        return $targets[array_key_last($targets)];
    }

    private function attachPayment(\App\Entity\Payment $payment, StudentFee $target): void
    {
        $target->addPayment($payment);
        $payment->setFee($target->getFee());
    }

    /**
     * Nouvelle ligne d'un encaissement découpé : mêmes informations que l'original,
     * même numéro de reçu (le reçu imprimé reste unique), nouveau numéro de paiement.
     */
    private function splitPayment(\App\Entity\Payment $original): \App\Entity\Payment
    {
        if ($original->getReceiptNumber() === null) {
            // Ancien paiement sans reçu partagé : son numéro devient celui du reçu.
            $original->setReceiptNumber($original->getPaymentNumber());
        }
        if ($original->getReceiptAmount() === null) {
            $original->setReceiptAmount($original->getAmount());
        }

        $line = (new \App\Entity\Payment())
            ->setPaymentNumber('PAY-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3))))
            ->setReceiptNumber($original->getReceiptNumber())
            ->setReceiptAmount($original->getReceiptAmount())
            ->setReference($original->getReference())
            ->setStudent($original->getStudent())
            ->setPaymentDate($original->getPaymentDate())
            ->setPaymentMethod($original->getPaymentMethod())
            ->setStatus($original->getStatus())
            ->setCashRegister($original->getCashRegister())
            ->setRecordedBy($original->getRecordedBy())
            ->setNotes($original->getNotes());
        $this->entityManager->persist($line);

        return $line;
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

        $schoolId = $fee->getSchool()->getId();

        if ($fee->appliesToAllLevels()) {
            $students = $this->studentRepository->findBySchool($schoolId);
        } else {
            // Union (dédupliquée) des élèves de chacun des niveaux ciblés.
            $students = [];
            foreach ($fee->getLevels() as $level) {
                foreach ($this->studentRepository->findActiveBySchoolAndLevel($schoolId, $level->getId()) as $student) {
                    $students[$student->getId()] = $student;
                }
            }
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
