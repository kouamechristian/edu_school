<?php

namespace App\EventSubscriber;

use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Un compte désactivé alors que son titulaire a une session ouverte est déconnecté
 * à sa requête suivante (la connexion elle-même est refusée par App\Security\UserChecker).
 *
 * L'API mobile n'est pas concernée : son authentificateur ignore déjà les jetons
 * des comptes inactifs (UserRepository::findOneByApiToken).
 */
class InactiveUserSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly Security $security)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // Après le pare-feu (8), pour que l'utilisateur soit chargé.
            KernelEvents::REQUEST => ['onKernelRequest', 7],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if (str_starts_with($request->getPathInfo(), '/api/')) {
            return;
        }

        $user = $this->security->getUser();
        if (!$user instanceof User || $user->isActive()) {
            return;
        }

        $response = $this->security->logout(false);
        if ($response === null) {
            return;
        }

        $session = $request->hasSession() ? $request->getSession() : null;
        if ($session instanceof FlashBagAwareSessionInterface) {
            $session->getFlashBag()->add('error', 'Votre compte a été désactivé. Contactez l\'administrateur de votre établissement.');
        }

        $event->setResponse($response);
    }
}
