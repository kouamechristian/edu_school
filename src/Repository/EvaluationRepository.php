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
     * Liste filtrée des évaluations d'un établissement / année, avec filtres optionnels
     * (classe, période, enseignant). Sans filtre classe/période, renvoie toutes les
     * évaluations de l'établissement et de l'année courants.
     */
    public function findFiltered(
        ?int $schoolId,
        ?int $yearId,
        ?int $classroomId = null,
        ?int $periodId = null,
        ?int $teacherId = null
    ): array {
        $qb = $this->createQueryBuilder('e')
            ->innerJoin('e.classroom', 'c')
            ->andWhere('e.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('e.date', 'DESC');

        if ($schoolId) {
            $qb->andWhere('c.school = :school')->setParameter('school', $schoolId);
        }
        if ($yearId) {
            $qb->andWhere('c.schoolYear = :year')->setParameter('year', $yearId);
        }
        if ($classroomId) {
            $qb->andWhere('e.classroom = :classroom')->setParameter('classroom', $classroomId);
        }
        if ($periodId) {
            $qb->andWhere('e.period = :period')->setParameter('period', $periodId);
        }
        if ($teacherId) {
            $qb->andWhere('e.teacher = :teacher')->setParameter('teacher', $teacherId);
        }

        return $qb->getQuery()->getResult();
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

