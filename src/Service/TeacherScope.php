<?php

namespace App\Service;

use App\Entity\Classroom;
use App\Entity\Course;
use App\Entity\Subject;
use App\Entity\User;
use App\Repository\CourseRepository;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Périmètre d'un enseignant : ses cours, et les classes / matières qui en découlent
 * (Course.teacher). Partagé par l'espace enseignant web et l'application mobile
 * enseignant, pour qu'un enseignant n'agisse que sur ses propres classes.
 *
 * Le cache des cours ne vaut que pour la requête en cours : reset() le vide entre
 * deux requêtes (processus longs), sinon il conserverait des entités détachées.
 */
class TeacherScope implements ResetInterface
{
    /** @var array<int, Course[]> */
    private array $coursesCache = [];

    public function __construct(private readonly CourseRepository $courseRepository)
    {
    }

    public function reset(): void
    {
        $this->coursesCache = [];
    }

    /**
     * @return Course[]
     */
    public function courses(User $teacher): array
    {
        return $this->coursesCache[$teacher->getId()] ??= $this->courseRepository->findByTeacher($teacher->getId());
    }

    /**
     * Classes distinctes enseignées.
     *
     * @return Classroom[]
     */
    public function classrooms(User $teacher): array
    {
        $classrooms = [];
        foreach ($this->courses($teacher) as $course) {
            $classroom = $course->getClassroom();
            if ($classroom && $classroom->getId() !== null) {
                $classrooms[$classroom->getId()] = $classroom;
            }
        }

        return array_values($classrooms);
    }

    /**
     * Classe enseignée par cet enseignant, ou null (n'existe pas / pas la sienne).
     */
    public function classroom(User $teacher, int $classroomId): ?Classroom
    {
        foreach ($this->classrooms($teacher) as $classroom) {
            if ($classroom->getId() === $classroomId) {
                return $classroom;
            }
        }

        return null;
    }

    /**
     * Matières distinctes enseignées, déduites des cours. Avec $withTeachingSubjects,
     * on y ajoute les « matières enseignées » de la fiche (règle des évaluations,
     * cf. EvaluationType).
     *
     * @return Subject[]
     */
    public function subjects(User $teacher, bool $withTeachingSubjects = false): array
    {
        $subjects = [];
        foreach ($this->courses($teacher) as $course) {
            $subject = $course->getSubject();
            if ($subject && $subject->getId() !== null) {
                $subjects[$subject->getId()] = $subject;
            }
        }

        if ($withTeachingSubjects) {
            foreach ($teacher->getTeachingSubjects() as $subject) {
                $subjects[$subject->getId()] = $subject;
            }
        }

        return array_values($subjects);
    }
}
