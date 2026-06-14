<?php

namespace App\Twig;

use App\Entity\User;
use App\Repository\NotificationRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class NotificationExtension extends AbstractExtension
{
    public function __construct(
        private Security $security,
        private NotificationRepository $notificationRepository
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('unread_notifications_count', [$this, 'unreadCount']),
            new TwigFunction('recent_notifications', [$this, 'recentNotifications']),
        ];
    }

    public function unreadCount(): int
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return 0;
        }

        return $this->notificationRepository->countUnreadForUser($user);
    }

    /**
     * @return \App\Entity\Notification[]
     */
    public function recentNotifications(int $limit = 8): array
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return [];
        }

        return $this->notificationRepository->findForUser($user, $limit);
    }
}
