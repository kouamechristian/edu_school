<?php

namespace App\Repository;

use App\Entity\Period;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Period>
 */
class PeriodRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Period::class);
    }

    /**
     * Trouve les périodes par établissement et année scolaire
     */
    public function findBySchoolAndYear(?int $schoolId, ?int $yearId): array
    {
        if (!$schoolId || !$yearId) {
            return [];
        }

        return $this->createQueryBuilder('p')
            ->andWhere('p.school = :school')
            ->andWhere('p.schoolYear = :year')
            ->andWhere('p.isActive = :active')
            ->setParameter('school', $schoolId)
            ->setParameter('year', $yearId)
            ->setParameter('active', true)
            ->orderBy('p.orderNumber', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve la période courante basée sur la date actuelle
     */
    public function findCurrentPeriod(?int $schoolId, ?int $yearId): ?Period
    {
        if (!$schoolId || !$yearId) {
            return null;
        }

        $now = new \DateTime();

        return $this->createQueryBuilder('p')
            ->andWhere('p.school = :school')
            ->andWhere('p.schoolYear = :year')
            ->andWhere('p.startDate <= :now')
            ->andWhere('p.endDate >= :now')
            ->andWhere('p.isActive = :active')
            ->setParameter('school', $schoolId)
            ->setParameter('year', $yearId)
            ->setParameter('now', $now)
            ->setParameter('active', true)
            ->orderBy('p.orderNumber', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouve la période courante d'un établissement basée sur la date actuelle
     * (sans filtrer sur l'année scolaire)
     */
    public function findCurrentBySchool(?int $schoolId): ?Period
    {
        if (!$schoolId) {
            return null;
        }

        $now = new \DateTime();

        return $this->createQueryBuilder('p')
            ->andWhere('p.school = :school')
            ->andWhere('p.startDate <= :now')
            ->andWhere('p.endDate >= :now')
            ->andWhere('p.isActive = :active')
            ->setParameter('school', $schoolId)
            ->setParameter('now', $now)
            ->setParameter('active', true)
            ->orderBy('p.orderNumber', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
