<?php

namespace App\Service;

use App\Entity\Student;
use App\Entity\StudentFee;

/**
 * Calcule la situation de recouvrement des élèves (à jour / en retard) à partir
 * de leurs lignes de frais, avec un filtrage optionnel par catégorie de frais.
 *
 * Le statut de relance est calculé en fonction des échéanciers de frais
 * (FeeSchedule) : seules les échéances dont la date est dépassée et non couvertes
 * par les paiements constituent un « montant échu impayé » justifiant une relance.
 */
class RecouvrementService
{
    private const EPSILON = 0.0001;

    /**
     * Construit les lignes de recouvrement pour une liste d'élèves.
     *
     * @param Student[]   $students
     * @param string|null $category  Catégorie de frais à considérer (scolarite, article,
     *                               autre_frais) ou null pour toutes.
     * @param int|null    $yearId    Année scolaire : ne considère que les frais rattachés
     *                               à l'inscription de cette année (null = toutes années).
     *
     * @return array{
     *     rows: list<array{student: Student, due: float, paid: float, balance: float,
     *                      status: string, overdue: float, overdue_count: int,
     *                      oldest_due_date: ?\DateTimeInterface, days_overdue: int,
     *                      next_due_date: ?\DateTimeInterface, next_due_amount: float}>,
     *     totals: array{due: float, paid: float, balance: float, a_jour: int,
     *                   en_retard: int, count: int, overdue: float}
     * }
     */
    public function build(array $students, ?string $category = null, ?int $yearId = null): array
    {
        $today = new \DateTime('today');

        $rows = [];
        $totals = [
            'due' => 0.0, 'paid' => 0.0, 'balance' => 0.0,
            'a_jour' => 0, 'en_retard' => 0, 'count' => 0, 'overdue' => 0.0,
        ];

        foreach ($students as $student) {
            $due = 0.0;
            $paid = 0.0;
            $overdue = 0.0;
            $overdueCount = 0;
            $oldestDueDate = null;
            $nextDueDate = null;
            $nextDueAmount = 0.0;

            foreach ($student->getStudentFees() as $sf) {
                $fee = $sf->getFee();
                if (!$fee || !$fee->isActive()) {
                    continue;
                }
                if ($category && $fee->getCategory() !== $category) {
                    continue;
                }
                if (!$this->feeMatchesYear($sf, $yearId)) {
                    continue;
                }

                $due += (float) $sf->getAmount();
                $paid += (float) $sf->getPaidAmount();

                $schedule = $this->computeScheduleStatus($sf, $today);
                $overdue += $schedule['overdue'];
                $overdueCount += $schedule['overdue_count'];

                if ($schedule['oldest_due_date'] !== null
                    && ($oldestDueDate === null || $schedule['oldest_due_date'] < $oldestDueDate)) {
                    $oldestDueDate = $schedule['oldest_due_date'];
                }
                if ($schedule['next_due_date'] !== null
                    && ($nextDueDate === null || $schedule['next_due_date'] < $nextDueDate)) {
                    $nextDueDate = $schedule['next_due_date'];
                    $nextDueAmount = $schedule['next_due_amount'];
                }
            }

            $balance = max(0.0, $due - $paid);
            // Statut de relance basé sur l'échéancier : en retard seulement si un
            // montant est échu et non payé.
            $status = $overdue > self::EPSILON ? 'en_retard' : 'a_jour';

            $daysOverdue = 0;
            if ($oldestDueDate !== null) {
                $daysOverdue = (int) $today->diff($oldestDueDate)->days;
            }

            $rows[] = [
                'student' => $student,
                'due' => $due,
                'paid' => $paid,
                'balance' => $balance,
                'status' => $status,
                'overdue' => $overdue,
                'overdue_count' => $overdueCount,
                'oldest_due_date' => $oldestDueDate,
                'days_overdue' => $daysOverdue,
                'next_due_date' => $nextDueDate,
                'next_due_amount' => $nextDueAmount,
            ];

            $totals['due'] += $due;
            $totals['paid'] += $paid;
            $totals['balance'] += $balance;
            $totals['overdue'] += $overdue;
            $totals['count']++;
            $totals[$status]++;
        }

        return ['rows' => $rows, 'totals' => $totals];
    }

