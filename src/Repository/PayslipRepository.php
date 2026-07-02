<?php

namespace App\Repository;

use App\Entity\Payslip;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Payslip>
 */
class PayslipRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Payslip::class);
    }

    /**
     * @return Payslip[]
     */
    public function findByEmployee(int $employeeId): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.employee = :employee')
            ->setParameter('employee', $employeeId)
            ->leftJoin('p.period', 'per')->addSelect('per')
            ->orderBy('per.year', 'DESC')
            ->addOrderBy('per.month', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
