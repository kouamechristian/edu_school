<?php

namespace App\Tests\Service;

use App\Entity\School;
use App\Entity\Student;
use App\Entity\User;
use App\Service\ParentAccountGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Test d'intégration (base de test) : chaque test s'exécute dans une transaction annulée à la fin.
 */
class ParentAccountGeneratorTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private ParentAccountGenerator $generator;
    private School $school;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $this->em = $container->get(EntityManagerInterface::class);
        $this->generator = $container->get(ParentAccountGenerator::class);

        $this->em->getConnection()->beginTransaction();

        $this->school = (new School())
            ->setName('École Test Génération')
            ->setType('PRESCOLAIRE-PRIMAIRE');
        $this->em->persist($this->school);
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

    private function student(string $lastName, string $firstName, ?string $authority): Student
    {
        $student = (new Student())
            ->setLastName($lastName)
            ->setFirstName($firstName)
            ->setSchool($this->school)
            ->setParentalAuthority($authority);
        $this->em->persist($student);

        return $student;
    }

    public function testFatherAuthorityCreatesFatherAccountLinkedToChild(): void
    {
        $child = $this->student('KOUASSI', 'Awa', 'father')
            ->setFatherLastName('KOUASSI')->setFatherFirstName('Jean Marc')->setFatherPhone('0707070707')
            ->setMotherLastName('TOURE')->setMotherFirstName('Mariam');
        $this->em->flush();

        $result = $this->generator->generate($this->school);

        $this->assertCount(1, $result['created']);
        $parent = $result['created'][0]['parent'];
        $this->assertSame('KOUASSI', $parent->getLastName());
        $this->assertSame('Jean Marc', $parent->getFirstName());
        $this->assertSame('jean.kouassi', $parent->getUsername());
        $this->assertSame('0707070707', $parent->getPhone());
        $this->assertSame(['ROLE_PARENT', 'ROLE_USER'], $parent->getRoles());
        $this->assertTrue($parent->isMustChangePassword());
        $this->assertSame($parent, $child->getParentUser());
        $this->assertTrue($parent->getSchools()->contains($this->school));

        $password = $result['created'][0]['password'];
        $this->assertSame(8, strlen($password));
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $this->assertTrue($hasher->isPasswordValid($parent, $password));
    }

    public function testMotherAuthorityUsesMotherInformation(): void
    {
        $this->student('KONE', 'Ibrahim', 'mother')
            ->setFatherLastName('KONE')->setFatherFirstName('Seydou')
            ->setMotherLastName('BAMBA')->setMotherFirstName('Fatou')->setMotherPhone('0505050505');
        $this->em->flush();

        $result = $this->generator->generate($this->school);

        $this->assertCount(1, $result['created']);
        $this->assertSame('BAMBA', $result['created'][0]['parent']->getLastName());
        $this->assertSame('Fatou', $result['created'][0]['parent']->getFirstName());
        $this->assertSame('fatou.bamba', $result['created'][0]['parent']->getUsername());
    }

    public function testSiblingsShareOneAccountAndHomonymsGetUniqueUsernames(): void
    {
        $a = $this->student('DIALLO', 'Aminata', 'father')
            ->setFatherLastName('DIALLO')->setFatherFirstName('Moussa')->setFatherPhone('0101010101');
        $b = $this->student('DIALLO', 'Oumar', 'father')
            ->setFatherLastName('Diallo')->setFatherFirstName('Moussa')->setFatherPhone('01 01 01 01 01');
        // Homonyme, autre téléphone : autre parent.
        $c = $this->student('DIALLO', 'Ali', 'father')
            ->setFatherLastName('DIALLO')->setFatherFirstName('Moussa')->setFatherPhone('0202020202');
        $this->em->flush();

        $result = $this->generator->generate($this->school);

        $this->assertCount(2, $result['created']);
        $this->assertSame($a->getParentUser(), $b->getParentUser());
        $this->assertNotSame($a->getParentUser(), $c->getParentUser());
        $this->assertNotSame($a->getParentUser()->getUsername(), $c->getParentUser()->getUsername());
    }

    public function testExistingParentAccountIsReusedAndStudentsWithoutDataAreSkipped(): void
    {
        $existing = (new User())
            ->setUsername('0303030303')
            ->setEmail('parent.0303030303@auto.edu-school.local')
            ->setFirstName('Paul')
            ->setLastName('YAO')
            ->setPhone('0303030303')
            ->setUserType('parent')
            ->setRoles(['ROLE_PARENT'])
            ->setPassword('x');
        $existing->addSchool($this->school);
        $this->em->persist($existing);

        $child = $this->student('YAO', 'Esther', 'both')
            ->setFatherLastName('YAO')->setFatherFirstName('Paul')->setFatherPhone('0303030303');
        $incomplete = $this->student('N\'GUESSAN', 'Koffi', 'mother')
            ->setFatherLastName('N\'GUESSAN')->setFatherFirstName('Eric');
        $noAuthority = $this->student('SORO', 'Lacina', null)
            ->setFatherLastName('SORO')->setFatherFirstName('Adama');
        $this->em->flush();

        $result = $this->generator->generate($this->school);

        $this->assertCount(0, $result['created']);
        $this->assertCount(1, $result['linked']);
        $this->assertSame($existing, $child->getParentUser());
        $this->assertCount(1, $result['skipped']);
        $this->assertSame($incomplete, $result['skipped'][0]['student']);
        $this->assertNull($noAuthority->getParentUser());

        // Deuxième exécution : plus rien à faire, aucun doublon.
        $again = $this->generator->generate($this->school);
        $this->assertCount(0, $again['created']);
        $this->assertCount(0, $again['linked']);
    }

    public function testAccessSheetResetsOnlyNotActivatedAccounts(): void
    {
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        // Parent jamais activé, deux enfants.
        $a = $this->student('DIALLO', 'Aminata', 'father')->setFatherLastName('DIALLO')->setFatherFirstName('Moussa');
        $b = $this->student('DIALLO', 'Oumar', 'father')->setFatherLastName('DIALLO')->setFatherFirstName('Moussa');
        $this->em->flush();
        $created = $this->generator->generate($this->school)['created'][0];
        $oldPassword = $created['password'];

        // Parent ayant déjà choisi son mot de passe.
        $active = (new User())
            ->setUsername('paul.yao.test')
            ->setEmail('paul.yao.test@example.test')
            ->setFirstName('Paul')->setLastName('YAO')
            ->setUserType('parent')->setRoles(['ROLE_PARENT'])
            ->setMustChangePassword(false);
        $active->setPassword($hasher->hashPassword($active, 'monMotDePasse'));
        $active->addSchool($this->school);
        $this->em->persist($active);
        $c = $this->student('YAO', 'Esther', null);
        $active->addChild($c);
        // Élève sans parent : absent de la fiche.
        $this->student('SORO', 'Lacina', null);
        $this->em->flush();

        $rows = $this->generator->buildAccessSheet($this->school);

        $this->assertCount(3, $rows);
        $byStudent = [];
        foreach ($rows as $row) {
            $byStudent[$row['student']->getFirstName()] = $row;
        }

        // Frères et sœurs : même nouveau mot de passe, valide, différent de l'ancien.
        $this->assertNotNull($byStudent['Aminata']['password']);
        $this->assertSame($byStudent['Aminata']['password'], $byStudent['Oumar']['password']);
        $this->assertNotSame($oldPassword, $byStudent['Aminata']['password']);
        $this->assertTrue($hasher->isPasswordValid($created['parent'], $byStudent['Aminata']['password']));
        $this->assertFalse($hasher->isPasswordValid($created['parent'], $oldPassword));
        $this->assertTrue($created['parent']->isMustChangePassword());

        // Compte activé : rien d'imprimé, mot de passe inchangé.
        $this->assertNull($byStudent['Esther']['password']);
        $this->assertTrue($hasher->isPasswordValid($active, 'monMotDePasse'));
    }
}
