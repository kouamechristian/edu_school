<?php

namespace App\Tests\Entity;

use App\Entity\Classroom;
use App\Entity\Grade;
use App\Entity\Level;
use App\Entity\Registration;
use App\Entity\School;
use App\Entity\SchoolGroup;
use App\Entity\SchoolYear;
use App\Entity\Student;
use PHPUnit\Framework\TestCase;

class StudentTest extends TestCase
{
    private Student $student;

    protected function setUp(): void
    {
        $this->student = new Student();
    }

    /**
     * Rattache une inscription à l'élève (la scolarité est portée par Registration).
     */
    private function attachRegistration(?Classroom $classroom = null): Registration
    {
        $year = new SchoolYear();
        $year->setStartDate(new \DateTime('2026-09-01'));

        $registration = new Registration();
        $registration->setSchoolYear($year);
        if ($classroom !== null) {
            $registration->setClassroom($classroom);
        }

        $this->student->addRegistration($registration);

        return $registration;
    }

    public function testNewStudentHasDefaults(): void
    {
        $this->assertNull($this->student->getId());
        $this->assertTrue($this->student->isActive());
        // Sans inscription, le statut dérivé vaut « non affecté ».
        $this->assertSame('non_affecte', $this->student->getStatus());
        $this->assertInstanceOf(\DateTimeInterface::class, $this->student->getCreatedAt());
    }

    public function testGetFullName(): void
    {
        $this->student->setFirstName('Amadou');
        $this->student->setLastName('Diallo');

        $this->assertSame('Amadou Diallo', $this->student->getFullName());
    }

    public function testGetFullNameFallback(): void
    {
        $this->assertSame('Élève', $this->student->getFullName());
    }

    public function testStatusLabel(): void
    {
        $this->student->setStatus('affecte');
        $this->assertSame('Affecté', $this->student->getStatusLabel());

        $this->student->setStatus('non_affecte');
        $this->assertSame('Non affecté', $this->student->getStatusLabel());
    }

    public function testStatusColor(): void
    {
        $this->student->setStatus('affecte');
        $this->assertSame('success', $this->student->getStatusColor());

        $this->student->setStatus('non_affecte');
        $this->assertSame('secondary', $this->student->getStatusColor());
    }

    public function testGradesCollection(): void
    {
        $grade = new Grade();

        $this->student->addGrade($grade);
        $this->assertCount(1, $this->student->getGrades());
        $this->assertSame($this->student, $grade->getStudent());

        $this->student->addGrade($grade);
        $this->assertCount(1, $this->student->getGrades());

        $this->student->removeGrade($grade);
        $this->assertCount(0, $this->student->getGrades());
    }

    public function testSchoolRelation(): void
    {
        $school = new School();
        $school->setName('Lycée Moderne');

        $this->student->setSchool($school);
        $this->assertSame($school, $this->student->getSchool());
    }

    public function testClassroomAndLevelRelations(): void
    {
        $level = new Level();
        $classroom = new Classroom();
        $classroom->setLevel($level);

        // Classe et niveau sont portés par l'inscription ; le niveau dérive de la classe.
        $this->attachRegistration($classroom);

        $this->assertSame($classroom, $this->student->getClassroom());
        $this->assertSame($level, $this->student->getLevel());
    }

    public function testParentInfo(): void
    {
        $this->student->setParentName('M. Diallo');
        $this->student->setParentPhone('0708091011');
        $this->student->setParentEmail('diallo@mail.com');

        $this->assertSame('M. Diallo', $this->student->getParentName());
        $this->assertSame('0708091011', $this->student->getParentPhone());
        $this->assertSame('diallo@mail.com', $this->student->getParentEmail());
    }

    public function testEmergencyInfo(): void
    {
        $this->student->setEmergencyContact('Mme Koné');
        $this->student->setEmergencyPhone('0101010101');
        $this->student->setMedicalInfo('Allergie arachides');

        $this->assertSame('Mme Koné', $this->student->getEmergencyContact());
        $this->assertSame('0101010101', $this->student->getEmergencyPhone());
        $this->assertSame('Allergie arachides', $this->student->getMedicalInfo());
    }

    public function testToStringReturnsFullName(): void
    {
        $this->student->setFirstName('Amadou');
        $this->student->setLastName('Diallo');

        $this->assertSame('Amadou Diallo', (string) $this->student);
    }
}
