<?php

namespace App\Service;

use App\Entity\Student;

/**
 * Frais restant dus par un élève pour une année, prêts pour l'imputation d'un paiement.
 *
 * Ordre : arriérés antérieurs d'abord (à solder en priorité), puis par prochaine
 * échéance impayée — c'est aussi l'ordre de la répartition automatique, au guichet
 * comme à l'import de paiements.
 */
class OutstandingFees
{
    /**
     * @return list<array{
     *     student_fee: \App\Entity\StudentFee, id: int, name: string, amount: float, paid: float,
     *     remaining: float, is_arriere: bool, annee_origine: ?string, next_due: string,
     *     schedules: list<array{order: int, due: ?string, amount: float, remaining: float}>
     * }>
     */
    public function forStudent(Student $student, ?int $schoolYearId): array
    {
        $inscription = $student->getScolariteRegistration($schoolYearId);
        $studentFees = $inscription ? $inscription->getStudentFees() : $student->getStudentFees();

        $list = [];
        foreach ($studentFees as $studentFee) {
            $fee = $studentFee->getFee();
            if (!$fee || !$fee->isActive() || $studentFee->getRemainingAmount() <= 0) {
                continue;
            }

            // Échéances du frais avec le reste par échéance (imputation en cascade du
            // déjà-payé, les plus anciennes d'abord).
            $schedules = $fee->getSchedules()->toArray();
            usort($schedules, static fn ($a, $b) => ($a->getOrderNumber() ?? 0) <=> ($b->getOrderNumber() ?? 0));
            $paidLeft = (float) $studentFee->getPaidAmount();
            $scheduleList = [];
            // Un frais sans échéancier (ou une échéance sans date) est dû immédiatement.
            $nextDue = null;
            foreach ($schedules as $i => $schedule) {
                $amt = (float) $schedule->getAmount();
                $imp = min($paidLeft, $amt);
                $paidLeft -= $imp;
                $remaining = round($amt - $imp, 2);
                if ($remaining > 0 && $nextDue === null) {
                    $nextDue = $schedule->getDueDate()?->format('Y-m-d') ?? '0000-00-00';
                }
                $scheduleList[] = [
                    'order' => $schedule->getOrderNumber() ?? ($i + 1),
                    'due' => $schedule->getDueDate()?->format('d/m/Y'),
                    'amount' => $amt,
                    'remaining' => $remaining,
                ];
            }

            $list[] = [
                'student_fee' => $studentFee,
                'id' => $studentFee->getId(),
                'name' => (string) $fee->getName(),
                'amount' => (float) $studentFee->getAmount(),
                'paid' => (float) $studentFee->getPaidAmount(),
                'remaining' => $studentFee->getRemainingAmount(),
                'is_arriere' => $studentFee->isArriereAnterieur(),
                'annee_origine' => $studentFee->getAnneeOrigine(),
                'next_due' => $nextDue ?? '0000-00-00',
                'schedules' => $scheduleList,
            ];
        }

        usort($list, static fn (array $a, array $b): int => ($b['is_arriere'] <=> $a['is_arriere'])
            ?: ($a['next_due'] <=> $b['next_due'])
            ?: strcmp($a['name'], $b['name']));

        return $list;
    }
}
