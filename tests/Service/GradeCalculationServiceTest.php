<?php

namespace App\Tests\Service;

use App\Service\GradeCalculationService;
use PHPUnit\Framework\TestCase;

class GradeCalculationServiceTest extends TestCase
{
    private GradeCalculationService $service;

    protected function setUp(): void
    {
        $gradeRepository = $this->createMock(\App\Repository\GradeRepository::class);
        $subjectRepository = $this->createMock(\App\Repository\SubjectRepository::class);
        $entityManager = $this->createMock(\Doctrine\ORM\EntityManagerInterface::class);

        $this->service = new GradeCalculationService(
            $gradeRepository,
            $subjectRepository,
            $entityManager
        );
    }

    public function testGetAppreciationExcellent(): void
    {
        $this->assertSame('Excellent', $this->service->getAppreciation(18.0));
        $this->assertSame('Excellent', $this->service->getAppreciation(19.5));
        $this->assertSame('Excellent', $this->service->getAppreciation(20.0));
    }

    public function testGetAppreciationTresBien(): void
    {
        $this->assertSame('Très bien', $this->service->getAppreciation(16.0));
        $this->assertSame('Très bien', $this->service->getAppreciation(17.99));
    }

    public function testGetAppreciationBien(): void
    {
        $this->assertSame('Bien', $this->service->getAppreciation(14.0));
        $this->assertSame('Bien', $this->service->getAppreciation(15.99));
    }

    public function testGetAppreciationAssezBien(): void
    {
        $this->assertSame('Assez bien', $this->service->getAppreciation(12.0));
        $this->assertSame('Assez bien', $this->service->getAppreciation(13.99));
    }

    public function testGetAppreciationPassable(): void
    {
        $this->assertSame('Passable', $this->service->getAppreciation(10.0));
        $this->assertSame('Passable', $this->service->getAppreciation(11.99));
    }

    public function testGetAppreciationInsuffisant(): void
    {
        $this->assertSame('Insuffisant', $this->service->getAppreciation(8.0));
        $this->assertSame('Insuffisant', $this->service->getAppreciation(9.99));
    }

    public function testGetAppreciationTresInsuffisant(): void
    {
        $this->assertSame('Très insuffisant', $this->service->getAppreciation(0.0));
        $this->assertSame('Très insuffisant', $this->service->getAppreciation(5.0));
        $this->assertSame('Très insuffisant', $this->service->getAppreciation(7.99));
    }

    public function testGetMentionFelicitations(): void
    {
        $this->assertSame('Félicitations du conseil de classe', $this->service->getMention(18.0));
        $this->assertSame('Félicitations du conseil de classe', $this->service->getMention(20.0));
    }

    public function testGetMentionCompliments(): void
    {
        $this->assertSame('Compliments du conseil de classe', $this->service->getMention(16.0));
        $this->assertSame('Compliments du conseil de classe', $this->service->getMention(17.5));
    }

    public function testGetMentionEncouragements(): void
    {
        $this->assertSame('Encouragements', $this->service->getMention(14.0));
        $this->assertSame('Encouragements', $this->service->getMention(15.99));
    }

    public function testGetMentionTableauHonneur(): void
    {
        $this->assertSame("Tableau d'honneur", $this->service->getMention(12.0));
        $this->assertSame("Tableau d'honneur", $this->service->getMention(13.99));
    }

    public function testGetMentionNullBelowThreshold(): void
    {
        $this->assertNull($this->service->getMention(11.99));
        $this->assertNull($this->service->getMention(5.0));
        $this->assertNull($this->service->getMention(0.0));
    }

    /**
     * @dataProvider appreciationBoundaryProvider
     */
    public function testAppreciationBoundaries(float $average, string $expected): void
    {
        $this->assertSame($expected, $this->service->getAppreciation($average));
    }

    public static function appreciationBoundaryProvider(): array
    {
        return [
            'boundary 18' => [18.0, 'Excellent'],
            'boundary 16' => [16.0, 'Très bien'],
            'boundary 14' => [14.0, 'Bien'],
            'boundary 12' => [12.0, 'Assez bien'],
            'boundary 10' => [10.0, 'Passable'],
            'boundary 8' => [8.0, 'Insuffisant'],
            'just below 18' => [17.99, 'Très bien'],
            'just below 16' => [15.99, 'Bien'],
            'just below 14' => [13.99, 'Assez bien'],
            'just below 12' => [11.99, 'Passable'],
            'just below 10' => [9.99, 'Insuffisant'],
            'just below 8' => [7.99, 'Très insuffisant'],
            'zero' => [0.0, 'Très insuffisant'],
        ];
    }
}
