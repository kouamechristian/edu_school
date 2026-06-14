<?php

namespace App\Security\Voter;

use App\Entity\Student;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Autorise un parent à consulter UNIQUEMENT ses propres enfants.
 *
 * Frontière de sécurité unique du Portail Parent : tout accès à des données
 * rattachées à un élève (notes, absences, finances) doit passer par ce voter
 * via denyAccessUnlessGranted(ChildVoter::VIEW, $student).
 *
 * Le lien parent ↔ enfant repose sur le schéma existant (Student.parentEmail ↔
 * User.email). En isolant la règle ici, on pourra la remplacer par une vraie
 * relation Doctrine sans modifier les contrôleurs.
 *
 * @extends Voter<string, Student>
 */
class ChildVoter extends Voter
{
    public const VIEW = 'PARENT_VIEW_CHILD';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === self::VIEW && $subject instanceof Student;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User || !$subject instanceof Student) {
            return false;
        }

        // Un super-administrateur réel peut tout consulter (support / SAV).
        if (in_array('ROLE_SUPER_ADMIN', $user->getRoles(), true)) {
            return true;
        }

        // Seuls les parents accèdent au portail parent.
        if (!in_array('ROLE_PARENT', $user->getRoles(), true)) {
            return false;
        }

        return $this->isLinked($user, $subject);
    }

    /**
     * Vrai si l'élève est actif et rattaché au parent — soit par le lien explicite
     * (auto-association via matricule + date de naissance), soit par l'e-mail
     * historique (Student.parentEmail ↔ User.email).
     */
    private function isLinked(User $parent, Student $child): bool
    {
        if (!$child->isActive()) {
            return false;
        }

        // 1. Lien explicite (Student.parentUser).
        if ($child->getParentUser() && $child->getParentUser()->getId() === $parent->getId()) {
            return true;
        }

        // 2. Lien historique par e-mail.
        $parentEmail = $this->normalize($parent->getEmail());
        $childParentEmail = $this->normalize($child->getParentEmail());

        return $parentEmail !== '' && $parentEmail === $childParentEmail;
    }

    private function normalize(?string $email): string
    {
        return mb_strtolower(trim((string) $email));
    }
}
