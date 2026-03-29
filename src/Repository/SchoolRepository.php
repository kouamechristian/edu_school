<?php

namespace App\Repository;

use App\Entity\School;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<School>
 */
class SchoolRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, School::class);
    }

    /**
     * Trouver tous les établissements actifs
     */
    public function findActive(): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('s.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouver les établissements par type
     */
    public function findByType(string $type): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.type = :type')
            ->andWhere('s.isActive = :active')
            ->setParameter('type', $type)
            ->setParameter('active', true)
            ->orderBy('s.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Rechercher des établissements par nom
     */
    public function searchByName(string $searchTerm): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.name LIKE :term')
            ->setParameter('term', '%'.$searchTerm.'%')
            ->orderBy('s.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compter le nombre d'établissements par type
     */
    public function countByType(): array
    {
        return $this->createQueryBuilder('s')
            ->select('s.type, COUNT(s.id) as count')
            ->andWhere('s.isActive = :active')
            ->setParameter('active', true)
            ->groupBy('s.type')
            ->getQuery()
            ->getResult();
    }
}

