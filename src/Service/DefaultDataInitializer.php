<?php

namespace App\Service;

use App\Entity\School;
use App\Entity\SchoolGroup;
use App\Entity\SchoolYear;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Initialise les données minimales nécessaires au démarrage de l'application
 * lorsque la base est vide : un groupe d'établissement, un établissement et un
 * compte SUPER_ADMIN par défaut.
 *
 * Identifiants par défaut : superadmin / ChangeMe2026!
 * (à modifier impérativement après la première connexion).
 */
class DefaultDataInitializer
{
    public const DEFAULT_USERNAME = 'superadmin';
    public const DEFAULT_PASSWORD = 'ChangeMe2026!';
    public const DEFAULT_EMAIL = 'admin@edu-school.local';

    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher
    ) {
    }

    /**
     * Indique si la base est « vide » du point de vue applicatif :
     * aucun utilisateur n'existe encore.
     */
    public function isDatabaseEmpty(): bool
    {
        $count = (int) $this->entityManager
            ->getRepository(User::class)
            ->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->getQuery()
            ->getSingleScalarResult();

        return $count === 0;
    }

    /**
     * Crée les données par défaut si la base est vide.
     *
     * @return bool true si les données ont été créées, false si la base
     *              contenait déjà au moins un utilisateur.
     */
    public function initializeIfEmpty(): bool
    {
        if (!$this->isDatabaseEmpty()) {
            return false;
        }

        // 1. Groupe d'établissement
        $group = new SchoolGroup();
        $group->setName('Groupe scolaire par défaut');
        $group->setCode('GRP-001');
        $group->setDescription('Groupe créé automatiquement lors de l\'initialisation.');
        $group->setIsActive(true);

        // 2. Établissement rattaché au groupe
        $school = new School();
        $school->setName('Établissement par défaut');
        $school->setCode('ETS-001');
        $school->setType('primaire');
        $school->setIsActive(true);
        $group->addSchool($school);

        // 3. Année scolaire courante (sept. → juil.), marquée « en cours » afin
        //    que le sélecteur d'année du header soit immédiatement disponible.
        $schoolYear = $this->createCurrentSchoolYear();

        // 4. Compte SUPER_ADMIN
        $user = new User();
        $user->setUsername(self::DEFAULT_USERNAME)
            ->setEmail(self::DEFAULT_EMAIL)
            ->setPassword($this->passwordHasher->hashPassword($user, self::DEFAULT_PASSWORD))
            ->setRoles(['ROLE_SUPER_ADMIN'])
            ->setUserType('admin')
            ->setFirstName('Super')
            ->setLastName('Admin')
            ->setIsActive(true)
            ->setMustChangePassword(true)
            ->setSchoolGroup($group);
        $user->addSchool($school);

        $this->entityManager->persist($group);
        $this->entityManager->persist($school);
        $this->entityManager->persist($schoolYear);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return true;
    }

    /**
     * Construit l'année scolaire courante en fonction de la date du jour.
     *
     * Convention : l'année démarre en septembre. Avant septembre, on est encore
     * dans l'année scolaire commencée l'année civile précédente.
     */
    private function createCurrentSchoolYear(): SchoolYear
    {
        $now = new \DateTime();
        $startYear = (int) $now->format('n') >= 9
            ? (int) $now->format('Y')
            : (int) $now->format('Y') - 1;
        $endYear = $startYear + 1;

        $schoolYear = new SchoolYear();
        $schoolYear->setName($startYear . '-' . $endYear);
        $schoolYear->setStartDate(new \DateTime($startYear . '-09-01'));
        $schoolYear->setEndDate(new \DateTime($endYear . '-07-31'));
        $schoolYear->setIsCurrent(true);

        return $schoolYear;
    }
}
