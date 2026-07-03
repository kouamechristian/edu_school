<?php

namespace App\Repository;

use App\Entity\ActivityLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ActivityLog>
 */
class ActivityLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ActivityLog::class);
    }

    /**
     * Construit la requête filtrée du journal, prête à être paginée.
     *
     * @param array{
     *     school_id?: int|null,
     *     action?: string|null,
     *     entity_type?: string|null,
     *     user_id?: int|null,
     *     search?: string|null,
     *     date_from?: string|null,
     *     date_to?: string|null,
     *     include_null_school?: bool
     * } $filters
     */
    public function buildFilteredQuery(array $filters = []): QueryBuilder
    {
        $qb = $this->createQueryBuilder('a')
            ->leftJoin('a.user', 'u')->addSelect('u')
            ->orderBy('a.createdAt', 'DESC')
            ->addOrderBy('a.id', 'DESC');

        // Cloisonnement multi-établissement : on peut inclure les traces « système »
        // (sans établissement, ex. connexions) en plus de l'établissement courant.
        if (!empty($filters['school_id'])) {
            if (!empty($filters['include_null_school'])) {
                $qb->andWhere('a.school = :school OR a.school IS NULL')
                    ->setParameter('school', $filters['school_id']);
            } else {
                $qb->andWhere('a.school = :school')
                    ->setParameter('school', $filters['school_id']);
            }
        }

        if (!empty($filters['action'])) {
            $qb->andWhere('a.action = :action')->setParameter('action', $filters['action']);
        }

        if (!empty($filters['entity_type'])) {
            $qb->andWhere('a.entityType = :entityType')->setParameter('entityType', $filters['entity_type']);
        }

        if (!empty($filters['user_id'])) {
            $qb->andWhere('a.user = :user')->setParameter('user', $filters['user_id']);
        }

        if (!empty($filters['search'])) {
            $qb->andWhere('a.username LIKE :search OR a.entityLabel LIKE :search OR a.description LIKE :search')
                ->setParameter('search', '%' . $filters['search'] . '%');
        }

        if (!empty($filters['date_from'])) {
            $qb->andWhere('a.createdAt >= :dateFrom')
                ->setParameter('dateFrom', new \DateTime($filters['date_from'] . ' 00:00:00'));
        }

        if (!empty($filters['date_to'])) {
            $qb->andWhere('a.createdAt <= :dateTo')
                ->setParameter('dateTo', new \DateTime($filters['date_to'] . ' 23:59:59'));
        }

        return $qb;
    }

    /**
     * Liste distincte des types d'entités déjà présents dans le journal (pour le filtre).
     *
     * @return string[]
     */
    public function findDistinctEntityTypes(?int $schoolId = null): array
    {
        $qb = $this->createQueryBuilder('a')
            ->select('DISTINCT a.entityType')
            ->andWhere('a.entityType IS NOT NULL')
            ->orderBy('a.entityType', 'ASC');

        if ($schoolId) {
            $qb->andWhere('a.school = :school OR a.school IS NULL')
                ->setParameter('school', $schoolId);
        }

        return array_column($qb->getQuery()->getScalarResult(), 'entityType');
    }

    /**
     * Purge les traces antérieures à une date (rétention).
     */
    public function deleteOlderThan(\DateTimeInterface $before): int
    {
        return $this->createQueryBuilder('a')
            ->delete()
            ->where('a.createdAt < :before')
            ->setParameter('before', $before)
            ->getQuery()
            ->execute();
    }
}
