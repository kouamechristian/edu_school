<?php

namespace App\Repository;

use App\Entity\FeeSchedule;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FeeSchedule>
 */
class FeeScheduleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FeeSchedule::class);
    }

    public function findByFee(int $feeId): array
    {
        return $this->createQueryBuilder('fs')
            ->where('fs.fee = :feeId')
            ->setParameter('feeId', $feeId)
            ->orderBy('fs.orderNumber', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
