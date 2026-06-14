<?php

namespace App\Repository;

use App\Entity\Round;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Round>
 */
class RoundRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Round::class);
    }

    /**
     * Séries d'un établissement, triées par libellé.
     *
     * @return Round[]
     */
    public function findBySchool(?int $schoolId): array
    {
        if (!$schoolId) {
            return [];
        }

        return $this->createQueryBuilder('r')
            ->andWhere('r.school = :school')
            ->setParameter('school', $schoolId)
            ->orderBy('r.libelle', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
