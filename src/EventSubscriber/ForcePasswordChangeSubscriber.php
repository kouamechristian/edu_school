<?php

namespace App\EventSubscriber;

use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Tant qu'un utilisateur connecté a le flag « mustChangePassword », il est
 * redirigé vers la page de changement de mot de passe, quelle que soit la page
 * demandée (hors page de changement, déconnexion et ressources techniques).
 */
class ForcePasswordChangeSubscriber implements EventSubscriberInterface
{
    /**
     * Préfixes de chemins toujours autorisés même si un changement est requis.
     */
    private const ALLOWED_PREFIXES = [
        '/change-password',
        '/logout',
        '/_wdt',
        '/_profiler',
    ];

    public function __construct(
        private Security $security,
        private UrlGeneratorInterface $urlGenerator
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // Priorité 8 : après le pare-feu de sécurité (qui s'exécute à 8 pour
            // le routing/firewall) afin que l'utilisateur soit déjà authentifié.
            KernelEvents::REQUEST => ['onKernelRequest', 7],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $user = $this->security->getUser();
        if (!$user instanceof User || !$user->isMustChangePassword()) {
            return;
        }

        $path = $event->getRequest()->getPathInfo();
        foreach (self::ALLOWED_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return;
            }
        }

        $event->setResponse(
            new RedirectResponse($this->urlGenerator->generate('app_change_password'))
        );
    }
}
