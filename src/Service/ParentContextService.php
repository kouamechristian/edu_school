<?php

namespace App\Service;

use App\Entity\SchoolYear;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Contexte de l'espace parent : gère l'année scolaire sélectionnée (bascule),
 * limitée aux années réellement présentes parmi les enfants du parent connecté.
 */
class ParentContextService
{
    private const SESSION_KEY = 'parent_selected_school_year';

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly ParentPortalService $portal,
        private readonly Security $security,
    ) {
    }

    /**
     * @return SchoolYear[]
     */
    public function getSchoolYears(): array
    {
        $parent = $this->security->getUser();

        return $parent instanceof User ? $this->portal->getSchoolYears($parent) : [];
    }

    public function getSelectedYear(): ?SchoolYear
    {
        $years = $this->getSchoolYears();
        if ($years === []) {
            return null;
        }

        $selectedId = $this->requestStack->getSession()->get(self::SESSION_KEY);
        if ($selectedId !== null) {
            foreach ($years as $year) {
                if ($year->getId() === $selectedId) {
                    return $year;
                }
            }
        }

        // Défaut : l'année courante si l'un des enfants y est, sinon la plus récente.
        foreach ($years as $year) {
            if ($year->isCurrent()) {
                return $year;
            }
        }

        return $years[0];
    }

    public function getSelectedYearId(): ?int
    {
        return $this->getSelectedYear()?->getId();
    }

    /**
     * Mémorise l'année choisie (uniquement si elle appartient au parent).
     */
    public function setSelectedYear(int $yearId): bool
    {
        foreach ($this->getSchoolYears() as $year) {
            if ($year->getId() === $yearId) {
                $this->requestStack->getSession()->set(self::SESSION_KEY, $yearId);

                return true;
            }
        }

        return false;
    }
}
