<?php

namespace App\Service;

use App\Entity\Notification;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Crée des notifications internes pour les utilisateurs.
 */
class NotificationService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserRepository $userRepository
    ) {
    }

    public function notify(User $recipient, string $title, string $message, ?string $link = null, string $icon = 'fa-bell'): Notification
    {
        $notification = (new Notification())
            ->setRecipient($recipient)
            ->setTitle($title)
            ->setMessage($message)
            ->setLink($link)
            ->setIcon($icon);

        $this->entityManager->persist($notification);

        return $notification;
    }

    /**
     * Notifie tous les utilisateurs actifs possédant un rôle donné.
     *
     * @return int Nombre de notifications créées
     */
    public function notifyRole(string $role, string $title, string $message, ?string $link = null, string $icon = 'fa-bell'): int
    {
        $count = 0;
        foreach ($this->userRepository->findByRole($role) as $user) {
            if (!$user->isActive()) {
                continue;
            }
            $this->notify($user, $title, $message, $link, $icon);
            $count++;
        }

        return $count;
    }
}
