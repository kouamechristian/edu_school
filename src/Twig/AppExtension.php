<?php

namespace App\Twig;

use App\Entity\SchoolYear;
use App\Entity\User;
use App\Repository\NotificationRepository;
use App\Service\GradeCalculationService;
use App\Service\ParentContextService;
use App\Service\ParentPortalService;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class AppExtension extends AbstractExtension
{
    public function __construct(
        private GradeCalculationService $gradeCalculationService,
        private ParentPortalService $parentPortalService,
        private ParentContextService $parentContextService,
        private Security $security,
        private NotificationRepository $notificationRepository,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('getAppreciation', [$this->gradeCalculationService, 'getAppreciation']),
            new TwigFunction('getMention', [$this->gradeCalculationService, 'getMention']),
            new TwigFunction('countFrequency', [$this, 'countFrequency']),
            new TwigFunction('parent_children', [$this, 'parentChildren']),
            new TwigFunction('parent_school_years', [$this, 'parentSchoolYears']),
            new TwigFunction('parent_selected_year', [$this, 'parentSelectedYear']),
            new TwigFunction('parent_unread_notifications', [$this, 'parentUnreadNotifications']),
        ];
    }

    /**
     * Nombre de notifications non lues du parent connecté (badge de la cloche).
     */
    public function parentUnreadNotifications(): int
    {
        $user = $this->security->getUser();

        if (!$user instanceof User) {
            return 0;
        }

        return $this->notificationRepository->countUnreadForUser($user);
    }

    /**
     * Années scolaires disponibles pour le parent connecté (sélecteur de bascule).
     *
     * @return SchoolYear[]
     */
    public function parentSchoolYears(): array
    {
        return $this->parentContextService->getSchoolYears();
    }

    public function parentSelectedYear(): ?SchoolYear
    {
        return $this->parentContextService->getSelectedYear();
    }

    /**
     * Enfants rattachés au parent connecté (pour le sélecteur de la navbar parent).
     *
     * @return \App\Entity\Student[]
     */
    public function parentChildren(): array
    {
        $user = $this->security->getUser();

        if (!$user instanceof User) {
            return [];
        }

        return $this->parentPortalService->getChildren($user);
    }

    public function countFrequency(array $items, string $frequency): int
    {
        return count(array_filter($items, function($item) use ($frequency) {
            return $item->getFrequency() === $frequency;
        }));
    }
}
