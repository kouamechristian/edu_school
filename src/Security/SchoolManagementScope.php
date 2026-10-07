<?php

namespace App\Security;

use App\Entity\School;
use App\Entity\SchoolGroup;
use App\Entity\User;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Périmètre des établissements que l'utilisateur connecté peut administrer
 * (écran Administration → Établissements).
 *
 *  - super-administrateur réel (rôle stocké) : tous les établissements ;
 *  - utilisateur rattaché à un groupe d'établissements : les établissements de ce
 *    groupe, actifs ou non ;
 *  - sinon : les établissements rattachés à son compte (repli, pour qu'un
 *    administrateur sans groupe ne se retrouve pas sans rien).
 */
final class SchoolManagementScope
{
    public function __construct(private readonly Security $security)
    {
    }

    public function isUnrestricted(): bool
    {
        $user = $this->user();

        return $user !== null && RoleGrantPolicy::isRealSuperAdmin($user);
    }

    public function group(): ?SchoolGroup
    {
        return $this->user()?->getSchoolGroup();
    }

    /**
     * Restreint une requête sur School (alias $alias) au périmètre de l'utilisateur.
     */
    public function restrict(QueryBuilder $qb, string $alias = 's'): QueryBuilder
    {
        if ($this->isUnrestricted()) {
            return $qb;
        }

        if ($group = $this->group()) {
            return $qb->andWhere(sprintf('%s.schoolGroup = :scopeGroup', $alias))->setParameter('scopeGroup', $group);
        }

        $ids = $this->ownSchoolIds();

        return $qb->andWhere(sprintf('%s.id IN (:scopeSchools)', $alias))->setParameter('scopeSchools', $ids ?: [0]);
    }

    public function canManage(School $school): bool
    {
        if ($this->isUnrestricted()) {
            return true;
        }

        if ($group = $this->group()) {
            return $school->getSchoolGroup()?->getId() === $group->getId();
        }

        return in_array($school->getId(), $this->ownSchoolIds(), true);
    }

    /**
     * Restreint une requête sur SchoolGroup (alias $alias) aux groupes du périmètre.
     */
    public function restrictGroups(QueryBuilder $qb, string $alias = 'sg'): QueryBuilder
    {
        $ids = $this->selectableGroupIds();
        if ($ids === null) {
            return $qb;
        }

        return $qb->andWhere(sprintf('%s.id IN (:scopeGroups)', $alias))->setParameter('scopeGroups', $ids ?: [0]);
    }

    public function canManageGroup(SchoolGroup $group): bool
    {
        $ids = $this->selectableGroupIds();

        return $ids === null || in_array($group->getId(), $ids, true);
    }

    /**
     * Groupes du périmètre (null = tous) : le groupe de l'utilisateur, à défaut ceux
     * des établissements de son compte. Ce sont aussi les groupes qu'un établissement
     * peut recevoir dans le formulaire.
     *
     * @return list<int>|null
     */
    public function selectableGroupIds(): ?array
    {
        if ($this->isUnrestricted()) {
            return null;
        }

        if ($group = $this->group()) {
            return [$group->getId()];
        }

        $ids = [];
        foreach ($this->user()?->getSchools() ?? [] as $school) {
            if ($school->getSchoolGroup()) {
                $ids[] = $school->getSchoolGroup()->getId();
            }
        }

        return array_values(array_unique($ids));
    }

    /** @return list<int> */
    private function ownSchoolIds(): array
    {
        $ids = [];
        foreach ($this->user()?->getSchools() ?? [] as $school) {
            $ids[] = $school->getId();
        }

        return $ids;
    }

    private function user(): ?User
    {
        $user = $this->security->getUser();

        return $user instanceof User ? $user : null;
    }
}
