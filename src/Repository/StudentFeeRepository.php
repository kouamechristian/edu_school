<?php

namespace App\Repository;

use App\Entity\StudentFee;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<StudentFee>
 */
class StudentFeeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StudentFee::class);
    }

    public function findByStudent(int $studentId): array
    {
        return $this->createQueryBuilder('sf')
            ->join('sf.fee', 'f')
            ->where('sf.student = :studentId')
            ->andWhere('f.isActive = true')
            ->setParameter('studentId', $studentId)
            ->orderBy('f.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByFee(int $feeId): array
    {
        return $this->createQueryBuilder('sf')
            ->where('sf.fee = :feeId')
            ->setParameter('feeId', $feeId)
            ->orderBy('sf.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function existsForStudentAndFee(int $studentId, int $feeId): bool
    {
        return (bool) $this->createQueryBuilder('sf')
            ->select('COUNT(sf.id)')
            ->where('sf.student = :studentId')
            ->andWhere('sf.fee = :feeId')
            ->setParameter('studentId', $studentId)
            ->setParameter('feeId', $feeId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findOneForStudentAndFee(int $studentId, int $feeId): ?StudentFee
    {
        return $this->createQueryBuilder('sf')
            ->where('sf.student = :studentId')
            ->andWhere('sf.fee = :feeId')
            ->setParameter('studentId', $studentId)
            ->setParameter('feeId', $feeId)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function getTotalByStudent(int $studentId): float
    {
        $result = $this->createQueryBuilder('sf')
            ->select('SUM(sf.amount) as total')
            ->join('sf.fee', 'f')
            ->where('sf.student = :studentId')
            ->andWhere('f.isActive = true')
            ->setParameter('studentId', $studentId)
            ->getQuery()
            ->getSingleScalarResult();

        return (float) ($result ?? 0);
    }

    public function getTotalPaidByStudent(int $studentId): float
    {
        $result = $this->createQueryBuilder('sf')
            ->select('SUM(sf.paidAmount) as total')
            ->join('sf.fee', 'f')
            ->where('sf.student = :studentId')
            ->andWhere('f.isActive = true')
            ->setParameter('studentId', $studentId)
            ->getQuery()
            ->getSingleScalarResult();

        return (float) ($result ?? 0);
    }

    public function getUnpaidByStudent(int $studentId): array
    {
        return $this->createQueryBuilder('sf')
            ->join('sf.fee', 'f')
            ->where('sf.student = :studentId')
            ->andWhere('sf.status != :paid')
            ->andWhere('f.isActive = true')
            ->setParameter('studentId', $studentId)
            ->setParameter('paid', 'paye')
            ->orderBy('f.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
