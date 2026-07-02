<?php

namespace App\Repository;

use App\Entity\BankReconciliation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BankReconciliation>
 */
class BankReconciliationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BankReconciliation::class);
    }

    /**
     * @return BankReconciliation[]
     */
    public function findBySchool(int $schoolId): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.school = :school')
            ->setParameter('school', $schoolId)
            ->orderBy('r.statementDate', 'DESC')
            ->addOrderBy('r.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findLatest(int $schoolId): ?BankReconciliation
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.school = :school')
            ->setParameter('school', $schoolId)
            ->orderBy('r.statementDate', 'DESC')
            ->addOrderBy('r.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
