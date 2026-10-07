<?php

namespace App\Security;

use App\Entity\User;
use App\Service\MaintenanceMode;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Contrôles appliqués à chaque connexion (formulaire personnel/parent, espace
 * élève, « se souvenir de moi ») :
 *  - un compte désactivé ne peut pas se connecter ;
 *  - pendant la maintenance, seul le super-administrateur peut se connecter.
 *
 * Les contrôles sont faits APRÈS la vérification des identifiants, pour ne pas
 * révéler l'existence ou l'état d'un compte à qui ne connaît pas son mot de passe.
 * Les messages sont affichés tels quels sur la page de connexion.
 */
class UserChecker implements UserCheckerInterface
{
    public function __construct(private readonly MaintenanceMode $maintenance)
    {
    }

    public function checkPreAuth(UserInterface $user): void
    {
    }

    public function checkPostAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }

        if (!$user->isActive()) {
            throw new CustomUserMessageAccountStatusException(
                'Votre compte est désactivé. Contactez l\'administrateur de votre établissement.'
            );
        }

        if ($this->maintenance->isEnabled() && !$this->maintenance->canBypass($user)) {
            throw new CustomUserMessageAccountStatusException(
                'L\'application est actuellement en maintenance. Veuillez réessayer plus tard.'
            );
        }
    }
}
