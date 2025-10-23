<?php

namespace App\Repository;

use App\Entity\SchoolGroup;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SchoolGroup>
 */
class SchoolGroupRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SchoolGroup::class);
    }

    /**
     * Trouver tous les groupes actifs
     */
    public function findActive(): array
    {
        return $this->createQueryBuilder('sg')
            ->andWhere('sg.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('sg.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}

