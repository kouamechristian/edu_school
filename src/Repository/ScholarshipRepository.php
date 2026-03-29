<?php

namespace App\Repository;

use App\Entity\Scholarship;
use App\Entity\Student;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Scholarship>
 */
class ScholarshipRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Scholarship::class);
    }

    public function save(Scholarship $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Scholarship $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Trouve les bourses par élève
     */
    public function findByStudent(Student $student): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.student = :student')
            ->setParameter('student', $student)
            ->orderBy('s.startDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les bourses par statut
     */
    public function findByStatus(string $status): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.status = :status')
            ->setParameter('status', $status)
            ->orderBy('s.startDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les bourses par type
     */
    public function findByType(string $type): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.type = :type')
            ->setParameter('type', $type)
            ->orderBy('s.startDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les bourses actives
     */
    public function findActive(): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.status = :status')
            ->andWhere('s.startDate <= :now')
            ->andWhere('s.endDate >= :now OR s.endDate IS NULL')
            ->setParameter('status', 'active')
            ->setParameter('now', new \DateTime())
            ->orderBy('s.startDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les bourses expirées
     */
    public function findExpired(): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.endDate < :now')
            ->andWhere('s.status = :status')
            ->setParameter('now', new \DateTime())
            ->setParameter('status', 'active')
            ->orderBy('s.endDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les bourses par sponsor
     */
    public function findBySponsor(string $sponsor): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.sponsor LIKE :sponsor')
            ->setParameter('sponsor', '%' . $sponsor . '%')
            ->orderBy('s.startDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les bourses par période
     */
    public function findByDateRange(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.startDate <= :endDate')
            ->andWhere('s.endDate >= :startDate OR s.endDate IS NULL')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('s.startDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les bourses qui commencent bientôt
     */
    public function findStartingSoon(int $days = 30): array
    {
        $date = new \DateTime();
        $date->modify("+{$days} days");

        return $this->createQueryBuilder('s')
            ->andWhere('s.startDate <= :date')
            ->andWhere('s.startDate >= :now')
            ->andWhere('s.status = :status')
            ->setParameter('date', $date)
            ->setParameter('now', new \DateTime())
            ->setParameter('status', 'active')
            ->orderBy('s.startDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les bourses qui expirent bientôt
     */
    public function findExpiringSoon(int $days = 30): array
    {
        $date = new \DateTime();
        $date->modify("+{$days} days");

        return $this->createQueryBuilder('s')
            ->andWhere('s.endDate <= :date')
            ->andWhere('s.endDate >= :now')
            ->andWhere('s.status = :status')
            ->setParameter('date', $date)
            ->setParameter('now', new \DateTime())
            ->setParameter('status', 'active')
            ->orderBy('s.endDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte les bourses par statut
     */
    public function countByStatus(): array
    {
        return $this->createQueryBuilder('s')
            ->select('s.status, COUNT(s.id) as count')
            ->groupBy('s.status')
            ->orderBy('count', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte les bourses par type
     */
    public function countByType(): array
    {
        return $this->createQueryBuilder('s')
            ->select('s.type, COUNT(s.id) as count')
            ->groupBy('s.type')
            ->orderBy('count', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Calcule le montant total des bourses par élève
     */
    public function getTotalAmountByStudent(Student $student): float
    {
        $result = $this->createQueryBuilder('s')
            ->select('SUM(s.amount) as total')
            ->andWhere('s.student = :student')
            ->andWhere('s.status = :status')
            ->setParameter('student', $student)
            ->setParameter('status', 'active')
            ->getQuery()
            ->getSingleScalarResult();

        return (float) ($result ?? 0);
    }

    /**
     * Calcule le montant total des bourses par période
     */
    public function getTotalAmountByDateRange(\DateTimeInterface $startDate, \DateTimeInterface $endDate): float
    {
        $result = $this->createQueryBuilder('s')
            ->select('SUM(s.amount) as total')
            ->andWhere('s.startDate <= :endDate')
            ->andWhere('s.endDate >= :startDate OR s.endDate IS NULL')
            ->andWhere('s.status = :status')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->setParameter('status', 'active')
            ->getQuery()
            ->getSingleScalarResult();

        return (float) ($result ?? 0);
    }

    /**
     * Trouve les bourses récentes
     */
    public function findRecent(int $limit = 10): array
    {
        return $this->createQueryBuilder('s')
            ->orderBy('s.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche les bourses par nom
     */
    public function searchByName(string $search): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.name LIKE :search')
            ->setParameter('search', '%' . $search . '%')
            ->orderBy('s.startDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les bourses par utilisateur qui les a accordées
     */
    public function findByGrantedBy(int $userId): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.grantedBy = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('s.startDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Calcule les statistiques de bourses pour un élève
     */
    public function getScholarshipStatsByStudent(Student $student): array
    {
        $qb = $this->createQueryBuilder('s')
            ->select([
                'COUNT(s.id) as total_scholarships',
                'SUM(s.amount) as total_amount',
                'SUM(CASE WHEN s.status = \'active\' THEN 1 ELSE 0 END) as active_count',
                'SUM(CASE WHEN s.type = \'gratuité_totale\' THEN 1 ELSE 0 END) as full_scholarship_count'
            ])
            ->andWhere('s.student = :student')
            ->setParameter('student', $student);

        return $qb->getQuery()->getSingleResult();
    }

    /**
     * Trouve les bourses avec un montant spécifique
     */
    public function findByAmount(float $amount, string $operator = '='): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere("s.amount {$operator} :amount")
            ->setParameter('amount', $amount)
            ->orderBy('s.startDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les bourses par pourcentage
     */
    public function findByPercentage(float $percentage, string $operator = '='): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere("s.percentage {$operator} :percentage")
            ->setParameter('percentage', $percentage)
            ->orderBy('s.startDate', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
