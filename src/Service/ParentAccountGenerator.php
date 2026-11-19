<?php

namespace App\Service;

use App\Entity\School;
use App\Entity\Student;
use App\Entity\User;
use App\Repository\StudentRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\String\Slugger\AsciiSlugger;

/**
 * Génère en masse les comptes parents à partir des fiches élèves.
 *
 * Pour chaque élève actif de l'établissement, sans compte parent rattaché et dont
 * le champ « Autorité parentale » est renseigné, le parent retenu est :
 *  - « Père »  → les informations du père ;
 *  - « Mère »  → les informations de la mère ;
 *  - « Les deux parents » → le père, ou la mère si le nom du père est absent
 *    (un élève ne peut être rattaché qu'à UN compte parent, cf. Student::$parentUser).
 *
 * Les frères et sœurs d'un même parent (même nom, prénom et téléphone) partagent
 * un seul compte. Un compte parent déjà existant dans l'établissement qui
 * correspond est réutilisé au lieu d'en créer un doublon.
 *
 * Le mot de passe généré n'est restitué qu'une seule fois (valeur de retour) :
 * il est haché en base et son changement est imposé à la première connexion.
 */
class ParentAccountGenerator
{
    /** Nombre maximal de comptes créés par exécution (hachage coûteux, ~0,4 s chacun). */
    public const BATCH_LIMIT = 100;

    /** Caractères sans ambiguïté de lecture (pas de 0/O, 1/l/I). */
    private const PASSWORD_ALPHABET = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    private const PASSWORD_LENGTH = 8;

    private AsciiSlugger $slugger;

    /** @var array<string, true> identifiants réservés pendant l'exécution */
    private array $reservedUsernames = [];

    public function __construct(
        private readonly StudentRepository $studentRepository,
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly EntityManagerInterface $em,
    ) {
        $this->slugger = new AsciiSlugger('fr');
    }

