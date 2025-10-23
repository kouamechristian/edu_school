<?php

namespace App\Validator\Constraints;

use App\Entity\Course;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class NoScheduleConflictValidator extends ConstraintValidator
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }

    public function validate($value, Constraint $constraint): void
    {
        if (!$constraint instanceof NoScheduleConflict) {
            throw new UnexpectedTypeException($constraint, NoScheduleConflict::class);
        }

        if (!$value instanceof Course) {
            throw new UnexpectedTypeException($value, Course::class);
        }

        // Si le cours n'a pas toutes les informations nécessaires, on skip la validation
        if (!$value->getDayOfWeek() || !$value->getTimeSlot()) {
            return;
        }

        $courseId = $value->getId();
        $dayOfWeek = $value->getDayOfWeek();
        $timeSlot = $value->getTimeSlot();
        $teacher = $value->getTeacher();
        $classroom = $value->getClassroom();
        $room = $value->getRoom();

        // 1. Vérifier les conflits d'enseignant
        if ($teacher) {
            $conflictingCourse = $this->findConflictingCourse(
                $courseId,
                $dayOfWeek,
                $timeSlot->getId(),
                'teacher',
                $teacher->getId()
            );

            if ($conflictingCourse) {
                $this->context->buildViolation($constraint->teacherConflict)
                    ->setParameter('{{ teacher }}', $teacher->getFullName())
                    ->setParameter('{{ day }}', $this->getDayLabel($dayOfWeek))
                    ->setParameter('{{ time }}', $timeSlot->getTimeRange())
                    ->atPath('teacher')
                    ->addViolation();
            }
        }

        // 2. Vérifier les conflits de classe
        if ($classroom) {
            $conflictingCourse = $this->findConflictingCourse(
                $courseId,
                $dayOfWeek,
                $timeSlot->getId(),
                'classroom',
                $classroom->getId()
            );

            if ($conflictingCourse) {
                $this->context->buildViolation($constraint->classroomConflict)
                    ->setParameter('{{ classroom }}', $classroom->getName())
                    ->setParameter('{{ day }}', $this->getDayLabel($dayOfWeek))
                    ->setParameter('{{ time }}', $timeSlot->getTimeRange())
                    ->atPath('classroom')
                    ->addViolation();
            }
        }

        // 3. Vérifier les conflits de salle
        if ($room) {
            $conflictingCourse = $this->findConflictingCourse(
                $courseId,
                $dayOfWeek,
                $timeSlot->getId(),
                'room',
                $room->getId()
            );

            if ($conflictingCourse) {
                $this->context->buildViolation($constraint->roomConflict)
                    ->setParameter('{{ room }}', $room->getCode())
                    ->setParameter('{{ day }}', $this->getDayLabel($dayOfWeek))
                    ->setParameter('{{ time }}', $timeSlot->getTimeRange())
                    ->atPath('room')
                    ->addViolation();
            }
        }
    }

    private function findConflictingCourse(
        ?int $currentCourseId,
        string $dayOfWeek,
        int $timeSlotId,
        string $entityType,
        int $entityId
    ): ?Course {
        $qb = $this->entityManager->createQueryBuilder();
        
        $qb->select('c')
            ->from(Course::class, 'c')
            ->where('c.dayOfWeek = :day')
            ->andWhere('c.timeSlot = :timeSlot')
            ->andWhere('c.isActive = :active')
            ->setParameter('day', $dayOfWeek)
            ->setParameter('timeSlot', $timeSlotId)
            ->setParameter('active', true);

        // Exclure le cours actuel si on est en modification
        if ($currentCourseId) {
            $qb->andWhere('c.id != :currentId')
               ->setParameter('currentId', $currentCourseId);
        }

        // Ajouter le filtre selon le type d'entité
        switch ($entityType) {
            case 'teacher':
                $qb->andWhere('c.teacher = :entityId');
                break;
            case 'classroom':
                $qb->andWhere('c.classroom = :entityId');
                break;
            case 'room':
                $qb->andWhere('c.room = :entityId');
                break;
        }

        $qb->setParameter('entityId', $entityId)
           ->setMaxResults(1);

        return $qb->getQuery()->getOneOrNullResult();
    }

    private function getDayLabel(string $day): string
    {
        return match($day) {
            'lundi' => 'Lundi',
            'mardi' => 'Mardi',
            'mercredi' => 'Mercredi',
            'jeudi' => 'Jeudi',
            'vendredi' => 'Vendredi',
            'samedi' => 'Samedi',
            'dimanche' => 'Dimanche',
            default => $day
        };
    }
}

