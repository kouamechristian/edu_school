<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;

/**
 * Détermine quels rôles un utilisateur a le droit d'attribuer à un compte.
 *
 * Règle : on ne peut attribuer que des rôles que l'on possède déjà (directement ou
 * par héritage), plus les rôles des portails (parent, élève). ROLE_SUPER_ADMIN est
 * réservé au super-administrateur « réel » (rôle stocké, hors héritage) — ce qui
 * empêche un administrateur d'établissement de s'élever ou de créer un compte plus
 * puissant que le sien.
 */
final class RoleGrantPolicy
{
    /** Rôles proposés dans les formulaires de gestion des comptes, avec leur libellé. */
    public const ROLE_LABELS = [
        'Super administrateur (ROLE_SUPER_ADMIN)' => 'ROLE_SUPER_ADMIN',
        'Fondateur (ROLE_FONDATEUR)' => 'ROLE_FONDATEUR',
        'Administrateur (ROLE_ADMIN)' => 'ROLE_ADMIN',
        'Directeur (ROLE_DIRECTEUR)' => 'ROLE_DIRECTEUR',
        'Agent d\'inscription (ROLE_INSCRIPTION)' => 'ROLE_INSCRIPTION',
        'Caissier (ROLE_CAISSE)' => 'ROLE_CAISSE',
        'Comptable (ROLE_COMPTABLE)' => 'ROLE_COMPTABLE',
        'Agent de recouvrement (ROLE_RECOUVREMENT)' => 'ROLE_RECOUVREMENT',
        'Ressources Humaines (ROLE_RH)' => 'ROLE_RH',
        'Enseignant (ROLE_ENSEIGNANT)' => 'ROLE_ENSEIGNANT',
        'Éducateur (ROLE_EDUCATEUR)' => 'ROLE_EDUCATEUR',
        'Correspondant fichier (ROLE_CORRESPONDANT_FICHIER)' => 'ROLE_CORRESPONDANT_FICHIER',
        'Parent (ROLE_PARENT)' => 'ROLE_PARENT',
        'Élève (ROLE_ELEVE)' => 'ROLE_ELEVE',
    ];

    /** Rôles des portails, sans privilège sur la gestion : attribuables par tout gestionnaire. */
    private const PORTAL_ROLES = ['ROLE_PARENT', 'ROLE_ELEVE'];

    public function __construct(private readonly RoleHierarchyInterface $roleHierarchy)
    {
    }

    public static function isRealSuperAdmin(User $user): bool
    {
        return in_array('ROLE_SUPER_ADMIN', $user->getRoles(), true);
    }

    /**
     * @return list<string>
     */
    public function grantableRoles(User $actor): array
    {
        if (self::isRealSuperAdmin($actor)) {
            return array_values(self::ROLE_LABELS);
        }

        $reachable = $this->roleHierarchy->getReachableRoleNames($actor->getRoles());

        return array_values(array_filter(
            self::ROLE_LABELS,
            static fn (string $role) => $role !== 'ROLE_SUPER_ADMIN'
                && (in_array($role, $reachable, true) || in_array($role, self::PORTAL_ROLES, true)),
        ));
    }

    /**
     * Libellés => rôles, limités à ceux que l'acteur peut attribuer.
     *
     * @return array<string, string>
     */
    public function grantableChoices(User $actor): array
    {
        $grantable = $this->grantableRoles($actor);

        return array_filter(self::ROLE_LABELS, static fn (string $role) => in_array($role, $grantable, true));
    }

    /**
     * Vrai si l'acteur pourrait lui-même attribuer chacun des rôles de la cible
     * (ROLE_USER, implicite, est ignoré).
     */
    public function canGrantAllRolesOf(User $actor, User $target): bool
    {
        $grantable = $this->grantableRoles($actor);

        foreach ($target->getRoles() as $role) {
            if ($role !== 'ROLE_USER' && !in_array($role, $grantable, true)) {
                return false;
            }
        }

        return true;
    }
}
