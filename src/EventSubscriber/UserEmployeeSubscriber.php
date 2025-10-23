<?php

namespace App\EventSubscriber;

use App\Entity\User;
use App\Service\UserEmployeeService;
use Doctrine\Bundle\DoctrineBundle\EventSubscriber\EventSubscriberInterface;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;

class UserEmployeeSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private UserEmployeeService $userEmployeeService
    ) {
    }

    public function getSubscribedEvents(): array
    {
        return [
            Events::postPersist,
            Events::postUpdate,
        ];
    }

    public function postPersist(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!$entity instanceof User) {
            return;
        }

        // Ne créer un employé que pour les types qui en ont besoin
        if ($this->shouldCreateEmployee($entity)) {
            $this->userEmployeeService->createEmployeeForUser($entity);
            $args->getObjectManager()->flush();
        }
    }

    public function postUpdate(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!$entity instanceof User) {
            return;
        }

        // Mettre à jour l'employé associé si nécessaire
        if ($this->shouldCreateEmployee($entity)) {
            $this->userEmployeeService->updateEmployeeFromUser($entity);
            $this->userEmployeeService->syncSchoolsBetweenUserAndEmployee($entity);
            $args->getObjectManager()->flush();
        }
    }

    /**
     * Détermine si un employé doit être créé pour cet utilisateur
     */
    private function shouldCreateEmployee(User $user): bool
    {
        $userType = $user->getUserType();
        
        return in_array($userType, ['directeur', 'enseignant', 'personnel']);
    }
}
