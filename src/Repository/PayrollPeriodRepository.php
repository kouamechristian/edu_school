<?php

namespace App\Repository;

use App\Entity\PayrollPeriod;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PayrollPeriod>
 */
class PayrollPeriodRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PayrollPeriod::class);
    }

    /**
     * @return PayrollPeriod[]
     */
    public function findBySchool(int $schoolId): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.school = :school')
            ->setParameter('school', $schoolId)
            ->orderBy('p.year', 'DESC')
            ->addOrderBy('p.month', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findOneForMonth(int $schoolId, int $year, int $month): ?PayrollPeriod
    {
        return $this->findOneBy(['school' => $schoolId, 'year' => $year, 'month' => $month]);
    }
}
