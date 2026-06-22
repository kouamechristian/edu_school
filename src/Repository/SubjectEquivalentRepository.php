<?php

namespace App\Repository;

use App\Entity\SubjectEquivalent;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SubjectEquivalent>
 */
class SubjectEquivalentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SubjectEquivalent::class);
    }

    /**
     * Prochain numéro d'ordre pour un établissement (max + 1, ou 1 si aucun).
     */
    public function getNextNumeroOrdre(int $schoolId): int
    {
        $max = $this->createQueryBuilder('e')
            ->select('MAX(e.numeroOrdre)')
            ->andWhere('e.school = :school')
            ->setParameter('school', $schoolId)
            ->getQuery()
            ->getSingleScalarResult();

        return ((int) $max) + 1;
    }

    /**
     * Équivalents d'un établissement, triés par numéro d'ordre puis code.
     *
     * @return SubjectEquivalent[]
     */
    public function findBySchool(int $schoolId): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.school = :school')
            ->setParameter('school', $schoolId)
            ->orderBy('e.numeroOrdre', 'ASC')
            ->addOrderBy('e.code', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
