<?php

namespace App\Service;

use App\Entity\Student;

/**
 * Calcule la situation de recouvrement des élèves (à jour / en retard) à partir
 * de leurs lignes de frais, avec un filtrage optionnel par catégorie de frais.
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
     *
     * @return array{
     *     rows: list<array{student: Student, due: float, paid: float, balance: float, status: string}>,
     *     totals: array{due: float, paid: float, balance: float, a_jour: int, en_retard: int, count: int}
     * }
     */
    public function build(array $students, ?string $category = null): array
    {
        $rows = [];
        $totals = ['due' => 0.0, 'paid' => 0.0, 'balance' => 0.0, 'a_jour' => 0, 'en_retard' => 0, 'count' => 0];

        foreach ($students as $student) {
            $due = 0.0;
            $paid = 0.0;

            foreach ($student->getStudentFees() as $sf) {
                $fee = $sf->getFee();
                if (!$fee || !$fee->isActive()) {
                    continue;
                }
                if ($category && $fee->getCategory() !== $category) {
                    continue;
                }

                $due += (float) $sf->getAmount();
                $paid += (float) $sf->getPaidAmount();
            }

            $balance = max(0.0, $due - $paid);
            $status = $balance <= self::EPSILON ? 'a_jour' : 'en_retard';

            $rows[] = [
                'student' => $student,
                'due' => $due,
                'paid' => $paid,
                'balance' => $balance,
                'status' => $status,
            ];

            $totals['due'] += $due;
            $totals['paid'] += $paid;
            $totals['balance'] += $balance;
            $totals['count']++;
            $totals[$status]++;
        }

        return ['rows' => $rows, 'totals' => $totals];
    }
}
