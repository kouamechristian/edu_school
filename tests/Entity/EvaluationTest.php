<?php

namespace App\Tests\Entity;

use App\Entity\Evaluation;
use App\Entity\Subject;
use PHPUnit\Framework\TestCase;

class EvaluationTest extends TestCase
{
    private Evaluation $evaluation;

    protected function setUp(): void
    {
        $this->evaluation = new Evaluation();
    }

    public function testNewEvaluationHasDefaults(): void
    {
        $this->assertFalse($this->evaluation->isPublished());
        $this->assertTrue($this->evaluation->isActive());
        $this->assertSame('20.00', $this->evaluation->getMaxGrade());
        $this->assertSame('1.00', $this->evaluation->getCoefficient());
        $this->assertInstanceOf(\DateTimeImmutable::class, $this->evaluation->getCreatedAt());
    }

    public function testTypeLabel(): void
    {
        $cases = [
            'controle_continu' => 'Contrôle continu',
            'devoir_surveille' => 'Devoir surveillé',
            'devoir_maison' => 'Devoir maison',
            'examen' => 'Examen',
            'oral' => 'Oral',
            'pratique' => 'Pratique',
            'projet' => 'Projet',
        ];

        foreach ($cases as $type => $expected) {
            $this->evaluation->setType($type);
            $this->assertSame($expected, $this->evaluation->getTypeLabel());
        }
    }

    public function testToStringFormat(): void
    {
        $subject = new Subject();
        $subject->setName('Mathématiques');

        $this->evaluation->setName('DS1');
        $this->evaluation->setSubject($subject);
        $this->evaluation->setDate(new \DateTime('2026-03-15'));

        $this->assertSame('DS1 (Mathématiques - 15/03/2026)', (string) $this->evaluation);
    }

    public function testPublishToggle(): void
    {
        $this->assertFalse($this->evaluation->isPublished());

        $this->evaluation->setIsPublished(true);
        $this->assertTrue($this->evaluation->isPublished());
    }

    public function testCustomMaxGradeAndCoefficient(): void
    {
        $this->evaluation->setMaxGrade('10.00');
        $this->evaluation->setCoefficient('3.00');

        $this->assertSame('10.00', $this->evaluation->getMaxGrade());
        $this->assertSame('3.00', $this->evaluation->getCoefficient());
    }
}
