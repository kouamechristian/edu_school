<?php

namespace App\Repository;

use App\Entity\PreRegistration;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PreRegistration>
 */
class PreRegistrationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PreRegistration::class);
    }

    /**
     * Trouve les préinscriptions par statut
     */
    public function findByStatus(string $status): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.status = :status')
            ->setParameter('status', $status)
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les préinscriptions par école
     */
    public function findBySchool(int $schoolId): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.school = :schoolId')
            ->setParameter('schoolId', $schoolId)
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les préinscriptions par école et statut
     */
    public function findBySchoolAndStatus(int $schoolId, string $status): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.school = :schoolId')
            ->andWhere('p.status = :status')
            ->setParameter('schoolId', $schoolId)
            ->setParameter('status', $status)
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche les préinscriptions par nom ou prénom
     */
    public function searchByName(string $search, ?int $schoolId = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->where('p.firstName LIKE :search OR p.lastName LIKE :search')
            ->setParameter('search', '%' . $search . '%')
            ->orderBy('p.createdAt', 'DESC');

        if ($schoolId) {
            $qb->andWhere('p.school = :schoolId')
               ->setParameter('schoolId', $schoolId);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Compte les préinscriptions par statut
     */
    public function countByStatus(): array
    {
        $result = $this->createQueryBuilder('p')
            ->select('p.status, COUNT(p.id) as count')
            ->groupBy('p.status')
            ->getQuery()
            ->getResult();

        $counts = [];
        foreach ($result as $row) {
            $counts[$row['status']] = $row['count'];
        }

        return $counts;
    }

    /**
     * Compte les préinscriptions par statut pour une école
     */
    public function countByStatusInSchool(int $schoolId): array
    {
        $result = $this->createQueryBuilder('p')
            ->select('p.status, COUNT(p.id) as count')
            ->where('p.school = :schoolId')
            ->setParameter('schoolId', $schoolId)
            ->groupBy('p.status')
            ->getQuery()
            ->getResult();

        $counts = [];
        foreach ($result as $row) {
            $counts[$row['status']] = $row['count'];
        }

        return $counts;
    }

    /**
     * Trouve les préinscriptions en attente de validation
     */
    public function findPendingValidation(): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.status = :status')
            ->setParameter('status', 'documents_received')
            ->orderBy('p.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les préinscriptions validées prêtes pour l'inscription
     */
    public function findReadyForEnrollment(?int $schoolId = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->where('p.status = :status')
            ->setParameter('status', 'validated')
            ->orderBy('p.validatedAt', 'ASC');

        if ($schoolId) {
            $qb->andWhere('p.school = :schoolId')
               ->setParameter('schoolId', $schoolId);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve les préinscriptions récentes
     */
    public function findRecent(int $limit = 10): array
    {
        return $this->createQueryBuilder('p')
            ->orderBy('p.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les préinscriptions récentes pour une école
     */
    public function findRecentBySchool(int $schoolId, int $limit = 10): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.school = :schoolId')
            ->setParameter('schoolId', $schoolId)
            ->orderBy('p.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
