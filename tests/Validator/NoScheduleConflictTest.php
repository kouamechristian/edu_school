<?php

namespace App\Tests\Validator;

use App\Entity\Classroom;
use App\Entity\Course;
use App\Entity\Level;
use App\Entity\School;
use App\Entity\SchoolYear;
use App\Entity\Subject;
use App\Entity\TimeSlot;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Contrôle des conflits d'emploi du temps (base de test, transaction annulée) :
 * un enseignant PEUT avoir cours dans deux classes au même créneau ; une classe, non.
 */
class NoScheduleConflictTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private ValidatorInterface $validator;
    private School $school;
    private SchoolYear $year;
    private Level $level;
    private Subject $subject;
    private TimeSlot $slot;
    private User $teacher;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $this->em = $container->get(EntityManagerInterface::class);
        $this->validator = $container->get(ValidatorInterface::class);
        $this->em->getConnection()->beginTransaction();

        $this->school = (new School())->setName('École Test EDT')->setType('PRESCOLAIRE-PRIMAIRE');
        $this->year = (new SchoolYear())->setName('2098-2099')
            ->setStartDate(new \DateTime('2098-09-01'))->setEndDate(new \DateTime('2099-07-31'));
        $this->level = (new Level())->setName('CM2 test')->setSchool($this->school);
        $this->subject = (new Subject())->setName('Maths test')->setCode('MTH-T')->setSchool($this->school);
        $this->slot = (new TimeSlot())->setSchool($this->school)->setName('08h-09h')
            ->setStartTime(new \DateTime('08:00'))->setEndTime(new \DateTime('09:00'));
        $this->teacher = (new User())->setUsername('prof.edt.test')->setEmail('prof.edt.test@example.test')
            ->setPassword('x')->setFirstName('Prof')->setLastName('TEST')->setUserType('enseignant');

        foreach ([$this->school, $this->year, $this->level, $this->subject, $this->slot, $this->teacher] as $entity) {
            $this->em->persist($entity);
        }
        $this->em->flush();
    }

    protected function tearDown(): void
    {
        if ($this->em->getConnection()->isTransactionActive()) {
            $this->em->getConnection()->rollBack();
        }
        $this->em->close();
        parent::tearDown();
    }

    private function classroom(string $name): Classroom
    {
        $classroom = (new Classroom())->setName($name)->setCode($name.'-'.uniqid())
            ->setSchool($this->school)->setSchoolYear($this->year)->setLevel($this->level);
        $this->em->persist($classroom);
        $this->em->flush();

        return $classroom;
    }

    private function course(Classroom $classroom): Course
    {
        return (new Course())->setClassroom($classroom)->setSubject($this->subject)
            ->setTeacher($this->teacher)->setTimeSlot($this->slot)->setDayOfWeek('lundi');
    }

    public function testTeacherCanTeachTwoClassroomsAtTheSameTime(): void
    {
        $this->em->persist($this->course($this->classroom('CM2 A')));
        $this->em->flush();

        $errors = $this->validator->validate($this->course($this->classroom('CM2 B')));

        $this->assertCount(0, $errors, (string) $errors);
    }

    public function testClassroomStillCannotHaveTwoCoursesAtTheSameTime(): void
    {
        $classroom = $this->classroom('CM2 C');
        $this->em->persist($this->course($classroom));
        $this->em->flush();

        $errors = $this->validator->validate($this->course($classroom));

        $this->assertCount(1, $errors);
        $this->assertStringContainsString('a déjà un cours', $errors[0]->getMessage());
    }
}
