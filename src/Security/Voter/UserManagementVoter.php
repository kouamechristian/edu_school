<?php

namespace App\Security\Voter;

use App\Entity\Student;
use App\Entity\User;
use App\Security\RoleGrantPolicy;
use App\Service\SchoolContextService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Autorise la gestion (consultation, modification, suppression, activation) d'un
 * compte utilisateur depuis l'administration.
 *
 * User n'implémente pas MultiSchoolOwnedInterface (il est aussi l'utilisateur
 * connecté, injecté partout) : le cloisonnement des comptes passe donc par ce voter,
 * via denyAccessUnlessGranted(UserManagementVoter::MANAGE, $user).
 *
 * Un gestionnaire (hors super-administrateur réel) ne peut gérer un compte que si :
 *  - il pourrait lui-même attribuer chacun de ses rôles (pas de compte plus puissant) ;
 *  - le compte partage un de ses établissements ; ou, pour un compte parent/élève
 *    sans établissement, s'il est rattaché à un élève d'un de ses établissements
 *    (ou à aucun élève, cas d'un compte parent auto-inscrit).
 *
 * @extends Voter<string, User>
 */
final class UserManagementVoter extends Voter
{
    public const MANAGE = 'USER_MANAGE';

    private const PORTAL_ROLES = ['ROLE_USER', 'ROLE_PARENT', 'ROLE_ELEVE'];

    public function __construct(
        private readonly RoleGrantPolicy $roleGrantPolicy,
        private readonly SchoolContextService $context,
        private readonly EntityManagerInterface $em,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === self::MANAGE && $subject instanceof User;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $actor = $token->getUser();

        if (!$actor instanceof User || !$subject instanceof User) {
            return false;
        }

        if (RoleGrantPolicy::isRealSuperAdmin($actor)) {
            return true;
        }

        if (!$this->roleGrantPolicy->canGrantAllRolesOf($actor, $subject)) {
            return false;
        }

        if ($actor->getId() !== null && $actor->getId() === $subject->getId()) {
            return true;
        }

        $available = $this->context->getAvailableSchools();

        if (!$subject->getSchools()->isEmpty()) {
            foreach ($subject->getSchools() as $school) {
                if ($this->context->isSchoolAllowed($school, $available)) {
                    return true;
                }
            }

            return false;
        }

        // Compte du personnel sans établissement : réservé au super-administrateur.
        if (array_diff($subject->getRoles(), self::PORTAL_ROLES) !== []) {
            return false;
        }

        $linkedStudents = $this->em->getRepository(Student::class)->createQueryBuilder('s')
            ->andWhere('s.parentUser = :user OR s.studentUser = :user')
            ->setParameter('user', $subject)
            ->getQuery()
            ->getResult();

        if ($linkedStudents === []) {
            return true;
        }

        foreach ($linkedStudents as $student) {
            $school = $student->getSchool();
            if ($school !== null && $this->context->isSchoolAllowed($school, $available)) {
                return true;
            }
        }

        return false;
    }
}
