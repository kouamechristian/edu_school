<?php

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class NoScheduleConflict extends Constraint
{
    public string $teacherConflict = 'L\'enseignant {{ teacher }} a déjà un cours le {{ day }} à {{ time }}.';
    public string $classroomConflict = 'La classe {{ classroom }} a déjà un cours le {{ day }} à {{ time }}.';
    public string $roomConflict = 'La salle {{ room }} est déjà occupée le {{ day }} à {{ time }}.';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}

