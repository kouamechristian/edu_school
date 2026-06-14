<?php

namespace App\Repository;

use App\Entity\Student;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Bridge\Doctrine\Security\User\UserLoaderInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface, UserLoaderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Permet la connexion par nom d'utilisateur OU adresse e-mail.
     */
    public function loadUserByIdentifier(string $identifier): ?UserInterface
    {
        return $this->createQueryBuilder('u')
            ->where('u.username = :id OR u.email = :id')
            ->setParameter('id', $identifier)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    /**
     * Trouver tous les utilisateurs actifs
     */
    public function findActive(): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('u.username', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouver les utilisateurs par type
     */
    public function findByType(string $type): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.userType = :type')
            ->andWhere('u.isActive = :active')
            ->setParameter('type', $type)
            ->setParameter('active', true)
            ->orderBy('u.lastName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouver les utilisateurs par rôle
     */
    public function findByRole(string $role): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.roles LIKE :role')
            ->setParameter('role', '%"'.$role.'"%')
            ->orderBy('u.username', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Rechercher des utilisateurs par nom ou email
     */
    public function searchByNameOrEmail(string $searchTerm): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.username LIKE :term OR u.email LIKE :term OR u.firstName LIKE :term OR u.lastName LIKE :term')
            ->setParameter('term', '%'.$searchTerm.'%')
            ->orderBy('u.username', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compter le nombre d'utilisateurs par type
     */
    public function countByType(): array
    {
        return $this->createQueryBuilder('u')
            ->select('u.userType, COUNT(u.id) as count')
            ->andWhere('u.isActive = :active')
            ->setParameter('active', true)
            ->groupBy('u.userType')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compter le nombre d'utilisateurs actifs
     */
    public function countActive(): int
    {
        return $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->andWhere('u.isActive = :active')
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Trouver les derniers utilisateurs inscrits
     */
    public function findLatest(int $limit = 10): array
    {
        return $this->createQueryBuilder('u')
            ->orderBy('u.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouver un utilisateur par username ou email
     */
    public function findByUsernameOrEmail(string $identifier): ?User
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.username = :identifier OR u.email = :identifier')
            ->setParameter('identifier', $identifier)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Mettre à jour la dernière connexion
     */
    public function updateLastLogin(User $user): void
    {
        $user->setLastLogin(new \DateTime());
        $this->getEntityManager()->flush();
    }

    /**
     * Trouver les utilisateurs par établissement
     * Retourne UNIQUEMENT les utilisateurs liés à l'établissement spécifié
     */
    public function findBySchool(?int $schoolId): array
    {
        if (!$schoolId) {
            return []; // Si pas d'établissement, retourner liste vide
        }

        return $this->createQueryBuilder('u')
            ->innerJoin('u.schools', 's')
            ->andWhere('s.id = :school')
            ->setParameter('school', $schoolId)
            ->orderBy('u.lastName', 'ASC')
            ->addOrderBy('u.firstName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Rechercher des utilisateurs par nom ou email dans un établissement
     */
    public function searchByNameOrEmailInSchool(string $searchTerm, ?int $schoolId): array
    {
        if (!$schoolId) {
            return $this->searchByNameOrEmail($searchTerm);
        }

        return $this->createQueryBuilder('u')
            ->innerJoin('u.schools', 's')
            ->andWhere('s.id = :school')
            ->andWhere('u.username LIKE :term OR u.email LIKE :term OR u.firstName LIKE :term OR u.lastName LIKE :term')
            ->setParameter('school', $schoolId)
            ->setParameter('term', '%'.$searchTerm.'%')
            ->orderBy('u.username', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouver les utilisateurs par type dans un établissement
     */
    public function findByTypeInSchool(string $type, ?int $schoolId): array
    {
        if (!$schoolId) {
            return $this->findByType($type);
        }

        return $this->createQueryBuilder('u')
            ->innerJoin('u.schools', 's')
            ->andWhere('s.id = :school')
            ->andWhere('u.userType = :type')
            ->andWhere('u.isActive = :active')
            ->setParameter('school', $schoolId)
            ->setParameter('type', $type)
            ->setParameter('active', true)
            ->orderBy('u.lastName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compter le nombre d'utilisateurs par type dans un établissement
     */
    public function countByTypeInSchool(?int $schoolId): array
    {
        if (!$schoolId) {
            return $this->countByType();
        }

        return $this->createQueryBuilder('u')
            ->select('u.userType, COUNT(u.id) as count')
            ->innerJoin('u.schools', 's')
            ->andWhere('s.id = :school')
            ->andWhere('u.isActive = :active')
            ->setParameter('school', $schoolId)
            ->setParameter('active', true)
            ->groupBy('u.userType')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compter le nombre d'utilisateurs actifs dans un établissement
     */
    public function countActiveInSchool(?int $schoolId): int
    {
        if (!$schoolId) {
            return $this->countActive();
        }

        return $this->createQueryBuilder('u')
            ->select('COUNT(DISTINCT u.id)')
            ->innerJoin('u.schools', 's')
            ->andWhere('s.id = :school')
            ->andWhere('u.isActive = :active')
            ->setParameter('school', $schoolId)
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Trouve le compte parent actuellement rattaché à un élève, le cas échéant.
     *
     * Couvre les deux mécanismes de rattachement, afin de garantir qu'un enfant
     * n'est lié qu'à un seul parent :
     *  - lien explicite (Student.parentUser, auto-association) ;
     *  - lien historique par e-mail vers un compte parent existant
     *    (Student.parentEmail ↔ User.email avec ROLE_PARENT).
     *
     * Retourne null si l'élève n'est rattaché à aucun compte parent.
     */
    public function findParentOfStudent(Student $child): ?User
    {
        if ($child->getParentUser() !== null) {
            return $child->getParentUser();
        }

        $email = mb_strtolower(trim((string) $child->getParentEmail()));

        if ($email === '') {
            return null;
        }

        return $this->createQueryBuilder('u')
            ->andWhere('LOWER(TRIM(u.email)) = :email')
            ->andWhere('u.roles LIKE :role')
            ->setParameter('email', $email)
            ->setParameter('role', '%"ROLE_PARENT"%')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouver les élèves d'une classe
     */
    public function findByClassroom(int $classroomId): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.userType = :type')
            ->andWhere('u.isActive = :active')
            ->setParameter('type', 'eleve')
            ->setParameter('active', true)
            ->orderBy('u.lastName', 'ASC')
            ->addOrderBy('u.firstName', 'ASC')
            ->getQuery()
            ->getResult();
    }
}

