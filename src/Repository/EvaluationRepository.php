<?php

namespace App\Repository;

use App\Entity\Evaluation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Evaluation>
 */
class EvaluationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Evaluation::class);
    }

    /**
     * Trouve les évaluations par classe
     */
    public function findByClassroom(int $classroomId): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.classroom = :classroom')
            ->andWhere('e.isActive = :active')
            ->setParameter('classroom', $classroomId)
            ->setParameter('active', true)
            ->orderBy('e.date', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les évaluations par période
     */
    public function findByPeriod(int $periodId): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.period = :period')
            ->andWhere('e.isActive = :active')
            ->setParameter('period', $periodId)
            ->setParameter('active', true)
            ->orderBy('e.date', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les évaluations par classe et période
     */
    public function findByClassroomAndPeriod(int $classroomId, int $periodId): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.classroom = :classroom')
            ->andWhere('e.period = :period')
            ->andWhere('e.isActive = :active')
            ->setParameter('classroom', $classroomId)
            ->setParameter('period', $periodId)
            ->setParameter('active', true)
            ->orderBy('e.date', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les évaluations par enseignant
     */
    public function findByTeacher(int $teacherId): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.teacher = :teacher')
            ->andWhere('e.isActive = :active')
            ->setParameter('teacher', $teacherId)
            ->setParameter('active', true)
            ->orderBy('e.date', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les évaluations par matière et classe
     */
    public function findBySubjectAndClassroom(int $subjectId, int $classroomId): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.subject = :subject')
            ->andWhere('e.classroom = :classroom')
            ->andWhere('e.isActive = :active')
            ->setParameter('subject', $subjectId)
            ->setParameter('classroom', $classroomId)
            ->setParameter('active', true)
            ->orderBy('e.date', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte les évaluations par classe
     */
    public function countByClassroom(int $classroomId): int
    {
        return $this->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->andWhere('e.classroom = :classroom')
            ->andWhere('e.isActive = :active')
            ->setParameter('classroom', $classroomId)
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();
    }
}

