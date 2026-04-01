<?php

namespace App\Repository;

use App\Entity\CashRegister;
use App\Entity\School;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CashRegister>
 */
class CashRegisterRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CashRegister::class);
    }

    public function findOpenForCashier(School $school, User $cashier): ?CashRegister
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.school = :school')
            ->andWhere('c.cashier = :cashier')
            ->andWhere('c.status = :status')
            ->setParameter('school', $school)
            ->setParameter('cashier', $cashier)
            ->setParameter('status', 'open')
            ->orderBy('c.openedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}

