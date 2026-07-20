<?php

namespace App\Repository;

use App\Entity\Homework;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Homework>
 */
class HomeworkRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Homework::class);
    }

    /**
     * Devoirs publiés par un enseignant (les plus récents d'abord).
     *
     * @return Homework[]
     */
    public function findByTeacher(int $teacherId): array
    {
        return $this->createQueryBuilder('h')
            ->andWhere('h.teacher = :teacher')
            ->setParameter('teacher', $teacherId)
            ->orderBy('h.dueDate', 'DESC')
            ->addOrderBy('h.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Tous les devoirs d'une classe (remise la plus proche en premier).
     *
     * @return Homework[]
     */
    public function findByClassroom(int $classroomId): array
    {
        return $this->createQueryBuilder('h')
            ->andWhere('h.classroom = :classroom')
            ->setParameter('classroom', $classroomId)
            ->orderBy('h.dueDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Devoirs à venir d'une classe (date de remise >= aujourd'hui), les plus proches d'abord.
     *
     * @return Homework[]
     */
    public function findUpcomingByClassroom(int $classroomId): array
    {
        return $this->createQueryBuilder('h')
            ->andWhere('h.classroom = :classroom')
            ->andWhere('h.dueDate >= :today')
            ->setParameter('classroom', $classroomId)
            ->setParameter('today', new \DateTime('today'))
            ->orderBy('h.dueDate', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
