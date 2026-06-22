<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Event\LogoutEvent;

/**
 * Redirige l'utilisateur après déconnexion selon son profil :
 *  - un parent « pur » (aucun rôle personnel) revient sur la connexion parent ;
 *  - tout autre utilisateur revient sur la connexion du personnel.
 *
 * Le token est encore disponible au moment du LogoutEvent, ce qui permet de
 * connaître les rôles de l'utilisateur qui se déconnecte.
 */
class LogoutSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly UrlGeneratorInterface $urlGenerator)
    {
    }

    public static function getSubscribedEvents(): array
    {
        // Priorité négative : on passe après le listener de déconnexion par défaut
        // afin de remplacer sa redirection (target: app_login).
        return [LogoutEvent::class => ['onLogout', -10]];
    }

    public function onLogout(LogoutEvent $event): void
    {
        $token = $event->getToken();
        $target = 'app_login';

        if ($token) {
            $roles = $token->getRoleNames();
            $staffRoles = array_diff($roles, ['ROLE_PARENT', 'ROLE_USER']);

            if (in_array('ROLE_PARENT', $roles, true) && $staffRoles === []) {
                $target = 'parent_login';
            }
        }

        $event->setResponse(new RedirectResponse($this->urlGenerator->generate($target)));
    }
}
