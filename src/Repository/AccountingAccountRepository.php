<?php

namespace App\Repository;

use App\Entity\AccountingAccount;
use App\Entity\School;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AccountingAccount>
 */
class AccountingAccountRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AccountingAccount::class);
    }

    /**
     * @return AccountingAccount[]
     */
    public function findBySchool(int $schoolId, ?string $type = null): array
    {
        $qb = $this->createQueryBuilder('a')
            ->andWhere('a.school = :school')
            ->setParameter('school', $schoolId)
            ->orderBy('a.type', 'ASC')
            ->addOrderBy('a.code', 'ASC');

        if ($type !== null) {
            $qb->andWhere('a.type = :type')->setParameter('type', $type);
        }

        return $qb->getQuery()->getResult();
    }

    public function findOneByCode(School $school, string $code): ?AccountingAccount
    {
        return $this->findOneBy(['school' => $school, 'code' => $code]);
    }

    public function countBySchool(int $schoolId): int
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->andWhere('a.school = :school')
            ->setParameter('school', $schoolId)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
