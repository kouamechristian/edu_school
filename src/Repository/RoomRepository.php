<?php

namespace App\Repository;

use App\Entity\Room;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Room>
 */
class RoomRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Room::class);
    }

    /**
     * Trouve les salles par établissement
     */
    public function findBySchool(?int $schoolId): array
    {
        if (!$schoolId) {
            return [];
        }

        return $this->createQueryBuilder('r')
            ->andWhere('r.isActive = :active')
            ->andWhere('r.school = :school')
            ->setParameter('active', true)
            ->setParameter('school', $schoolId)
            ->orderBy('r.code', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les salles par établissement et type
     */
    public function findBySchoolAndType(?int $schoolId, ?string $type): array
    {
        if (!$schoolId) {
            return [];
        }

        $qb = $this->createQueryBuilder('r')
            ->andWhere('r.isActive = :active')
            ->andWhere('r.school = :school')
            ->setParameter('active', true)
            ->setParameter('school', $schoolId);

        if ($type) {
            $qb->andWhere('r.type = :type')
               ->setParameter('type', $type);
        }

        return $qb->orderBy('r.code', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche les salles par nom ou code
     */
    public function searchByNameOrCode(string $searchTerm, ?int $schoolId): array
    {
        $qb = $this->createQueryBuilder('r')
            ->andWhere('r.isActive = :active')
            ->andWhere('r.name LIKE :search OR r.code LIKE :search')
            ->setParameter('active', true)
            ->setParameter('search', '%' . $searchTerm . '%');

        if ($schoolId) {
            $qb->andWhere('r.school = :school')
               ->setParameter('school', $schoolId);
        }

        return $qb->orderBy('r.code', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte les salles par établissement
     */
    public function countBySchool(?int $schoolId): int
    {
        if (!$schoolId) {
            return 0;
        }

        return $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.isActive = :active')
            ->andWhere('r.school = :school')
            ->setParameter('active', true)
            ->setParameter('school', $schoolId)
            ->getQuery()
            ->getSingleScalarResult();
    }
}

