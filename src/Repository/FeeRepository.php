<?php

namespace App\Repository;

use App\Entity\Fee;
use App\Entity\School;
use App\Entity\Level;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Fee>
 */
class FeeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Fee::class);
    }

    public function save(Fee $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Fee $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Trouve les frais par établissement
     */
    public function findBySchool(School $school): array
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.school = :school')
            ->andWhere('f.isActive = :active')
            ->setParameter('school', $school)
            ->setParameter('active', true)
            ->orderBy('f.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les frais par niveau
     */
    public function findByLevel(Level $level): array
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.level = :level')
            ->andWhere('f.isActive = :active')
            ->setParameter('level', $level)
            ->setParameter('active', true)
            ->orderBy('f.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les frais par type
     */
    public function findByType(string $type): array
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.type = :type')
            ->andWhere('f.isActive = :active')
            ->setParameter('type', $type)
            ->setParameter('active', true)
            ->orderBy('f.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les frais par fréquence
     */
    public function findByFrequency(string $frequency): array
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.frequency = :frequency')
            ->andWhere('f.isActive = :active')
            ->setParameter('frequency', $frequency)
            ->setParameter('active', true)
            ->orderBy('f.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les frais actifs
     */
    public function findActive(): array
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('f.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte les frais par type
     */
    public function countByType(): array
    {
        return $this->createQueryBuilder('f')
            ->select('f.type, COUNT(f.id) as count')
            ->andWhere('f.isActive = :active')
            ->setParameter('active', true)
            ->groupBy('f.type')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte les frais par fréquence
     */
    public function countByFrequency(): array
    {
        return $this->createQueryBuilder('f')
            ->select('f.frequency, COUNT(f.id) as count')
            ->andWhere('f.isActive = :active')
            ->setParameter('active', true)
            ->groupBy('f.frequency')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte les frais par établissement
     */
    public function countBySchool(): array
    {
        return $this->createQueryBuilder('f')
            ->select('s.name as school_name, COUNT(f.id) as count')
            ->join('f.school', 's')
            ->andWhere('f.isActive = :active')
            ->setParameter('active', true)
            ->groupBy('s.id')
            ->orderBy('count', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les frais avec échéance proche
     */
    public function findWithDueDateNear(int $days = 7): array
    {
        $date = new \DateTime();
        $date->modify("+{$days} days");

        return $this->createQueryBuilder('f')
            ->andWhere('f.dueDate <= :date')
            ->andWhere('f.dueDate >= :now')
            ->andWhere('f.isActive = :active')
            ->setParameter('date', $date)
            ->setParameter('now', new \DateTime())
            ->setParameter('active', true)
            ->orderBy('f.dueDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les frais en retard
     */
    public function findOverdue(): array
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.dueDate < :now')
            ->andWhere('f.isActive = :active')
            ->setParameter('now', new \DateTime())
            ->setParameter('active', true)
            ->orderBy('f.dueDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche les frais par nom ou code
     */
    public function searchByNameOrCode(string $search): array
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.name LIKE :search OR f.code LIKE :search')
            ->andWhere('f.isActive = :active')
            ->setParameter('search', '%' . $search . '%')
            ->setParameter('active', true)
            ->orderBy('f.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Calcule le montant total des frais par établissement
     */
    public function getTotalAmountBySchool(School $school): float
    {
        $result = $this->createQueryBuilder('f')
            ->select('SUM(f.amount) as total')
            ->andWhere('f.school = :school')
            ->andWhere('f.isActive = :active')
            ->setParameter('school', $school)
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();

        return (float) ($result ?? 0);
    }

    /**
     * Trouve les frais par période
     */
    public function findByDateRange(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.startDate <= :endDate')
            ->andWhere('f.endDate >= :startDate OR f.endDate IS NULL')
            ->andWhere('f.isActive = :active')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->setParameter('active', true)
            ->orderBy('f.startDate', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
