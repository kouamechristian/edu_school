<?php

namespace App\Repository;

use App\Entity\SalaryComponent;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SalaryComponent>
 */
class SalaryComponentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SalaryComponent::class);
    }

    /**
     * @return SalaryComponent[]
     */
    public function findBySchool(int $schoolId, bool $activeOnly = false): array
    {
        $qb = $this->createQueryBuilder('c')
            ->andWhere('c.school = :school')
            ->setParameter('school', $schoolId)
            ->orderBy('c.direction', 'ASC')
            ->addOrderBy('c.sortOrder', 'ASC')
            ->addOrderBy('c.code', 'ASC');

        if ($activeOnly) {
            $qb->andWhere('c.isActive = true');
        }

        return $qb->getQuery()->getResult();
    }

    public function findOneByCode(int $schoolId, string $code): ?SalaryComponent
    {
        return $this->findOneBy(['school' => $schoolId, 'code' => $code]);
    }

    public function countBySchool(int $schoolId): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.school = :school')
            ->setParameter('school', $schoolId)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
