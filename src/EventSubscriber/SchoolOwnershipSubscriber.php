<?php

namespace App\EventSubscriber;

use App\Entity\MultiSchoolOwnedInterface;
use App\Entity\School;
use App\Entity\SchoolOwnedInterface;
use App\Entity\User;
use App\Service\SchoolContextService;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Garde-fou central contre les IDOR inter-établissements.
 *
 * Les entités rattachées à un établissement sont, sur les actions show/edit/delete…,
 * injectées par la route via leur seul identifiant (auto-increment séquentiel), sans
 * vérification d'appartenance. Ce souscripteur inspecte, juste après la résolution des
 * arguments du contrôleur, tout argument implémentant SchoolOwnedInterface /
 * MultiSchoolOwnedInterface et renvoie un 404 si l'entité n'appartient pas à un
 * établissement autorisé pour l'utilisateur courant.
 *
 * Exemptions :
 *  - requêtes non authentifiées (pages publiques) ;
 *  - vrai ROLE_SUPER_ADMIN (rôle stocké, hors héritage) : accès à tout ;
 *  - portail parent (/parent) : autorisation gérée par ChildVoter.
 */
final class SchoolOwnershipSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly SchoolContextService $context,
        private readonly Security $security,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER_ARGUMENTS => 'onControllerArguments',
        ];
    }

    public function onControllerArguments(ControllerArgumentsEvent $event): void
    {
        // Le portail parent a sa propre logique d'autorisation (ChildVoter) et un
        // contexte d'établissement différent : on ne l'assujettit pas à ce garde-fou.
        if (str_starts_with($event->getRequest()->getPathInfo(), '/parent')) {
            return;
        }

        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return;
        }

        // Le super-administrateur « réel » (rôle stocké, pas hérité) accède à tout.
        if (in_array('ROLE_SUPER_ADMIN', $user->getRoles(), true)) {
            return;
        }

        foreach ($event->getArguments() as $argument) {
            if ($argument instanceof SchoolOwnedInterface) {
                $school = $argument->getSchool();
                if ($school instanceof School && !$this->context->isSchoolAllowed($school)) {
                    throw new NotFoundHttpException();
                }
            } elseif ($argument instanceof MultiSchoolOwnedInterface) {
                if (!$this->belongsToAllowedSchool($argument->getSchools())) {
                    throw new NotFoundHttpException();
                }
            }
        }
    }

    /**
     * Vrai si l'entité n'est rattachée à aucun établissement (non filtrée) ou si
     * elle partage au moins un établissement autorisé avec l'utilisateur.
     *
     * @param iterable<School> $schools
     */
    private function belongsToAllowedSchool(iterable $schools): bool
    {
        $hasAny = false;
        foreach ($schools as $school) {
            $hasAny = true;
            if ($school instanceof School && $this->context->isSchoolAllowed($school)) {
                return true;
            }
        }

        return $hasAny === false;
    }
}
