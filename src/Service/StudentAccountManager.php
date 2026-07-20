<?php

namespace App\Service;

use App\Entity\Student;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Crée et rattache, de façon idempotente, le compte de connexion d'un élève
 * (espace élève). L'élève se connecte par son matricule national + sa date de
 * naissance : le compte User ne sert qu'à porter le rôle ROLE_ELEVE et l'identité.
 *
 * Appelé à l'inscription (EnrollmentService) et, en repli, à la première connexion
 * réussie (StudentAuthenticator) pour les élèves déjà inscrits avant la fonctionnalité.
 */
class StudentAccountManager
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    /**
     * Garantit l'existence du compte de l'élève et le retourne.
     *
     * Retourne null si l'élève ne dispose pas des informations minimales requises
     * (matricule national + date de naissance), qui sont aussi ses identifiants de
     * connexion.
     */
    public function ensureAccount(Student $student): ?User
    {
        if ($student->getStudentUser() instanceof User) {
            return $student->getStudentUser();
        }

        $matricule = trim((string) $student->getMatriculeNational());
        if ($matricule === '' || !$student->getDateOfBirth()) {
            return null;
        }

        // Un compte peut déjà exister (username = matricule national) sans être lié
        // à la fiche : on le réutilise pour éviter tout doublon.
        $user = $this->entityManager->getRepository(User::class)
            ->findOneBy(['username' => $matricule]);

        if (!$user instanceof User) {
            $user = new User();
            $user->setUsername($matricule);
            // Email synthétique unique : la colonne est NOT NULL et unique, mais
            // l'élève ne se connecte jamais par e-mail.
            $user->setEmail(sprintf('eleve.%s@edu-school.local', $matricule));
            $user->setFirstName($student->getFirstName());
            $user->setLastName($student->getLastName());
            $user->setDateOfBirth($student->getDateOfBirth());
            $user->setGender($student->getGender());
            $user->setUserType('eleve');
            $user->setRoles(['ROLE_ELEVE']);
            $user->setIsActive(true);
            // Mot de passe aléatoire non communiqué : l'authentification élève repose
            // sur matricule + date de naissance, pas sur ce mot de passe.
            $user->setPassword($this->passwordHasher->hashPassword($user, bin2hex(random_bytes(16))));

            $this->entityManager->persist($user);
        }

        $student->setStudentUser($user);
        $this->entityManager->flush();

        return $user;
    }
}
