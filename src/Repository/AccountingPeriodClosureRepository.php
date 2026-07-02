<?php

namespace App\Repository;

use App\Entity\AccountingPeriodClosure;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AccountingPeriodClosure>
 */
class AccountingPeriodClosureRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AccountingPeriodClosure::class);
    }

    /**
     * @return AccountingPeriodClosure[]
     */
    public function findBySchool(int $schoolId): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.school = :school')
            ->setParameter('school', $schoolId)
            ->orderBy('c.endDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Dernière clôture (date de fin la plus récente) d'un établissement.
     */
    public function findLatest(int $schoolId): ?AccountingPeriodClosure
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.school = :school')
            ->setParameter('school', $schoolId)
            ->orderBy('c.endDate', 'DESC')
            ->addOrderBy('c.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
