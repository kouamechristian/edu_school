<?php

namespace App\Security\Voter;

use App\Entity\School;
use App\Entity\SchoolGroup;
use App\Security\SchoolManagementScope;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Autorise l'administration d'un établissement (fiche, modification, suppression,
 * activation, GeniusPay) ou d'un groupe d'établissements (fiche, modification)
 * compris dans le périmètre de l'utilisateur.
 *
 * @see SchoolManagementScope
 *
 * @extends Voter<string, School|SchoolGroup>
 */
final class SchoolManagementVoter extends Voter
{
    public const MANAGE = 'SCHOOL_MANAGE';

    public function __construct(private readonly SchoolManagementScope $scope)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === self::MANAGE && ($subject instanceof School || $subject instanceof SchoolGroup);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        return $subject instanceof School
            ? $this->scope->canManage($subject)
            : $this->scope->canManageGroup($subject);
    }
}
