<?php

namespace App\EventSubscriber;

use App\Service\DefaultDataInitializer;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Lorsqu'on accède à la page de connexion et que la base est vide,
 * crée automatiquement le groupe d'établissement, l'établissement et
 * le compte SUPER_ADMIN par défaut.
 *
 * Ce mécanisme permet un premier démarrage sans fixtures ni commande manuelle.
 */
class DefaultDataSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private DefaultDataInitializer $initializer,
        private ?LoggerInterface $logger = null
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // Priorité élevée : avant le pare-feu de sécurité, afin que le
            // compte par défaut existe au moment de l'authentification.
            KernelEvents::REQUEST => ['onKernelRequest', 100],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        // On ne déclenche le contrôle que sur la page de connexion pour éviter
        // une requête « COUNT » à chaque page de l'application.
        if ($event->getRequest()->getPathInfo() !== '/login') {
            return;
        }

        try {
            if ($this->initializer->initializeIfEmpty()) {
                $this->logger?->info(
                    'Base vide détectée : groupe, établissement et compte SUPER_ADMIN par défaut créés.'
                );
            }
        } catch (\Throwable $e) {
            // Schéma non encore créé ou base indisponible : on n'interrompt pas
            // l'affichage de la page de connexion.
            $this->logger?->warning(
                'Initialisation des données par défaut impossible : ' . $e->getMessage()
            );
        }
    }
}