    /**
     * Élèves éligibles de l'établissement : actifs, sans compte parent, autorité parentale renseignée.
     *
     * @return Student[]
     */
    public function findEligibleStudents(School $school): array
    {
        return $this->studentRepository->createQueryBuilder('s')
            ->andWhere('s.school = :school')
            ->andWhere('s.isActive = true')
            ->andWhere('s.parentUser IS NULL')
            ->andWhere('s.parentalAuthority IS NOT NULL')
            ->andWhere("s.parentalAuthority <> ''")
            ->setParameter('school', $school)
            ->orderBy('s.lastName', 'ASC')
            ->addOrderBy('s.firstName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array{
     *     created: list<array{parent: User, password: string, children: list<Student>}>,
     *     linked: list<array{parent: User, children: list<Student>}>,
     *     skipped: list<array{student: Student, reason: string}>,
     *     remaining: int
     * }
     */
    public function generate(School $school): array
    {
        $this->reservedUsernames = [];

        /** @var array<string, array{guardian: array{lastName: string, firstName: string, phone: ?string}, children: list<Student>}> $groups */
        $groups = [];
        $skipped = [];

        foreach ($this->findEligibleStudents($school) as $student) {
            $guardian = $this->resolveGuardian($student);
            if ($guardian === null) {
                $skipped[] = [
                    'student' => $student,
                    'reason' => sprintf('Nom et prénom %s non renseignés', $this->authorityLabel($student)),
                ];
                continue;
            }

            $key = $this->guardianKey($guardian);
            $groups[$key] ??= ['guardian' => $guardian, 'children' => []];
            $groups[$key]['children'][] = $student;
        }

        $created = [];
        $linked = [];
        $remaining = 0;

        foreach ($groups as $group) {
            $guardian = $group['guardian'];

            // Compte parent déjà présent dans l'établissement : on y rattache les enfants.
            if ($existing = $this->findExistingParent($school, $guardian)) {
                foreach ($group['children'] as $child) {
                    $existing->addChild($child);
                }
                $linked[] = ['parent' => $existing, 'children' => $group['children']];
                continue;
            }

            if (count($created) >= self::BATCH_LIMIT) {
                $remaining += count($group['children']);
                continue;
            }

            $password = $this->generatePassword();
            $parent = $this->createParent($school, $guardian, $password);
            foreach ($group['children'] as $child) {
                $parent->addChild($child);
            }
            $this->em->persist($parent);

            $created[] = ['parent' => $parent, 'password' => $password, 'children' => $group['children']];
        }

        $this->em->flush();

        return [
            'created' => $created,
            'linked' => $linked,
            'skipped' => $skipped,
            'remaining' => $remaining,
        ];
    }

    /**
     * Identité du parent détenant l'autorité parentale, ou null si incomplète.
     *
     * @return array{lastName: string, firstName: string, phone: ?string}|null
     */
    private function resolveGuardian(Student $student): ?array
    {
        $father = [
            'lastName' => trim((string) $student->getFatherLastName()),
            'firstName' => trim((string) $student->getFatherFirstName()),
            'phone' => $this->normalizePhone($student->getFatherPhone()),
        ];
        $mother = [
            'lastName' => trim((string) $student->getMotherLastName()),
            'firstName' => trim((string) $student->getMotherFirstName()),
            'phone' => $this->normalizePhone($student->getMotherPhone()),
        ];

        $candidate = match ($student->getParentalAuthority()) {
            'father' => $father,
            'mother' => $mother,
            'both' => $this->isComplete($father) ? $father : $mother,
            default => null,
        };

        return $candidate !== null && $this->isComplete($candidate) ? $candidate : null;
    }

    private function isComplete(array $guardian): bool
    {
        return $guardian['lastName'] !== '' && $guardian['firstName'] !== '';
    }

    private function authorityLabel(Student $student): string
    {
        return match ($student->getParentalAuthority()) {
            'father' => 'du père',
            'mother' => 'de la mère',
            default => 'des parents',
        };
    }

    /**
     * Clé de regroupement des frères et sœurs : nom + prénom + téléphone normalisés.
     */
    private function guardianKey(array $guardian): string
    {
        return implode('|', [
            mb_strtolower($this->slugger->slug($guardian['lastName'])->toString()),
            mb_strtolower($this->slugger->slug($guardian['firstName'])->toString()),
            $guardian['phone'] ?? '',
        ]);
    }

    /**
     * Compte parent existant de l'établissement portant le même nom, prénom et téléphone.
     */
    private function findExistingParent(School $school, array $guardian): ?User
    {
        $candidates = $this->userRepository->createQueryBuilder('u')
            ->andWhere('u.roles LIKE :role')
            ->andWhere(':school MEMBER OF u.schools')
            ->andWhere('LOWER(u.lastName) = :lastName')
            ->andWhere('LOWER(u.firstName) = :firstName')
            ->setParameter('role', '%"ROLE_PARENT"%')
            ->setParameter('school', $school)
            ->setParameter('lastName', mb_strtolower($guardian['lastName']))
            ->setParameter('firstName', mb_strtolower($guardian['firstName']))
            ->getQuery()
            ->getResult();

        foreach ($candidates as $user) {
            if ($this->normalizePhone($user->getPhone()) === $guardian['phone']) {
                return $user;
            }
        }

        return null;
    }

    private function createParent(School $school, array $guardian, string $password): User
    {
        $username = $this->generateUsername($guardian['firstName'], $guardian['lastName']);

        $parent = new User();
        $parent->setUsername($username);
        // La colonne email est NOT NULL et unique : e-mail technique dérivé de l'identifiant.
        $parent->setEmail(sprintf('parent.%s@auto.edu-school.local', $username));
        $parent->setFirstName($guardian['firstName']);
        $parent->setLastName($guardian['lastName']);
        $parent->setPhone($guardian['phone']);
        $parent->setUserType('parent');
        $parent->setRoles(['ROLE_PARENT']);
        $parent->setIsActive(true);
        $parent->setPassword($this->passwordHasher->hashPassword($parent, $password));
        $parent->setMustChangePassword(true);
        $parent->addSchool($school);
        $parent->setLastSchool($school);

        return $parent;
    }

    /**
     * Identifiant unique de la forme « prenom.nom », suffixé d'un numéro en cas de doublon.
     */
    private function generateUsername(string $firstName, string $lastName): string
    {
        // Premier prénom seulement (« Jean Marc » → « jean »).
        $firstWord = preg_split('/[\s\-]+/u', trim($firstName))[0] ?? '';
        $first = mb_strtolower($this->slugger->slug($firstWord, '')->toString()) ?: 'parent';
        $last = mb_strtolower($this->slugger->slug($lastName, '')->toString()) ?: 'parent';
        $base = substr($first . '.' . $last, 0, 60);

        $username = $base;
        $suffix = 2;
        while (isset($this->reservedUsernames[$username])
            || $this->userRepository->findOneBy(['username' => $username]) !== null
            || $this->userRepository->findOneBy(['email' => sprintf('parent.%s@auto.edu-school.local', $username)]) !== null
        ) {
            $username = $base . $suffix++;
        }

        $this->reservedUsernames[$username] = true;

        return $username;
    }

    /**
     * Fiche d'accès imprimable : une ligne par élève actif de l'établissement
     * rattaché à un compte parent actif, triée par classe puis par nom.
     *
     * Les comptes jamais activés (mot de passe provisoire encore en vigueur,
     * mustChangePassword) reçoivent un NOUVEAU mot de passe provisoire, imprimé sur
     * la fiche : l'ancien, haché, ne peut pas être relu. Les comptes déjà activés
     * gardent le mot de passe choisi par le parent (password = null sur la ligne).
     * Un parent de plusieurs enfants n'a qu'un seul nouveau mot de passe.
     *
     * @return list<array{student: Student, classroom: string, parent: User, password: ?string}>
     */
    public function buildAccessSheet(School $school): array
    {
        /** @var Student[] $students */
        $students = $this->studentRepository->createQueryBuilder('s')
            ->innerJoin('s.parentUser', 'p')
            ->addSelect('p')
            ->andWhere('s.school = :school')
            ->andWhere('s.isActive = true')
            ->andWhere('p.isActive = true')
            ->setParameter('school', $school)
            ->getQuery()
            ->getResult();

        $rows = [];
        $passwords = []; // id parent => nouveau mot de passe (partagé entre frères et sœurs)
        foreach ($students as $student) {
            $parent = $student->getParentUser();
            if ($parent->isMustChangePassword() && !array_key_exists($parent->getId(), $passwords)) {
                $passwords[$parent->getId()] = $this->generatePassword();
                $parent->setPassword($this->passwordHasher->hashPassword($parent, $passwords[$parent->getId()]));
            }

            $rows[] = [
                'student' => $student,
                'classroom' => $student->getClassroom()?->getName() ?? 'Classe non affectée',
                'parent' => $parent,
                'password' => $passwords[$parent->getId()] ?? null,
            ];
        }

        $this->em->flush();

        usort($rows, static fn (array $a, array $b) => [$a['classroom'] === 'Classe non affectée', $a['classroom'], $a['student']->getLastName(), $a['student']->getFirstName()]
            <=> [$b['classroom'] === 'Classe non affectée', $b['classroom'], $b['student']->getLastName(), $b['student']->getFirstName()]);

        return $rows;
    }

    private function generatePassword(): string
    {
        $max = strlen(self::PASSWORD_ALPHABET) - 1;
        $password = '';
        for ($i = 0; $i < self::PASSWORD_LENGTH; $i++) {
            $password .= self::PASSWORD_ALPHABET[random_int(0, $max)];
        }

        return $password;
    }

    private function normalizePhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);

        return $digits === '' ? null : $digits;
    }
}
