<?php

namespace App\Repository;

use App\Entity\Cycle;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Cycle>
 */
class CycleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Cycle::class);
    }

    /**
     * Cycles d'un établissement, triés par libellé.
     *
     * @return Cycle[]
     */
    public function findBySchool(?int $schoolId): array
    {
        if (!$schoolId) {
            return [];
        }

        return $this->createQueryBuilder('c')
            ->andWhere('c.school = :school')
            ->setParameter('school', $schoolId)
            ->orderBy('c.libelle', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
