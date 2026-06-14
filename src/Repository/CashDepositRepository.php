<?php

namespace App\Repository;

use App\Entity\CashDeposit;
use App\Entity\SchoolGroup;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CashDeposit>
 */
class CashDepositRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CashDeposit::class);
    }

    /**
     * Total des versements approuvés, par établissement d'un groupe.
     * Lien vers l'école via la caisse (cashRegister → school).
     *
     * @return array<int, float> indexé par identifiant d'école
     */
    public function getApprovedTotalsBySchoolForGroup(SchoolGroup $group): array
    {
        $rows = $this->createQueryBuilder('d')
            ->select('s.id AS schoolId', 'SUM(d.amount) AS total')
            ->join('d.cashRegister', 'cr')
            ->join('cr.school', 's')
            ->andWhere('s.schoolGroup = :group')
            ->andWhere('d.status = :approved')
            ->setParameter('group', $group)
            ->setParameter('approved', 'approuvé')
            ->groupBy('s.id')
            ->getQuery()
            ->getResult();

        $totals = [];
        foreach ($rows as $row) {
            $totals[(int) $row['schoolId']] = (float) $row['total'];
        }

        return $totals;
    }

    /**
     * Nombre de versements d'un groupe ayant un statut donné.
     */
    public function countByStatusForGroup(string $status, SchoolGroup $group): int
    {
        return (int) $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->join('d.cashRegister', 'cr')
            ->join('cr.school', 's')
            ->andWhere('s.schoolGroup = :group')
            ->andWhere('d.status = :status')
            ->setParameter('group', $group)
            ->setParameter('status', $status)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Total des versements effectués depuis une caisse.
     */
    public function getTotalByCashRegister(int $cashRegisterId): float
    {
        // Les versements rejetés ne réduisent pas le solde.
        $result = $this->createQueryBuilder('d')
            ->select('SUM(d.amount) as total')
            ->andWhere('d.cashRegister = :id')
            ->andWhere('d.status != :rejected')
            ->setParameter('id', $cashRegisterId)
            ->setParameter('rejected', 'rejeté')
            ->getQuery()
            ->getSingleScalarResult();

        return (float) ($result ?? 0);
    }

    /**
     * @return CashDeposit[]
     */
    public function findByStatus(?string $status = null): array
    {
        $qb = $this->createQueryBuilder('d')->orderBy('d.depositDate', 'DESC');
        if ($status !== null) {
            $qb->andWhere('d.status = :status')->setParameter('status', $status);
        }

        return $qb->getQuery()->getResult();
    }

    public function countByStatus(string $status): int
    {
        return (int) $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->andWhere('d.status = :status')
            ->setParameter('status', $status)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return CashDeposit[]
     */
    public function findByCashRegister(int $cashRegisterId): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.cashRegister = :id')
            ->setParameter('id', $cashRegisterId)
            ->orderBy('d.depositDate', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
