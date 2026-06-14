<?php

namespace App\Repository;

use App\Entity\StudentDropout;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<StudentDropout>
 */
class StudentDropoutRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StudentDropout::class);
    }

    /**
     * Abandons des élèves d'un établissement donné.
     *
     * @return StudentDropout[]
     */
    public function findBySchool(?int $schoolId): array
    {
        $qb = $this->createQueryBuilder('d')
            ->leftJoin('d.student', 's')
            ->addSelect('s')
            ->orderBy('d.createdAt', 'DESC');

        if ($schoolId) {
            $qb->leftJoin('s.school', 'school')
                ->andWhere('school.id = :schoolId')
                ->setParameter('schoolId', $schoolId);
        }

        return $qb->getQuery()->getResult();
    }
}
