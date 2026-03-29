<?php

namespace App\Tests\Entity;

use App\Entity\School;
use App\Entity\SchoolGroup;
use PHPUnit\Framework\TestCase;

class SchoolTest extends TestCase
{
    private School $school;

    protected function setUp(): void
    {
        $this->school = new School();
    }

    public function testNewSchoolHasDefaults(): void
    {
        $this->assertNull($this->school->getId());
        $this->assertTrue($this->school->isActive());
        $this->assertInstanceOf(\DateTimeInterface::class, $this->school->getCreatedAt());
        $this->assertInstanceOf(\DateTimeInterface::class, $this->school->getUpdatedAt());
    }

    public function testTypeLabel(): void
    {
        $cases = [
            'maternelle' => 'Maternelle',
            'primaire' => 'Primaire',
            'college' => 'Collège',
            'lycee' => 'Lycée',
            'universite' => 'Université',
        ];

        foreach ($cases as $type => $expected) {
            $this->school->setType($type);
            $this->assertSame($expected, $this->school->getTypeLabel());
        }
    }

    public function testSettersAndGetters(): void
    {
        $this->school->setName('Groupe Scolaire Les Étoiles');
        $this->school->setCode('GSE-001');
        $this->school->setType('primaire');
        $this->school->setAddress('Abidjan, Cocody');
        $this->school->setPhone('0101020304');
        $this->school->setEmail('contact@gse.ci');
        $this->school->setDirector('M. Kouadio');
        $this->school->setLogo('/uploads/logo.png');

        $this->assertSame('Groupe Scolaire Les Étoiles', $this->school->getName());
        $this->assertSame('GSE-001', $this->school->getCode());
        $this->assertSame('primaire', $this->school->getType());
        $this->assertSame('Abidjan, Cocody', $this->school->getAddress());
        $this->assertSame('0101020304', $this->school->getPhone());
        $this->assertSame('contact@gse.ci', $this->school->getEmail());
        $this->assertSame('M. Kouadio', $this->school->getDirector());
        $this->assertSame('/uploads/logo.png', $this->school->getLogo());
    }

    public function testSchoolGroupRelation(): void
    {
        $group = new SchoolGroup();
        $this->school->setSchoolGroup($group);

        $this->assertSame($group, $this->school->getSchoolGroup());
    }

    public function testIsActiveToggle(): void
    {
        $this->assertTrue($this->school->isActive());

        $this->school->setIsActive(false);
        $this->assertFalse($this->school->isActive());
    }

    public function testToStringReturnsName(): void
    {
        $this->school->setName('Lycée Moderne');
        $this->assertSame('Lycée Moderne', (string) $this->school);
    }

    public function testToStringEmptyByDefault(): void
    {
        $this->assertSame('', (string) $this->school);
    }
}
