<?php

namespace App\Repository;

use App\Entity\Classroom;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Classroom>
 */
class ClassroomRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Classroom::class);
    }

    /**
     * Trouver toutes les classes actives
     */
    public function findActive(): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouver les classes par établissement
     */
    public function findBySchool(?int $schoolId): array
    {
        if (!$schoolId) {
            return [];
        }

        return $this->createQueryBuilder('c')
            ->andWhere('c.school = :school')
            ->andWhere('c.isActive = :active')
            ->setParameter('school', $schoolId)
            ->setParameter('active', true)
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouver les classes par établissement et année scolaire
     */
    public function findBySchoolAndYear(?int $schoolId, ?int $yearId): array
    {
        $qb = $this->createQueryBuilder('c')
            ->andWhere('c.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('c.name', 'ASC');

        if ($schoolId) {
            $qb->andWhere('c.school = :school')
               ->setParameter('school', $schoolId);
        }

        if ($yearId) {
            $qb->andWhere('c.schoolYear = :year')
               ->setParameter('year', $yearId);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouver les classes par niveau
     */
    public function findByLevel(int $levelId): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.level = :level')
            ->andWhere('c.isActive = :active')
            ->setParameter('level', $levelId)
            ->setParameter('active', true)
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compter les classes par établissement
     */
    public function countBySchool(int $schoolId): int
    {
        return $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.school = :school')
            ->andWhere('c.isActive = :active')
            ->setParameter('school', $schoolId)
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Nombre de classes actives d'un établissement, restreint à l'année si fournie.
     */
    public function countBySchoolAndYear(int $schoolId, ?int $yearId = null): int
    {
        $qb = $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.school = :school')
            ->andWhere('c.isActive = :active')
            ->setParameter('school', $schoolId)
            ->setParameter('active', true);

        if ($yearId !== null) {
            $qb->andWhere('c.schoolYear = :yearId')->setParameter('yearId', $yearId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }
}

