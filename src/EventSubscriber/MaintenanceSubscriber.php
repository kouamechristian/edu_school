<?php

namespace App\EventSubscriber;

use App\Entity\User;
use App\Service\MaintenanceMode;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Twig\Environment;

/**
 * Pendant la maintenance, un utilisateur déjà connecté (session ouverte avant
 * l'activation) qui n'est pas super-administrateur voit la page de maintenance
 * (HTTP 503) à chaque requête ; l'API mobile répond en JSON. Sa session est
 * conservée : il retrouve l'application dès la fin de la maintenance.
 *
 * Les visiteurs anonymes ne sont pas bloqués ici : le contrôle d'accès les envoie
 * vers la page de connexion, qui signale la maintenance et refuse la connexion
 * (cf. App\Security\UserChecker).
 */
class MaintenanceSubscriber implements EventSubscriberInterface
{
    /** Préfixes de chemins toujours accessibles. */
    private const ALLOWED_PREFIXES = [
        '/login',
        '/logout',
        '/parent/connexion',
        '/eleve/connexion',
        '/documentation',
        '/support',
        // Le webhook de paiement doit continuer à enregistrer les paiements reçus.
        '/webhook/',
        '/_wdt',
        '/_profiler',
    ];

    public function __construct(
        private readonly MaintenanceMode $maintenance,
        private readonly Security $security,
        private readonly Environment $twig,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // Après le pare-feu (8), avant les autres redirections post-connexion.
            KernelEvents::REQUEST => ['onKernelRequest', 7],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest() || !$this->maintenance->isEnabled()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();
        foreach (self::ALLOWED_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return;
            }
        }

        $user = $this->security->getUser();
        if ($user === null || ($user instanceof User && $this->maintenance->canBypass($user))) {
            return;
        }

        if (str_starts_with($path, '/api/')) {
            $event->setResponse(new JsonResponse([
                'error' => 'maintenance',
                'message' => $this->maintenance->getMessage() ?? 'L\'application est en maintenance. Veuillez réessayer plus tard.',
            ], Response::HTTP_SERVICE_UNAVAILABLE, ['Retry-After' => '600']));

            return;
        }

        $event->setResponse(new Response(
            $this->twig->render('security/maintenance.html.twig'),
            Response::HTTP_SERVICE_UNAVAILABLE,
            ['Retry-After' => '600']
        ));
    }
}
