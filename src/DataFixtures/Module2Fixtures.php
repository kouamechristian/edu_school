<?php

namespace App\DataFixtures;

use App\Entity\School;
use App\Entity\SchoolGroup;
use App\Entity\User;
use App\Repository\SchoolRepository;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class Module2Fixtures extends Fixture implements DependentFixtureInterface
{
    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(UserPasswordHasherInterface $passwordHasher)
    {
        $this->passwordHasher = $passwordHasher;
    }

    public function getDependencies(): array
    {
        return [
            Module1Fixtures::class,
        ];
    }

    public function load(ObjectManager $manager): void
    {
        // Récupérer les établissements créés par Module1Fixtures
        $schools = $manager->getRepository(School::class)->findAll();
        $groups = $manager->getRepository(SchoolGroup::class)->findAll();

        if (empty($schools)) {
            return; // Pas d'écoles, on ne peut pas créer les utilisateurs
        }

        // Créer des utilisateurs de test
        $this->createAdminUsers($manager, $schools, $groups);
        $manager->flush(); // Flush après chaque groupe
        
        $this->createDirecteurs($manager, $schools, $groups);
        $manager->flush();
        
        $this->createEnseignants($manager, $schools, $groups);
        $manager->flush();
        
        $this->createPersonnel($manager, $schools, $groups);
        $manager->flush();
        
        $this->createEleves($manager, $schools, $groups);
        $manager->flush();
        
        $this->createParents($manager, $schools, $groups);
        $manager->flush();
    }

    private function createAdminUsers(ObjectManager $manager, array $schools, array $groups): void
    {
        // Super Admin - Accès à tous les établissements
        $superAdmin = new User();
        $superAdmin->setUsername('superadmin')
            ->setEmail('superadmin@edu-school.com')
            ->setPassword($this->passwordHasher->hashPassword($superAdmin, 'Admin@123'))
            ->setRoles(['ROLE_SUPER_ADMIN'])
            ->setFirstName('Super')
            ->setLastName('ADMIN')
            ->setUserType('admin')
            ->setIsActive(true);
        
        // Lier à tous les établissements (pas de groupe pour super admin)
        foreach ($schools as $school) {
            $superAdmin->addSchool($school);
        }
        $manager->persist($superAdmin);

        // Admin - Accès à tous les établissements
        $admin = new User();
        $admin->setUsername('admin')
            ->setEmail('admin@edu-school.com')
            ->setPassword($this->passwordHasher->hashPassword($admin, 'Admin@123'))
            ->setRoles(['ROLE_ADMIN'])
            ->setFirstName('Jean')
            ->setLastName('ADMIN')
            ->setUserType('admin')
            ->setPhone('01 23 45 67 89')
            ->setIsActive(true);
        
        // Lier à tous les établissements (pas de groupe pour admin général)
        foreach ($schools as $school) {
            $admin->addSchool($school);
        }
        $manager->persist($admin);
    }

    private function createDirecteurs(ObjectManager $manager, array $schools, array $groups): void
    {
        $directeurs = [
            ['username' => 'directeur1', 'firstName' => 'Marie', 'lastName' => 'DUPONT', 'email' => 'marie.dupont@edu-school.com'],
            ['username' => 'directeur2', 'firstName' => 'Pierre', 'lastName' => 'MARTIN', 'email' => 'pierre.martin@edu-school.com'],
        ];

        foreach ($directeurs as $index => $data) {
            $user = new User();
            $user->setUsername($data['username'])
                ->setEmail($data['email'])
                ->setPassword($this->passwordHasher->hashPassword($user, 'Password@123'))
                ->setRoles(['ROLE_ADMIN'])
                ->setFirstName($data['firstName'])
                ->setLastName($data['lastName'])
                ->setUserType('directeur')
                ->setPhone('06 12 34 56 78')
                ->setGender($data['firstName'] == 'Marie' ? 'F' : 'M')
                ->setIsActive(true);
            
            // Lier à un établissement spécifique et son groupe
            if (isset($schools[$index])) {
                $user->addSchool($schools[$index]);
                // Lier au groupe de l'école
                if ($schools[$index]->getSchoolGroup()) {
                    $user->setSchoolGroup($schools[$index]->getSchoolGroup());
                }
            }
            $manager->persist($user);
        }
    }

    private function createEnseignants(ObjectManager $manager, array $schools, array $groups): void
    {
        $enseignants = [
            ['username' => 'jmartin', 'firstName' => 'Jean', 'lastName' => 'MARTIN', 'email' => 'jean.martin@edu-school.com', 'gender' => 'M'],
            ['username' => 'sdupre', 'firstName' => 'Sophie', 'lastName' => 'DUPRÉ', 'email' => 'sophie.dupre@edu-school.com', 'gender' => 'F'],
            ['username' => 'pbernard', 'firstName' => 'Paul', 'lastName' => 'BERNARD', 'email' => 'paul.bernard@edu-school.com', 'gender' => 'M'],
            ['username' => 'mleroy', 'firstName' => 'Marie', 'lastName' => 'LEROY', 'email' => 'marie.leroy@edu-school.com', 'gender' => 'F'],
            ['username' => 'lblanc', 'firstName' => 'Luc', 'lastName' => 'BLANC', 'email' => 'luc.blanc@edu-school.com', 'gender' => 'M'],
        ];

        foreach ($enseignants as $index => $data) {
            $user = new User();
            $user->setUsername($data['username'])
                ->setEmail($data['email'])
                ->setPassword($this->passwordHasher->hashPassword($user, 'Teacher@123'))
                ->setRoles(['ROLE_MODIFICATION'])
                ->setFirstName($data['firstName'])
                ->setLastName($data['lastName'])
                ->setUserType('enseignant')
                ->setPhone('06 ' . rand(10, 99) . ' ' . rand(10, 99) . ' ' . rand(10, 99) . ' ' . rand(10, 99))
                ->setGender($data['gender'])
                ->setDateOfBirth(new \DateTime(rand(1970, 1990).'-'.rand(1,12).'-'.rand(1,28)))
                ->setIsActive(true);
            
            // Lier à 1-2 établissements
            if (isset($schools[$index % count($schools)])) {
                $user->addSchool($schools[$index % count($schools)]);
            }
            $manager->persist($user);
        }
    }

    private function createPersonnel(ObjectManager $manager, array $schools, array $groups): void
    {
        $personnel = [
            ['username' => 'secretaire1', 'firstName' => 'Anne', 'lastName' => 'PETIT', 'email' => 'anne.petit@edu-school.com', 'gender' => 'F'],
            ['username' => 'comptable1', 'firstName' => 'Thomas', 'lastName' => 'MOREAU', 'email' => 'thomas.moreau@edu-school.com', 'gender' => 'M'],
        ];

        foreach ($personnel as $index => $data) {
            $user = new User();
            $user->setUsername($data['username'])
                ->setEmail($data['email'])
                ->setPassword($this->passwordHasher->hashPassword($user, 'Staff@123'))
                ->setRoles(['ROLE_SAISIE'])
                ->setFirstName($data['firstName'])
                ->setLastName($data['lastName'])
                ->setUserType('personnel')
                ->setPhone('06 ' . rand(10, 99) . ' ' . rand(10, 99) . ' ' . rand(10, 99) . ' ' . rand(10, 99))
                ->setGender($data['gender'])
                ->setIsActive(true);
            
            // Lier au premier établissement
            if (isset($schools[0])) {
                $user->addSchool($schools[0]);
            }
            $manager->persist($user);
        }
    }

    private function createEleves(ObjectManager $manager, array $schools, array $groups): void
    {
        $prenoms = ['Alexandre', 'Camille', 'Lucas', 'Emma', 'Hugo', 'Léa', 'Louis', 'Chloé', 'Gabriel', 'Sarah'];
        $noms = ['DUBOIS', 'THOMAS', 'ROBERT', 'RICHARD', 'PETIT', 'DURAND', 'LEROY', 'MOREAU', 'SIMON', 'LAURENT'];

        for ($i = 0; $i < 10; $i++) {
            $firstName = $prenoms[$i];
            $lastName = $noms[$i];
            $gender = in_array($firstName, ['Camille', 'Emma', 'Léa', 'Chloé', 'Sarah']) ? 'F' : 'M';

            $user = new User();
            $user->setUsername(strtolower($firstName.'.'.$lastName))
                ->setEmail(strtolower($firstName.'.'.$lastName).'@student.edu-school.com')
                ->setPassword($this->passwordHasher->hashPassword($user, 'Student@123'))
                ->setRoles(['ROLE_USER'])
                ->setFirstName($firstName)
                ->setLastName($lastName)
                ->setUserType('eleve')
                ->setPhone('07 ' . rand(10, 99) . ' ' . rand(10, 99) . ' ' . rand(10, 99) . ' ' . rand(10, 99))
                ->setGender($gender)
                ->setDateOfBirth(new \DateTime(rand(2010, 2015).'-'.rand(1,12).'-'.rand(1,28)))
                ->setIsActive(true);
            
            // Lier à un établissement (rotation)
            if (isset($schools[$i % count($schools)])) {
                $user->addSchool($schools[$i % count($schools)]);
            }
            $manager->persist($user);
        }
    }

    private function createParents(ObjectManager $manager, array $schools, array $groups): void
    {
        $parents = [
            ['username' => 'parent1', 'firstName' => 'Jacques', 'lastName' => 'DUBOIS', 'email' => 'jacques.dubois@parent.edu-school.com', 'gender' => 'M'],
            ['username' => 'parent2', 'firstName' => 'Christine', 'lastName' => 'THOMAS', 'email' => 'christine.thomas@parent.edu-school.com', 'gender' => 'F'],
            ['username' => 'parent3', 'firstName' => 'Michel', 'lastName' => 'ROBERT', 'email' => 'michel.robert@parent.edu-school.com', 'gender' => 'M'],
        ];

        foreach ($parents as $index => $data) {
            $user = new User();
            $user->setUsername($data['username'])
                ->setEmail($data['email'])
                ->setPassword($this->passwordHasher->hashPassword($user, 'Parent@123'))
                ->setRoles(['ROLE_USER'])
                ->setFirstName($data['firstName'])
                ->setLastName($data['lastName'])
                ->setUserType('parent')
                ->setPhone('06 ' . rand(10, 99) . ' ' . rand(10, 99) . ' ' . rand(10, 99) . ' ' . rand(10, 99))
                ->setGender($data['gender'])
                ->setIsActive(true);
            
            // Lier au premier établissement
            if (isset($schools[0])) {
                $user->addSchool($schools[0]);
            }
            $manager->persist($user);
        }
    }
}

