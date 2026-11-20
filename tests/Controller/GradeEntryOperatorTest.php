<?php

namespace App\Tests\Controller;

use App\Entity\Classroom;
use App\Entity\Evaluation;
use App\Entity\Level;
use App\Entity\Period;
use App\Entity\School;
use App\Entity\SchoolYear;
use App\Entity\Subject;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * ROLE_OPERATEUR_DE_SAISIR : saisit les notes des évaluations d'un enseignant,
 * sans pouvoir publier ni supprimer (base de test, transaction annulée).
 */
class GradeEntryOperatorTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private School $school;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->em->getConnection()->beginTransaction();

        $this->school = (new School())->setName('École Saisie Test')->setType('PRESCOLAIRE-PRIMAIRE');
        $this->em->persist($this->school);
    }

    protected function tearDown(): void
    {
        if ($this->em->getConnection()->isTransactionActive()) {
            $this->em->getConnection()->rollBack();
        }
        parent::tearDown();
    }

    private function user(string $username, string $type, array $roles): User
    {
        $user = (new User())->setUsername($username)->setEmail($username.'@example.test')
            ->setFirstName('Test')->setLastName(strtoupper($username))
            ->setUserType($type)->setRoles($roles)->setPassword('x');
        $user->addSchool($this->school);
        $this->em->persist($user);
        // Un flush par compte : UserEmployeeSubscriber crée la fiche employé en postPersist.
        $this->em->flush();

        return $user;
    }

    private function evaluationOf(User $teacher): Evaluation
    {
        $year = (new SchoolYear())->setName('2097-2098')
            ->setStartDate(new \DateTime('2097-09-01'))->setEndDate(new \DateTime('2098-07-31'));
        $level = (new Level())->setName('6e test')->setSchool($this->school);
        $classroom = (new Classroom())->setName('6e A test')->setCode('6A-'.uniqid())
            ->setSchool($this->school)->setSchoolYear($year)->setLevel($level);
        $subject = (new Subject())->setName('Français test')->setCode('FR-T')->setSchool($this->school);
        $period = (new Period())->setSchool($this->school)->setSchoolYear($year)->setName('1er trimestre')
            ->setCode('T1')->setOrderNumber(1)
            ->setStartDate(new \DateTime('2097-09-01'))->setEndDate(new \DateTime('2097-12-20'));
        $evaluation = (new Evaluation())->setClassroom($classroom)->setSubject($subject)->setPeriod($period)
            ->setTeacher($teacher)->setName('Dictée 1')->setType('devoir_surveille')
            ->setDate(new \DateTime('2097-10-10'));

        foreach ([$year, $level, $classroom, $subject, $period, $evaluation] as $entity) {
            $this->em->persist($entity);
        }

        return $evaluation;
    }

    public function testOperatorEntersGradesForATeacherButCannotPublish(): void
    {
        $teacher = $this->user('prof.saisie.test', 'enseignant', ['ROLE_ENSEIGNANT']);
        $operator = $this->user('operateur.saisie.test', 'personnel', ['ROLE_OPERATEUR_DE_SAISIR']);
        $evaluation = $this->evaluationOf($teacher);
        $this->em->flush();
        $id = $evaluation->getId();
        // Repartir d'entités rechargées, comme une vraie requête (fiche employé incluse).
        $this->em->clear();

        $this->client->loginUser($operator);

        $this->client->request('GET', '/admin/evaluations/');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('a[href$="/admin/evaluations/"]', 'Entrée « Saisie des notes » dans le menu');

        $this->client->request('GET', '/admin/evaluations/new');
        $this->assertResponseIsSuccessful();

        $this->client->request('GET', "/admin/evaluations/$id/grades");
        $this->assertResponseIsSuccessful();
        $this->client->request('POST', "/admin/evaluations/$id/grades", ['grades' => []]);
        $this->assertResponseRedirects("/admin/evaluations/$id/grades");
        $this->assertTrue($this->em->find(Evaluation::class, $id)->isValidated());

        $crawler = $this->client->request('GET', "/admin/evaluations/$id/show");
        $this->assertResponseIsSuccessful();
        $this->assertCount(0, $crawler->filter('form[action$="/publish"]'), 'Pas de bouton Publier');

        $this->client->request('POST', "/admin/evaluations/$id/publish");
        $this->assertResponseStatusCodeSame(403);
    }

    public function testStaffWithoutRoleCannotEnterGrades(): void
    {
        $accountant = $this->user('compta.saisie.test', 'personnel', ['ROLE_COMPTABLE']);
        $this->em->clear();
        $this->client->loginUser($accountant);

        $this->client->request('GET', '/admin/evaluations/');
        $this->assertResponseStatusCodeSame(403);
    }
}