    /**
     * Situation de relance détaillée d'un seul élève, basée sur les échéanciers.
     *
     * @return array{
     *     due: float, paid: float, balance: float, overdue: float, overdue_count: int,
     *     oldest_due_date: ?\DateTimeInterface, days_overdue: int,
     *     next_due_date: ?\DateTimeInterface, next_due_amount: float,
     *     overdue_details: list<array{fee: string, order_number: ?int,
     *         due_date: \DateTimeInterface, amount: float, days_overdue: int}>
     * }
     */
    public function buildForStudent(Student $student, ?string $category = null, ?int $yearId = null): array
    {
        $today = new \DateTime('today');

        $due = 0.0;
        $paid = 0.0;
        $overdue = 0.0;
        $overdueCount = 0;
        $oldestDueDate = null;
        $nextDueDate = null;
        $nextDueAmount = 0.0;
        $details = [];

        foreach ($student->getStudentFees() as $sf) {
            $fee = $sf->getFee();
            if (!$fee || !$fee->isActive()) {
                continue;
            }
            if ($category && $fee->getCategory() !== $category) {
                continue;
            }
            if (!$this->feeMatchesYear($sf, $yearId)) {
                continue;
            }

            $due += (float) $sf->getAmount();
            $paid += (float) $sf->getPaidAmount();

            $schedule = $this->computeScheduleStatus($sf, $today, true);
            $overdue += $schedule['overdue'];
            $overdueCount += $schedule['overdue_count'];

            foreach ($schedule['details'] as $line) {
                $details[] = [
                    'fee' => $fee->getName() ?? '',
                    'order_number' => $line['order_number'],
                    'due_date' => $line['due_date'],
                    'amount' => $line['amount'],
                    'days_overdue' => (int) $today->diff($line['due_date'])->days,
                ];
            }

            if ($schedule['oldest_due_date'] !== null
                && ($oldestDueDate === null || $schedule['oldest_due_date'] < $oldestDueDate)) {
                $oldestDueDate = $schedule['oldest_due_date'];
            }
            if ($schedule['next_due_date'] !== null
                && ($nextDueDate === null || $schedule['next_due_date'] < $nextDueDate)) {
                $nextDueDate = $schedule['next_due_date'];
                $nextDueAmount = $schedule['next_due_amount'];
            }
        }

        usort($details, static fn (array $a, array $b) => $a['due_date'] <=> $b['due_date']);

        return [
            'due' => $due,
            'paid' => $paid,
            'balance' => max(0.0, $due - $paid),
            'overdue' => $overdue,
            'overdue_count' => $overdueCount,
            'oldest_due_date' => $oldestDueDate,
            'days_overdue' => $oldestDueDate !== null ? (int) $today->diff($oldestDueDate)->days : 0,
            'next_due_date' => $nextDueDate,
            'next_due_amount' => $nextDueAmount,
            'overdue_details' => $details,
        ];
    }

    /**
     * Indique si une ligne de frais relève de l'année demandée (via son inscription).
     * Sans année demandée, toutes les lignes sont retenues.
     */
    private function feeMatchesYear(StudentFee $sf, ?int $yearId): bool
    {
        if ($yearId === null) {
            return true;
        }

        return $sf->getRegistration()?->getSchoolYear()?->getId() === $yearId;
    }

    /**
     * Calcule, pour une ligne de frais élève, le montant échu impayé en fonction
     * de l'échéancier du frais. Les paiements couvrent les échéances dans l'ordre
     * (de la plus ancienne à la plus récente).
     *
     * Si le frais n'a pas d'échéancier, la totalité est considérée comme due
     * immédiatement (comportement historique).
     *
     * @return array{overdue: float, overdue_count: int,
     *               oldest_due_date: ?\DateTimeInterface,
     *               next_due_date: ?\DateTimeInterface, next_due_amount: float,
     *               details: list<array{order_number: ?int, due_date: \DateTimeInterface, amount: float}>}
     */
    private function computeScheduleStatus(StudentFee $sf, \DateTimeInterface $today, bool $withDetails = false): array
    {
        $studentAmount = (float) $sf->getAmount();
        $paid = (float) $sf->getPaidAmount();

        $result = [
            'overdue' => 0.0,
            'overdue_count' => 0,
            'oldest_due_date' => null,
            'next_due_date' => null,
            'next_due_amount' => 0.0,
            'details' => [],
        ];

        $fee = $sf->getFee();
        $schedules = $fee ? $fee->getSchedules() : null;

        // Pas d'échéancier : tout est dû immédiatement.
        if (!$schedules || $schedules->count() === 0) {
            $unpaid = max(0.0, $studentAmount - $paid);
            if ($unpaid > self::EPSILON) {
                $dueDate = \DateTime::createFromInterface($today);
                $result['overdue'] = $unpaid;
                $result['overdue_count'] = 1;
                $result['oldest_due_date'] = $dueDate;
                if ($withDetails) {
                    $result['details'][] = ['order_number' => null, 'due_date' => $dueDate, 'amount' => $unpaid];
                }
            }

            return $result;
        }

        // Le montant facturé à l'élève peut différer du total de l'échéancier
        // (frais affecté, remise…) : on proratise les échéances en conséquence.
        $scheduleTotal = $fee->getSchedulesTotalAmount();
        $ratio = $scheduleTotal > 0 ? $studentAmount / $scheduleTotal : 1.0;

        $remainingPaid = $paid;

        foreach ($schedules as $schedule) {
            $instalment = (float) $schedule->getAmount() * $ratio;
            if ($instalment <= self::EPSILON) {
                continue;
            }

            // Les paiements couvrent d'abord les échéances les plus anciennes.
            $coveredHere = min($remainingPaid, $instalment);
            $remainingPaid -= $coveredHere;
            $unpaidHere = $instalment - $coveredHere;

            if ($unpaidHere <= self::EPSILON) {
                continue;
            }

            $dueDate = $schedule->getDueDate();

            if ($dueDate < $today) {
                // Échéance dépassée et non couverte → montant échu impayé.
                $result['overdue'] += $unpaidHere;
                $result['overdue_count']++;
                if ($result['oldest_due_date'] === null || $dueDate < $result['oldest_due_date']) {
                    $result['oldest_due_date'] = $dueDate;
                }
                if ($withDetails) {
                    $result['details'][] = [
                        'order_number' => $schedule->getOrderNumber(),
                        'due_date' => $dueDate,
                        'amount' => $unpaidHere,
                    ];
                }
            } elseif ($result['next_due_date'] === null || $dueDate < $result['next_due_date']) {
                // Prochaine échéance à venir non encore couverte.
                $result['next_due_date'] = $dueDate;
                $result['next_due_amount'] = $unpaidHere;
            }
        }

        return $result;
    }
}
