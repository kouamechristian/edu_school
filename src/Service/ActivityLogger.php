<?php

namespace App\Service;

use App\Entity\ActivityLog;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Point d'entrée du journal d'activité (« Mouchard »).
 *
 * Fabrique des lignes {@see ActivityLog} pré-remplies avec le contexte courant
 * (utilisateur connecté, adresse IP, route, méthode HTTP). Le sous-système
 * Doctrine ({@see \App\EventSubscriber\ActivityLogSubscriber}) enregistre les
 * changements d'entités ; ce service sert aux évènements de sécurité (connexion,
 * déconnexion) et à toute journalisation manuelle depuis un contrôleur.
 */
class ActivityLogger
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Security $security,
        private RequestStack $requestStack,
    ) {
    }

    /**
     * Crée une ligne de journal renseignée avec le contexte courant, SANS la persister.
     *
     * Utilisé par le sous-système Doctrine, qui se charge lui-même du persist/flush
     * pour éviter toute récursion pendant un flush en cours.
     */
    public function createContextualLog(string $action): ActivityLog
    {
        $log = (new ActivityLog())->setAction($action);

        $user = $this->security->getUser();
        if ($user instanceof User) {
            $log->setUser($user);
            $log->setUsername($user->getUserIdentifier());
        } elseif ($user !== null) {
            $log->setUsername($user->getUserIdentifier());
        }

        $request = $this->requestStack->getCurrentRequest();
        if ($request !== null) {
            $log->setIpAddress($request->getClientIp());
            $log->setRoute($request->attributes->get('_route'));
            $log->setMethod($request->getMethod());
        }

        return $log;
    }

    /**
     * Journalise immédiatement une action (persist + flush).
     *
     * Réservé aux contextes HORS flush Doctrine en cours (évènements de sécurité,
     * appels explicites depuis un contrôleur). $username permet de forcer le nom
     * lorsqu'aucun utilisateur n'est authentifié (ex. échec de connexion).
     */
    public function log(string $action, ?string $description = null, ?string $username = null): void
    {
        $log = $this->createContextualLog($action)->setDescription($description);

        if ($username !== null) {
            $log->setUsername($username);
        }

        // Rattache l'action au dernier établissement connu de l'utilisateur.
        $user = $this->security->getUser();
        if ($user instanceof User && $user->getLastSchool() !== null) {
            $log->setSchool($user->getLastSchool());
        }

        $this->persistLog($log);
    }

    /**
     * Persiste et flush une ligne déjà préparée (utilisé par les évènements de sécurité,
     * où l'utilisateur provient du jeton plutôt que du contexte de sécurité courant).
     */
    public function persistLog(ActivityLog $log): void
    {
        $this->entityManager->persist($log);
        $this->entityManager->flush();
    }
}
