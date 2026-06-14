<?php

namespace App\Repository;

use App\Entity\CashRegister;
use App\Entity\School;
use App\Entity\SchoolGroup;
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

    /**
     * Caisse « en ligne » par défaut de l'établissement (paiements mobile/passerelle).
     */
    public function findOnlineForSchool(School $school): ?CashRegister
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.school = :school')
            ->andWhere('c.isOnline = :online')
            ->setParameter('school', $school)
            ->setParameter('online', true)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
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

    /**
     * Nombre de caisses d'un groupe filtrées sur un champ booléen
     * (ex. isValidated = false → en attente de validation).
     */
    public function countByBooleanForGroup(string $field, bool $value, SchoolGroup $group): int
    {
        if (!\in_array($field, ['isValidated', 'expenseAuthorized'], true)) {
            throw new \InvalidArgumentException(sprintf('Champ non autorisé : %s', $field));
        }

        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->join('c.school', 's')
            ->andWhere('s.schoolGroup = :group')
            ->andWhere(sprintf('c.%s = :value', $field))
            ->setParameter('group', $group)
            ->setParameter('value', $value)
            ->getQuery()
            ->getSingleScalarResult();
    }
}

