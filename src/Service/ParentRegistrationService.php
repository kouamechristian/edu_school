<?php

namespace App\Service;

use App\Entity\School;
use App\Entity\Student;
use App\Entity\User;
use App\Repository\StudentRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Auto-inscription d'un parent, partagée par la page web (/parent/inscription)
 * et l'application mobile (POST /api/parent/register).
 *
 * Le parent fournit son identité (nom, prénom, téléphone, mot de passe) et le
 * matricule national de son enfant — déjà connu de l'établissement (ancien élève).
 * Le matricule national relie automatiquement le parent à l'élève
 * (Student.parentUser) et à son établissement (User.schools). Le téléphone sert
 * d'identifiant de connexion.
 */
class ParentRegistrationService
{
    public function __construct(
        private readonly StudentRepository $studentRepository,
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * Crée et enregistre le compte parent relié à l'enfant.
     *
     * @return array{parent: User, child: Student}
     *
     * @throws ParentRegistrationException message destiné au parent
     */
    public function register(string $firstName, string $lastName, string $phone, string $password, string $matriculeNational): array
    {
        $firstName = trim($firstName);
        $lastName = trim($lastName);
        $phone = trim($phone);
        $matriculeNational = trim($matriculeNational);

        if ($error = $this->validate($firstName, $lastName, $phone, $password, $matriculeNational)) {
            throw new ParentRegistrationException($error);
        }

        // L'enfant doit être un élève connu (ancien élève) identifié par son matricule national.
        $child = $this->studentRepository->findOneActiveByMatriculeNational($matriculeNational);

        if (!$child) {
            throw new ParentRegistrationException("Aucun élève actif ne correspond à ce matricule national. Vérifiez le matricule auprès du secrétariat.");
        }
        if (!$this->resolveSchool($child)) {
            throw new ParentRegistrationException("Cet élève n'est rattaché à aucun établissement. Contactez le secrétariat.");
        }
        if ($this->userRepository->findParentOfStudent($child) !== null) {
            throw new ParentRegistrationException("Cet élève est déjà rattaché à un compte parent. Si vous pensez qu'il s'agit d'une erreur, contactez le secrétariat.");
        }
        if ($this->userRepository->findOneBy(['username' => $phone]) !== null) {
            throw new ParentRegistrationException("Ce numéro de téléphone est déjà utilisé par un compte. Connectez-vous ou utilisez un autre numéro.");
        }

        $parent = $this->createParent($firstName, $lastName, $phone, $password, $child);
        $this->em->persist($parent);
        $this->em->flush();

        return ['parent' => $parent, 'child' => $child];
    }

    /**
     * Valide les champs du formulaire ; retourne le message d'erreur ou null.
     */
    private function validate(string $firstName, string $lastName, string $phone, string $password, string $matriculeNational): ?string
    {
        if ($firstName === '' || $lastName === '' || $phone === '' || $password === '' || $matriculeNational === '') {
            return 'Veuillez renseigner tous les champs.';
        }

        if (mb_strlen($password) < 6) {
            return 'Le mot de passe doit contenir au moins 6 caractères.';
        }

        // Le téléphone sert d'identifiant : exactement 10 chiffres.
        if (!preg_match('/^\d{10}$/', $phone)) {
            return 'Le numéro de téléphone doit contenir exactement 10 chiffres.';
        }

        return null;
    }

    /**
     * Construit le compte parent relié à l'enfant et à son établissement.
     */
    private function createParent(string $firstName, string $lastName, string $phone, string $password, Student $child): User
    {
        $parent = new User();
        $parent->setUsername($phone);
        $parent->setEmail($this->generateTechnicalEmail($phone));
        $parent->setFirstName($firstName);
        $parent->setLastName($lastName);
        $parent->setPhone($phone);
        $parent->setUserType('parent');
        $parent->setRoles(['ROLE_PARENT']);
        $parent->setPassword($this->passwordHasher->hashPassword($parent, $password));

        // Liaison parent → enfant (pose Student.parentUser) et parent → établissement.
        $parent->addChild($child);
        if ($school = $this->resolveSchool($child)) {
            $parent->addSchool($school);
            $parent->setLastSchool($school);
        }

        return $parent;
    }

    /**
     * Établissement de l'élève, avec repli sur la classe puis le niveau.
     */
    private function resolveSchool(Student $child): ?School
    {
        return $child->getSchool()
            ?? $child->getClassroom()?->getSchool()
            ?? $child->getLevel()?->getSchool();
    }

    /**
     * Email technique unique dérivé du téléphone : la colonne email est NOT NULL
     * et unique, mais le parent s'identifie par son téléphone (username), pas par
     * un email réel.
     */
    private function generateTechnicalEmail(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?: bin2hex(random_bytes(4));

        return sprintf('parent.%s@auto.edu-school.local', $digits);
    }
}
