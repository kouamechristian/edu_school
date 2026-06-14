<?php

namespace App\Repository;

use App\Entity\StudentTransfer;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<StudentTransfer>
 */
class StudentTransferRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StudentTransfer::class);
    }

    /**
     * Transferts des élèves d'un établissement donné, les plus récents d'abord.
     *
     * @return StudentTransfer[]
     */
    public function findBySchool(?int $schoolId, int $limit = 50): array
    {
        $qb = $this->createQueryBuilder('t')
            ->leftJoin('t.student', 's')
            ->addSelect('s')
            ->leftJoin('t.fromClassroom', 'fc')
            ->addSelect('fc')
            ->leftJoin('t.toClassroom', 'tc')
            ->addSelect('tc')
            ->orderBy('t.createdAt', 'DESC')
            ->setMaxResults($limit);

        if ($schoolId) {
            $qb->leftJoin('s.school', 'school')
                ->andWhere('school.id = :schoolId')
                ->setParameter('schoolId', $schoolId);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Transferts d'un élève donné, les plus récents d'abord.
     *
     * @return StudentTransfer[]
     */
    public function findByStudent(int $studentId): array
    {
        return $this->createQueryBuilder('t')
            ->leftJoin('t.fromClassroom', 'fc')->addSelect('fc')
            ->leftJoin('t.toClassroom', 'tc')->addSelect('tc')
            ->andWhere('t.student = :studentId')
            ->setParameter('studentId', $studentId)
            ->orderBy('t.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
