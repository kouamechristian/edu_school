<?php

namespace App\Tests\Entity;

use App\Entity\Employee;
use App\Entity\School;
use App\Entity\SchoolGroup;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
    private User $user;

    protected function setUp(): void
    {
        $this->user = new User();
    }

    public function testNewUserHasDefaults(): void
    {
        $this->assertNull($this->user->getId());
        $this->assertTrue($this->user->isActive());
        $this->assertContains('ROLE_USER', $this->user->getRoles());
        $this->assertInstanceOf(\DateTimeInterface::class, $this->user->getCreatedAt());
        $this->assertInstanceOf(\DateTimeInterface::class, $this->user->getUpdatedAt());
    }

    public function testGetRolesAlwaysContainsRoleUser(): void
    {
        $this->user->setRoles(['ROLE_ADMIN']);
        $roles = $this->user->getRoles();

        $this->assertContains('ROLE_USER', $roles);
        $this->assertContains('ROLE_ADMIN', $roles);
    }

    public function testGetRolesReturnsUniqueValues(): void
    {
        $this->user->setRoles(['ROLE_USER', 'ROLE_USER', 'ROLE_ADMIN']);
        $roles = $this->user->getRoles();

        $this->assertCount(2, $roles);
    }

    public function testGetFullNameWithFirstAndLastName(): void
    {
        $this->user->setFirstName('Jean');
        $this->user->setLastName('Dupont');

        $this->assertSame('Jean Dupont', $this->user->getFullName());
    }

    public function testGetFullNameFallsBackToUsername(): void
    {
        $this->user->setUsername('jdupont');

        $this->assertSame('jdupont', $this->user->getFullName());
    }

    public function testGetInitialsFromName(): void
    {
        $this->user->setFirstName('Jean');
        $this->user->setLastName('Dupont');

        $this->assertSame('JD', $this->user->getInitials());
    }

    public function testGetInitialsFromUsername(): void
    {
        $this->user->setUsername('admin');

        $this->assertSame('AD', $this->user->getInitials());
    }

    public function testHasRole(): void
    {
        $this->user->setRoles(['ROLE_ADMIN']);

        $this->assertTrue($this->user->hasRole('ROLE_ADMIN'));
        $this->assertTrue($this->user->hasRole('ROLE_USER'));
        $this->assertFalse($this->user->hasRole('ROLE_SUPER_ADMIN'));
    }

    public function testGetUserIdentifier(): void
    {
        $this->user->setUsername('admin');

        $this->assertSame('admin', $this->user->getUserIdentifier());
    }

    public function testUserTypeLabel(): void
    {
        $cases = [
            'admin' => 'Administrateur',
            'directeur' => 'Directeur',
            'enseignant' => 'Enseignant',
            'personnel' => 'Personnel',
            'parent' => 'Parent',
            null => 'Utilisateur',
        ];

        foreach ($cases as $type => $expected) {
            $this->user->setUserType($type ?: null);
            $this->assertSame($expected, $this->user->getUserTypeLabel());
        }
    }

    public function testCreateEmployeeFromUser(): void
    {
        $this->user->setFirstName('Marie');
        $this->user->setLastName('Koné');
        $this->user->setPhone('0102030405');
        $this->user->setUserType('enseignant');

        $employee = $this->user->createEmployee();

        $this->assertInstanceOf(Employee::class, $employee);
        $this->assertSame('Marie', $employee->getFirstName());
        $this->assertSame('Koné', $employee->getLastName());
        $this->assertSame('0102030405', $employee->getPhone());
        $this->assertSame('enseignant', $employee->getEmployeeType());
    }

    public function testCreateEmployeeReturnsSameInstanceOnSecondCall(): void
    {
        $this->user->setFirstName('Marie');
        $this->user->setLastName('Koné');
        $this->user->setUserType('personnel');

        $employee1 = $this->user->createEmployee();
        $employee2 = $this->user->createEmployee();

        $this->assertSame($employee1, $employee2);
    }

    public function testSchoolsCollection(): void
    {
        $school = new School();
        $school->setName('École Test');

        $this->user->addSchool($school);
        $this->assertCount(1, $this->user->getSchools());
        $this->assertTrue($this->user->getSchools()->contains($school));

        $this->user->addSchool($school);
        $this->assertCount(1, $this->user->getSchools());

        $this->user->removeSchool($school);
        $this->assertCount(0, $this->user->getSchools());
    }

    public function testToStringReturnsFullName(): void
    {
        $this->user->setFirstName('Jean');
        $this->user->setLastName('Dupont');

        $this->assertSame('Jean Dupont', (string) $this->user);
    }
}
