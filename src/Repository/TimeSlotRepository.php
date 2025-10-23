<?php

namespace App\Repository;

use App\Entity\TimeSlot;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TimeSlot>
 */
class TimeSlotRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TimeSlot::class);
    }

    /**
     * Trouver toutes les plages horaires actives
     */
    public function findActive(): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('t.orderNumber', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouver les plages horaires par établissement
     */
    public function findBySchool(?int $schoolId): array
    {
        if (!$schoolId) {
            return [];
        }

        return $this->createQueryBuilder('t')
            ->andWhere('t.school = :school')
            ->andWhere('t.isActive = :active')
            ->setParameter('school', $schoolId)
            ->setParameter('active', true)
            ->orderBy('t.orderNumber', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouver les plages horaires par type
     */
    public function findByType(string $type, int $schoolId): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.school = :school')
            ->andWhere('t.type = :type')
            ->andWhere('t.isActive = :active')
            ->setParameter('school', $schoolId)
            ->setParameter('type', $type)
            ->setParameter('active', true)
            ->orderBy('t.orderNumber', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouver les créneaux de cours
     */
    public function findCourseSlotsForSchool(int $schoolId): array
    {
        return $this->findByType('cours', $schoolId);
    }
}

