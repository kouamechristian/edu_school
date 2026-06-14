<?php

namespace App\Repository;

use App\Entity\Faculty;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Faculty>
 */
class FacultyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Faculty::class);
    }

    /**
     * Facultés d'un établissement, triées par libellé.
     *
     * @return Faculty[]
     */
    public function findBySchool(?int $schoolId): array
    {
        if (!$schoolId) {
            return [];
        }

        return $this->createQueryBuilder('f')
            ->andWhere('f.school = :school')
            ->setParameter('school', $schoolId)
            ->orderBy('f.libelle', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
