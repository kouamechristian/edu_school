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
     * Trouve les préinscriptions par école (et, si fourni, par année scolaire).
     *
     * La préinscription étant liée à l'année scolaire, la liste est normalement
     * restreinte à l'année courante ; $schoolYearId à null retourne toutes les années.
     */
    public function findBySchool(int $schoolId, ?int $schoolYearId = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->where('p.school = :schoolId')
            ->setParameter('schoolId', $schoolId)
            ->orderBy('p.createdAt', 'DESC');

        if ($schoolYearId) {
            $qb->andWhere('p.schoolYear = :schoolYearId')
               ->setParameter('schoolYearId', $schoolYearId);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve les préinscriptions par école et statut (et, si fourni, par année scolaire).
     */
    public function findBySchoolAndStatus(int $schoolId, string $status, ?int $schoolYearId = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->where('p.school = :schoolId')
            ->andWhere('p.status = :status')
            ->setParameter('schoolId', $schoolId)
            ->setParameter('status', $status)
            ->orderBy('p.createdAt', 'DESC');

        if ($schoolYearId) {
            $qb->andWhere('p.schoolYear = :schoolYearId')
               ->setParameter('schoolYearId', $schoolYearId);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Recherche les préinscriptions par nom ou prénom (filtrable par école et année).
     */
    public function searchByName(string $search, ?int $schoolId = null, ?int $schoolYearId = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->where('p.firstName LIKE :search OR p.lastName LIKE :search')
            ->setParameter('search', '%' . $search . '%')
            ->orderBy('p.createdAt', 'DESC');

        if ($schoolId) {
            $qb->andWhere('p.school = :schoolId')
               ->setParameter('schoolId', $schoolId);
        }

        if ($schoolYearId) {
            $qb->andWhere('p.schoolYear = :schoolYearId')
               ->setParameter('schoolYearId', $schoolYearId);
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
     * Compte les préinscriptions par statut pour une école (et, si fourni, une année).
     */
    public function countByStatusInSchool(int $schoolId, ?int $schoolYearId = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->select('p.status, COUNT(p.id) as count')
            ->where('p.school = :schoolId')
            ->setParameter('schoolId', $schoolId)
            ->groupBy('p.status');

        if ($schoolYearId) {
            $qb->andWhere('p.schoolYear = :schoolYearId')
               ->setParameter('schoolYearId', $schoolYearId);
        }

        $result = $qb->getQuery()->getResult();

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
     * Trouve les préinscriptions validées prêtes pour l'inscription.
     *
     * L'inscription étant liée à l'année scolaire, on ne propose que les
     * préinscriptions validées de l'année courante lorsque $schoolYearId est fourni.
     */
    public function findReadyForEnrollment(?int $schoolId = null, ?int $schoolYearId = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->where('p.status = :status')
            ->setParameter('status', 'validated')
            ->orderBy('p.validatedAt', 'ASC');

        if ($schoolId) {
            $qb->andWhere('p.school = :schoolId')
               ->setParameter('schoolId', $schoolId);
        }

        if ($schoolYearId) {
            $qb->andWhere('p.schoolYear = :schoolYearId')
               ->setParameter('schoolYearId', $schoolYearId);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Compte le nombre de préinscriptions par matricule national, pour un établissement.
     *
     * Toutes années confondues, mais uniquement les préinscriptions abouties
     * (statuts « validated » et « enrolled ») : reflète le nombre de préinscriptions
     * validées/inscrites d'un même élève depuis sa venue dans l'établissement.
     *
     * @param string[] $matriculeNationals
     * @return array<string, int> matricule national => nombre de préinscriptions validées/inscrites
     */
    public function countBySchoolGroupedByNational(int $schoolId, array $matriculeNationals): array
    {
        if ($matriculeNationals === []) {
            return [];
        }

        $rows = $this->createQueryBuilder('p')
            ->select('p.matriculeNational AS mn, COUNT(p.id) AS cnt')
            ->where('p.school = :schoolId')
            ->andWhere('p.matriculeNational IN (:nationals)')
            ->andWhere('p.status IN (:statuses)')
            ->setParameter('schoolId', $schoolId)
            ->setParameter('nationals', $matriculeNationals)
            ->setParameter('statuses', ['validated', 'enrolled'])
            ->groupBy('p.matriculeNational')
            ->getQuery()
            ->getScalarResult();

        $counts = [];
        foreach ($rows as $r) {
            $counts[trim((string) $r['mn'])] = (int) $r['cnt'];
        }

        return $counts;
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
