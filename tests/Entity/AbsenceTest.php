<?php

namespace App\Tests\Entity;

use App\Entity\Absence;
use App\Entity\AbsenceType;
use App\Entity\School;
use App\Entity\Student;
use PHPUnit\Framework\TestCase;

class AbsenceTest extends TestCase
{
    private Absence $absence;

    protected function setUp(): void
    {
        $this->absence = new Absence();
    }

    public function testNewAbsenceHasDefaults(): void
    {
        $this->assertTrue($this->absence->isActive());
        $this->assertSame('pending', $this->absence->getJustificationStatus());
        $this->assertInstanceOf(\DateTimeInterface::class, $this->absence->getCreatedAt());
    }

    public function testJustificationStatusChecks(): void
    {
        $this->assertTrue($this->absence->isPendingJustification());
        $this->assertFalse($this->absence->isJustified());
        $this->assertFalse($this->absence->isUnjustified());

        $this->absence->setJustificationStatus('justified');
        $this->assertTrue($this->absence->isJustified());
        $this->assertFalse($this->absence->isPendingJustification());

        $this->absence->setJustificationStatus('unjustified');
        $this->assertTrue($this->absence->isUnjustified());
        $this->assertFalse($this->absence->isJustified());
    }

    public function testJustificationStatusLabel(): void
    {
        $cases = [
            'pending' => 'En attente',
            'justified' => 'Justifiée',
            'unjustified' => 'Non justifiée',
        ];

        foreach ($cases as $status => $expected) {
            $this->absence->setJustificationStatus($status);
            $this->assertSame($expected, $this->absence->getJustificationStatusLabel());
        }
    }

    public function testGetDurationInHoursReturnsNullWithoutTimes(): void
    {
        $this->assertNull($this->absence->getDurationInHours());
    }

    public function testGetDurationInHoursReturnsNullWithPartialTimes(): void
    {
        $this->absence->setStartTime(new \DateTime('08:00'));
        $this->assertNull($this->absence->getDurationInHours());
    }

    public function testGetDurationInHoursCalculatesCorrectly(): void
    {
        $this->absence->setStartTime(new \DateTime('08:00'));
        $this->absence->setEndTime(new \DateTime('12:00'));

        $this->assertSame(4.0, $this->absence->getDurationInHours());
    }

    public function testGetDurationInHoursHalfHour(): void
    {
        $this->absence->setStartTime(new \DateTime('09:00'));
        $this->absence->setEndTime(new \DateTime('10:30'));

        $this->assertSame(1.5, $this->absence->getDurationInHours());
    }

    public function testRelations(): void
    {
        $student = new Student();
        $student->setFirstName('Awa');
        $student->setLastName('Traoré');
        $school = new School();
        $absenceType = new AbsenceType();

        $this->absence->setStudent($student);
        $this->absence->setSchool($school);
        $this->absence->setAbsenceType($absenceType);

        $this->assertSame($student, $this->absence->getStudent());
        $this->assertSame($school, $this->absence->getSchool());
        $this->assertSame($absenceType, $this->absence->getAbsenceType());
    }
}
