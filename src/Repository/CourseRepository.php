<?php

namespace App\Repository;

use App\Entity\Course;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Course>
 */
class CourseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Course::class);
    }

    /**
     * Trouver tous les cours actifs
     */
    public function findActive(): array
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.timeSlot', 't')
            ->andWhere('c.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('c.dayOfWeek', 'ASC')
            ->addOrderBy('t.orderNumber', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouver les cours par classe
     */
    public function findByClassroom(int $classroomId): array
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.timeSlot', 't')
            ->andWhere('c.classroom = :classroom')
            ->andWhere('c.isActive = :active')
            ->setParameter('classroom', $classroomId)
            ->setParameter('active', true)
            ->orderBy('c.dayOfWeek', 'ASC')
            ->addOrderBy('t.orderNumber', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouver les cours par enseignant
     */
    public function findByTeacher(int $teacherId): array
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.timeSlot', 't')
            ->andWhere('c.teacher = :teacher')
            ->andWhere('c.isActive = :active')
            ->setParameter('teacher', $teacherId)
            ->setParameter('active', true)
            ->orderBy('c.dayOfWeek', 'ASC')
            ->addOrderBy('t.orderNumber', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouver les cours par jour de la semaine
     */
    public function findByDayOfWeek(string $day, int $classroomId): array
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.timeSlot', 't')
            ->andWhere('c.dayOfWeek = :day')
            ->andWhere('c.classroom = :classroom')
            ->andWhere('c.isActive = :active')
            ->setParameter('day', $day)
            ->setParameter('classroom', $classroomId)
            ->setParameter('active', true)
            ->orderBy('t.orderNumber', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouver l'emploi du temps d'une classe
     */
    public function findScheduleByClassroom(int $classroomId): array
    {
        $courses = $this->findByClassroom($classroomId);
        
        // Organiser par jour de la semaine
        $schedule = [
            'lundi' => [],
            'mardi' => [],
            'mercredi' => [],
            'jeudi' => [],
            'vendredi' => [],
            'samedi' => [],
        ];

        foreach ($courses as $course) {
            $day = $course->getDayOfWeek();
            if (isset($schedule[$day])) {
                $schedule[$day][] = $course;
            }
        }

        return $schedule;
    }

    /**
     * Vérifier s'il y a des conflits pour un cours
     */
    public function checkConflicts(
        ?int $courseId,
        string $dayOfWeek,
        int $timeSlotId,
        ?int $teacherId = null,
        ?int $classroomId = null,
        ?int $roomId = null
    ): array {
        $conflicts = [];

        // Vérifier conflit enseignant
        if ($teacherId) {
            $qb = $this->createQueryBuilder('c')
                ->where('c.dayOfWeek = :day')
                ->andWhere('c.timeSlot = :timeSlot')
                ->andWhere('c.teacher = :teacher')
                ->andWhere('c.isActive = :active')
                ->setParameter('day', $dayOfWeek)
                ->setParameter('timeSlot', $timeSlotId)
                ->setParameter('teacher', $teacherId)
                ->setParameter('active', true);

            if ($courseId) {
                $qb->andWhere('c.id != :courseId')
                   ->setParameter('courseId', $courseId);
            }

            if ($qb->getQuery()->getOneOrNullResult()) {
                $conflicts[] = 'teacher';
            }
        }

        // Vérifier conflit classe
        if ($classroomId) {
            $qb = $this->createQueryBuilder('c')
                ->where('c.dayOfWeek = :day')
                ->andWhere('c.timeSlot = :timeSlot')
                ->andWhere('c.classroom = :classroom')
                ->andWhere('c.isActive = :active')
                ->setParameter('day', $dayOfWeek)
                ->setParameter('timeSlot', $timeSlotId)
                ->setParameter('classroom', $classroomId)
                ->setParameter('active', true);

            if ($courseId) {
                $qb->andWhere('c.id != :courseId')
                   ->setParameter('courseId', $courseId);
            }

            if ($qb->getQuery()->getOneOrNullResult()) {
                $conflicts[] = 'classroom';
            }
        }

        // Vérifier conflit salle
        if ($roomId) {
            $qb = $this->createQueryBuilder('c')
                ->where('c.dayOfWeek = :day')
                ->andWhere('c.timeSlot = :timeSlot')
                ->andWhere('c.room = :room')
                ->andWhere('c.isActive = :active')
                ->setParameter('day', $dayOfWeek)
                ->setParameter('timeSlot', $timeSlotId)
                ->setParameter('room', $roomId)
                ->setParameter('active', true);

            if ($courseId) {
                $qb->andWhere('c.id != :courseId')
                   ->setParameter('courseId', $courseId);
            }

            if ($qb->getQuery()->getOneOrNullResult()) {
                $conflicts[] = 'room';
            }
        }

        return $conflicts;
    }

    /**
     * Obtenir les statistiques de conflits pour un établissement
     */
    public function getConflictStatistics(int $schoolId): array
    {
        // Cette méthode pourrait être développée pour afficher un tableau de bord des conflits
        return [
            'total_conflicts' => 0,
            'teacher_conflicts' => 0,
            'classroom_conflicts' => 0,
            'room_conflicts' => 0,
        ];
    }
}

