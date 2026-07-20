<?php

namespace App\Repository;

use App\Entity\Depense;
use App\Entity\SchoolGroup;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Depense>
 */
class DepenseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Depense::class);
    }

    /**
     * Dépenses d'un établissement, de la plus récente à la plus ancienne.
     *
     * @return Depense[]
     */
    public function findBySchool(int $schoolId): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.school = :school')
            ->setParameter('school', $schoolId)
            ->orderBy('d.depenseDate', 'DESC')
            ->addOrderBy('d.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Dépenses en attente d'approbation du fondateur (toutes écoles), les plus récentes d'abord.
     *
     * @return Depense[]
     */
    public function findPending(): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.status = :status')
            ->setParameter('status', 'en_attente')
            ->orderBy('d.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Dépenses en attente d'approbation pour les établissements d'un groupe.
     *
     * @return Depense[]
     */
    public function findPendingForGroup(SchoolGroup $group): array
    {
        return $this->createQueryBuilder('d')
            ->join('d.school', 's')
            ->andWhere('s.schoolGroup = :group')
            ->andWhere('d.status = :status')
            ->setParameter('group', $group)
            ->setParameter('status', 'en_attente')
            ->orderBy('d.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Nombre de dépenses en attente d'approbation (toutes écoles).
     */
    public function countPending(): int
    {
        return (int) $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->andWhere('d.status = :status')
            ->setParameter('status', 'en_attente')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Nombre de dépenses en attente d'approbation pour un groupe scolaire.
     */
    public function countPendingForGroup(SchoolGroup $group): int
    {
        return (int) $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->join('d.school', 's')
            ->andWhere('s.schoolGroup = :group')
            ->andWhere('d.status = :status')
            ->setParameter('group', $group)
            ->setParameter('status', 'en_attente')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Total des dépenses confirmées d'une caisse (sert au calcul du solde).
     */
    public function getTotalByCashRegister(int $cashRegisterId): float
    {
        return (float) $this->createQueryBuilder('d')
            ->select('COALESCE(SUM(d.amount), 0)')
            ->andWhere('d.cashRegister = :cr')
            ->andWhere('d.status = :status')
            ->setParameter('cr', $cashRegisterId)
            ->setParameter('status', 'confirmée')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Total des dépenses confirmées d'un établissement.
     */
    public function getConfirmedTotalForSchool(int $schoolId): float
    {
        return (float) $this->createQueryBuilder('d')
            ->select('COALESCE(SUM(d.amount), 0)')
            ->andWhere('d.school = :school')
            ->andWhere('d.status = :status')
            ->setParameter('school', $schoolId)
            ->setParameter('status', 'confirmée')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Total des dépenses confirmées par établissement, pour un groupe scolaire.
     *
     * @param int[] $schoolIds
     * @return array<int, float> school id => total
     */
    public function getConfirmedTotalsBySchool(array $schoolIds): array
    {
        if ($schoolIds === []) {
            return [];
        }

        $rows = $this->createQueryBuilder('d')
            ->select('IDENTITY(d.school) AS sid, COALESCE(SUM(d.amount), 0) AS total')
            ->andWhere('d.school IN (:schools)')
            ->andWhere('d.status = :status')
            ->setParameter('schools', $schoolIds)
            ->setParameter('status', 'confirmée')
            ->groupBy('d.school')
            ->getQuery()
            ->getScalarResult();

        $totals = [];
        foreach ($rows as $row) {
            $totals[(int) $row['sid']] = (float) $row['total'];
        }

        return $totals;
    }

    /**
     * Total des dépenses confirmées par établissement, pour un groupe scolaire.
     *
     * @return array<int, float> school id => total
     */
    public function getConfirmedTotalsBySchoolForGroup(SchoolGroup $group): array
    {
        $rows = $this->createQueryBuilder('d')
            ->select('IDENTITY(d.school) AS sid, COALESCE(SUM(d.amount), 0) AS total')
            ->join('d.school', 's')
            ->andWhere('s.schoolGroup = :group')
            ->andWhere('d.status = :status')
            ->setParameter('group', $group)
            ->setParameter('status', 'confirmée')
            ->groupBy('d.school')
            ->getQuery()
            ->getScalarResult();

        $totals = [];
        foreach ($rows as $row) {
            $totals[(int) $row['sid']] = (float) $row['total'];
        }

        return $totals;
    }

    /**
     * Total des dépenses confirmées de tous les établissements d'un groupe.
     */
    public function getConfirmedTotalForGroup(SchoolGroup $group): float
    {
        return (float) $this->createQueryBuilder('d')
            ->select('COALESCE(SUM(d.amount), 0)')
            ->join('d.school', 's')
            ->andWhere('s.schoolGroup = :group')
            ->andWhere('d.status = :status')
            ->setParameter('group', $group)
            ->setParameter('status', 'confirmée')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
