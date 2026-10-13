<?php

namespace App\Service;

use App\Entity\Absence;
use App\Entity\Course;
use App\Entity\Grade;
use App\Entity\Period;
use App\Entity\Student;
use App\Repository\CourseRepository;
use App\Repository\TimeSlotRepository;
use Symfony\Component\HttpFoundation\Request;

/**
 * Données JSON de la scolarité d'un élève (fiche, notes, absences, emploi du temps),
 * partagées par les API des applications mobiles parent et élève : les deux
 * affichent les mêmes informations, seul le contrôle d'accès diffère.
 *
 * Aucun contrôle d'accès ici : il est fait en amont par chaque contrôleur
 * (ChildVoter pour le parent, élève du compte connecté pour l'élève).
 */
class PortalPayloads
{
    public function __construct(
        private readonly ParentPortalService $portal,
        private readonly CourseRepository $courseRepository,
        private readonly TimeSlotRepository $timeSlotRepository,
    ) {
    }

    public function student(Student $student, Request $request): array
    {
        $photo = $student->getPhoto();

        return [
            'id' => $student->getId(),
            'fullName' => $student->getFullName(),
            'firstName' => $student->getFirstName(),
            'lastName' => $student->getLastName(),
            'gender' => $student->getGender(),
            'dateOfBirth' => $student->getDateOfBirth()?->format('Y-m-d'),
            'matriculeInterne' => $student->getMatriculeInterne(),
            'matriculeNational' => $student->getMatriculeNational(),
            'school' => $student->getSchool()?->getName(),
            'level' => $student->getLevel()?->getName(),
            'classroom' => $student->getClassroom()?->getName(),
            'schoolYear' => $student->getSchoolYear()?->getName(),
            // getBasePath() couvre les déploiements en sous-dossier (ex. /edu_school/public).
            'photoUrl' => $photo
                ? $request->getSchemeAndHttpHost() . $request->getBasePath() . '/' . ltrim($photo, '/')
                : null,
        ];
    }

    public function period(?Period $period): ?array
    {
        return $period ? [
            'id' => $period->getId(),
            'name' => $period->getName(),
            'start' => $period->getStartDate()?->format('Y-m-d'),
            'end' => $period->getEndDate()?->format('Y-m-d'),
        ] : null;
    }

    /**
     * Période demandée (?period=ID) validée contre les périodes de l'élève,
     * sinon la période courante.
     */
    public function resolvePeriod(Student $student, Request $request): ?Period
    {
        $requestedId = $request->query->getInt('period');
        if ($requestedId > 0) {
            foreach ($this->portal->getPeriods($student) as $period) {
                if ($period->getId() === $requestedId) {
                    return $period;
                }
            }
        }

        return $this->portal->getCurrentPeriod($student);
    }

    /**
     * Notes publiées d'une période, regroupées par matière.
     */
    public function grades(Student $student, ?Period $period): array
    {
        $academic = $this->portal->getAcademicReport($student, $period);

        $subjects = [];
        foreach ($academic['subjects'] as $row) {
            $subjects[] = [
                'id' => $row['subject']->getId(),
                'name' => $row['subject']->getName(),
                'coefficient' => $this->num($row['subject']->getCoefficient()),
                'average' => $this->num($row['average']),
                'grades' => array_map(fn (Grade $g) => $this->grade($g), $row['grades']),
            ];
        }

        return [
            'periods' => array_map(fn (Period $p) => $this->period($p), $this->portal->getPeriods($student)),
            'period' => $this->period($period),
            'generalAverage' => $this->num($academic['general_average']),
            'gradeCount' => $academic['grade_count'],
            'subjects' => $subjects,
        ];
    }

    public function absences(Student $student, ?Period $period): array
    {
        $attendance = $this->portal->getAttendanceReport($student, $period);

        return [
            'periods' => array_map(fn (Period $p) => $this->period($p), $this->portal->getPeriods($student)),
            'period' => $this->period($period),
            'totals' => $this->attendanceTotals($attendance),
            'absences' => array_map(fn (Absence $a) => $this->absence($a), $attendance['absences']),
        ];
    }

    /**
     * Emploi du temps de la classe de l'élève (jours triés par heure de début).
     */
    public function schedule(Student $student): array
    {
        $classroom = $student->getClassroom();
        $days = [];
        $timeSlots = [];

        if ($classroom) {
            foreach ($this->courseRepository->findScheduleByClassroom($classroom->getId()) as $day => $courses) {
                usort($courses, static fn (Course $a, Course $b) => $a->getStartTime() <=> $b->getStartTime());
                $days[] = ['day' => $day, 'courses' => array_map(fn (Course $c) => $this->course($c), $courses)];
            }
            foreach ($this->timeSlotRepository->findBySchool($classroom->getSchool()?->getId()) as $slot) {
                $timeSlots[] = [
                    'id' => $slot->getId(),
                    'name' => $slot->getName(),
                    'start' => $slot->getStartTime()?->format('H:i'),
                    'end' => $slot->getEndTime()?->format('H:i'),
                    'type' => $slot->getType(),
                ];
            }
        }

        return [
            'classroom' => $classroom?->getName(),
            'days' => $days,
            'timeSlots' => $timeSlots,
        ];
    }

    public function grade(Grade $grade): array
    {
        $evaluation = $grade->getEvaluation();

        return [
            'id' => $grade->getId(),
            'value' => $this->num($grade->getValue()),
            'display' => $grade->getDisplayValue(),
            'status' => $grade->getStatusLabel(),
            'comment' => $grade->getComment(),
            'evaluation' => $evaluation?->getName(),
            'type' => $evaluation?->getTypeLabel(),
            'date' => $evaluation?->getDate()?->format('Y-m-d'),
            'maxGrade' => $this->num($evaluation?->getMaxGrade()),
            'coefficient' => $this->num($evaluation?->getCoefficient()),
        ];
    }

    public function absence(Absence $absence): array
    {
        return [
            'id' => $absence->getId(),
            'date' => $absence->getDate()?->format('Y-m-d'),
            'start' => $absence->getStartTime()?->format('H:i'),
            'end' => $absence->getEndTime()?->format('H:i'),
            'type' => $absence->getAbsenceType()?->getName(),
            'color' => $absence->getAbsenceType()?->getColor(),
            'reason' => $absence->getReason(),
            'justified' => $absence->isJustified(),
            'pending' => $absence->isPendingJustification(),
            'status' => $absence->getJustificationStatusLabel(),
        ];
    }

    public function course(Course $course): array
    {
        return [
            'id' => $course->getId(),
            'subject' => $course->getSubject()?->getName(),
            'color' => $course->getSubject()?->getColor(),
            'teacher' => $course->getTeacher()?->getFullName(),
            'room' => $course->getRoom()?->getName(),
            'start' => $course->getStartTime()?->format('H:i'),
            'end' => $course->getEndTime()?->format('H:i'),
        ];
    }

    public function attendanceTotals(array $attendance): array
    {
        return [
            'total' => $attendance['total'],
            'justified' => $attendance['justified'],
            'pending' => $attendance['pending'],
            'unjustified' => $attendance['unjustified'],
        ];
    }

    public function num(mixed $value): ?float
    {
        return $value === null || $value === '' ? null : round((float) $value, 2);
    }
}
