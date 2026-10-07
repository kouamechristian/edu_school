<?php

namespace App\EventSubscriber;

use App\Entity\User;
use App\Service\SchoolContextService;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Un compte du personnel rattaché à aucun établissement n'a accès à aucune donnée
 * (SchoolContextService::getAvailableSchools() renvoie une liste vide). Plutôt que
 * de le laisser sur des écrans vides ou en erreur, on le redirige vers une page
 * d'explication.
 *
 * Ne sont pas concernés : le super-administrateur réel (accès à tout), les comptes
 * parent et élève (leurs portails s'appuient sur les fiches élèves, pas sur un
 * rattachement), et l'API mobile (qui répond elle-même « aucun accès »).
 */
class NoSchoolAssignedSubscriber implements EventSubscriberInterface
{
    /** Préfixes de chemins toujours accessibles. */
    private const ALLOWED_PREFIXES = [
        '/context/aucun-etablissement',
        '/logout',
        '/login',
        '/change-password',
        '/documentation',
        '/support',
        '/parent',
        '/eleve',
        '/api/',
        '/_wdt',
        '/_profiler',
    ];

    private const PORTAL_ROLES = ['ROLE_USER', 'ROLE_PARENT', 'ROLE_ELEVE'];

    public function __construct(
        private readonly Security $security,
        private readonly SchoolContextService $context,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // Après le pare-feu (8) et le changement de mot de passe forcé (7).
            KernelEvents::REQUEST => ['onKernelRequest', 6],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return;
        }

        $roles = $user->getRoles();
        if (in_array('ROLE_SUPER_ADMIN', $roles, true) || array_diff($roles, self::PORTAL_ROLES) === []) {
            return;
        }

        $path = $event->getRequest()->getPathInfo();
        foreach (self::ALLOWED_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return;
            }
        }

        if ($this->context->getAvailableSchoolsFor($user) === []) {
            $event->setResponse(new RedirectResponse($this->urlGenerator->generate('context_no_school')));
        }
    }
}
