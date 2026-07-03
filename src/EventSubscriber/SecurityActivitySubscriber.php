<?php

namespace App\EventSubscriber;

use App\Entity\ActivityLog;
use App\Entity\User;
use App\Service\ActivityLogger;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Security\Http\Event\LogoutEvent;

/**
 * Journalise les évènements de sécurité dans le « Mouchard » :
 *  - connexion réussie,
 *  - déconnexion,
 *  - échec de connexion (mot de passe erroné, compte inconnu, etc.).
 *
 * Ces traces sont précieuses pour détecter les tentatives d'accès frauduleuses.
 */
class SecurityActivitySubscriber implements EventSubscriberInterface
{
    public function __construct(
        private ActivityLogger $activityLogger,
        private RequestStack $requestStack,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LoginSuccessEvent::class => 'onLoginSuccess',
            LogoutEvent::class => 'onLogout',
            LoginFailureEvent::class => 'onLoginFailure',
        ];
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();

        $log = $this->activityLogger->createContextualLog(ActivityLog::ACTION_LOGIN)
            ->setDescription('Connexion au logiciel');

        if ($user instanceof User) {
            $log->setUser($user)->setUsername($user->getUserIdentifier());
            if ($user->getLastSchool() !== null) {
                $log->setSchool($user->getLastSchool());
            }
        } else {
            $log->setUsername($user->getUserIdentifier());
        }

        $this->activityLogger->persistLog($log);
    }

    public function onLogout(LogoutEvent $event): void
    {
        $user = $event->getToken()?->getUser();

        // createContextualLog s'appuie sur le contexte de sécurité, déjà en cours
        // d'invalidation : on renseigne l'utilisateur depuis le jeton de l'évènement.
        $log = $this->activityLogger->createContextualLog(ActivityLog::ACTION_LOGOUT)
            ->setDescription('Déconnexion du logiciel');

        if ($user instanceof User) {
            $log->setUser($user)->setUsername($user->getUserIdentifier());
            if ($user->getLastSchool() !== null) {
                $log->setSchool($user->getLastSchool());
            }
        }

        $this->activityLogger->persistLog($log);
    }

    public function onLoginFailure(LoginFailureEvent $event): void
    {
        $request = $event->getRequest() ?? $this->requestStack->getCurrentRequest();
        $username = trim((string) ($request?->request->get('username') ?? '')) ?: 'inconnu';

        $log = $this->activityLogger->createContextualLog(ActivityLog::ACTION_LOGIN_FAILED)
            ->setUsername($username)
            ->setDescription('Échec de connexion : ' . $event->getException()->getMessageKey());

        // Aucun utilisateur authentifié : on efface l'auteur éventuellement pré-rempli.
        $log->setUser(null);

        $this->activityLogger->persistLog($log);
    }
}
