<?php

namespace App\Repository;

use App\Entity\Subject;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Subject>
 */
class SubjectRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Subject::class);
    }

    /**
     * Trouver toutes les matières actives
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
     * Trouver les matières par établissement
     * Retourne UNIQUEMENT les matières liées à l'établissement spécifié
     */
    public function findBySchool(?int $schoolId): array
    {
        if (!$schoolId) {
            return [];
        }

        return $this->createQueryBuilder('s')
            ->andWhere('s.school = :school')
            ->andWhere('s.isActive = :active')
            ->setParameter('school', $schoolId)
            ->setParameter('active', true)
            ->orderBy('s.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouver les matières par niveau
     */
    public function findByLevel(int $levelId): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.level = :level')
            ->andWhere('s.isActive = :active')
            ->setParameter('level', $levelId)
            ->setParameter('active', true)
            ->orderBy('s.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouver les matières par type
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
     * Trouver les matières par établissement et niveau
     */
    public function findBySchoolAndLevel(?int $schoolId, ?int $levelId): array
    {
        $qb = $this->createQueryBuilder('s')
            ->andWhere('s.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('s.name', 'ASC');

        if ($schoolId) {
            $qb->andWhere('s.school = :school')
               ->setParameter('school', $schoolId);
        }

        if ($levelId) {
            $qb->andWhere('s.level = :level')
               ->setParameter('level', $levelId);
        }

        return $qb->getQuery()->getResult();
    }
}

