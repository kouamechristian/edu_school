<?php

namespace App\Tests\Entity;

use App\Entity\Evaluation;
use App\Entity\Grade;
use App\Entity\Student;
use PHPUnit\Framework\TestCase;

class GradeTest extends TestCase
{
    private Grade $grade;

    protected function setUp(): void
    {
        $this->grade = new Grade();
    }

    public function testNewGradeHasDefaults(): void
    {
        $this->assertNull($this->grade->getId());
        $this->assertNull($this->grade->getValue());
        $this->assertNull($this->grade->getStatus());
        $this->assertInstanceOf(\DateTimeImmutable::class, $this->grade->getCreatedAt());
    }

    public function testGetDisplayValueWithNumericGrade(): void
    {
        $evaluation = new Evaluation();
        $evaluation->setMaxGrade('20.00');

        $this->grade->setEvaluation($evaluation);
        $this->grade->setValue('15.50');

        $this->assertSame('15.50 / 20.00', $this->grade->getDisplayValue());
    }

    public function testGetDisplayValueWithAbsentStatus(): void
    {
        $this->grade->setStatus('absent');
        $this->assertSame('Absent', $this->grade->getDisplayValue());
    }

    public function testGetDisplayValueWithDispenseStatus(): void
    {
        $this->grade->setStatus('dispense');
        $this->assertSame('Dispensé', $this->grade->getDisplayValue());
    }

    public function testGetDisplayValueWithNonRenduStatus(): void
    {
        $this->grade->setStatus('non_rendu');
        $this->assertSame('Non rendu', $this->grade->getDisplayValue());
    }

    public function testGetDisplayValueWithNoValueAndNoStatus(): void
    {
        $this->assertSame('-', $this->grade->getDisplayValue());
    }

    public function testStatusLabelMapping(): void
    {
        $cases = [
            'absent' => 'Absent',
            'dispense' => 'Dispensé',
            'non_rendu' => 'Non rendu',
            null => '',
        ];

        foreach ($cases as $status => $expected) {
            $this->grade->setStatus($status);
            $this->assertSame($expected, $this->grade->getStatusLabel());
        }
    }

    public function testStatusTakesPriorityOverValueInDisplay(): void
    {
        $evaluation = new Evaluation();
        $evaluation->setMaxGrade('20.00');

        $this->grade->setEvaluation($evaluation);
        $this->grade->setValue('12.00');
        $this->grade->setStatus('absent');

        $this->assertSame('Absent', $this->grade->getDisplayValue());
    }

    public function testRelations(): void
    {
        $student = new Student();
        $evaluation = new Evaluation();

        $this->grade->setStudent($student);
        $this->grade->setEvaluation($evaluation);

        $this->assertSame($student, $this->grade->getStudent());
        $this->assertSame($evaluation, $this->grade->getEvaluation());
    }

    public function testToStringDelegatesToDisplayValue(): void
    {
        $this->grade->setStatus('absent');
        $this->assertSame('Absent', (string) $this->grade);
    }
}
