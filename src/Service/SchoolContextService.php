<?php

namespace App\Service;

use App\Entity\School;
use App\Entity\SchoolYear;
use App\Repository\SchoolRepository;
use App\Repository\SchoolYearRepository;
use Symfony\Component\HttpFoundation\RequestStack;

class SchoolContextService
{
    private const SESSION_SCHOOL_KEY = 'current_school_id';
    private const SESSION_YEAR_KEY = 'current_school_year_id';

    public function __construct(
        private RequestStack $requestStack,
        private SchoolRepository $schoolRepository,
        private SchoolYearRepository $schoolYearRepository
    ) {
    }

    /**
     * Obtenir l'établissement en cours
     */
    public function getCurrentSchool(): ?School
    {
        $session = $this->requestStack->getSession();
        $schoolId = $session->get(self::SESSION_SCHOOL_KEY);

        if ($schoolId) {
            return $this->schoolRepository->find($schoolId);
        }

        // Si aucun établissement en session, prendre le premier actif
        $schools = $this->schoolRepository->findActive();
        if (!empty($schools)) {
            $school = $schools[0];
            $this->setCurrentSchool($school);
            return $school;
        }

        return null;
    }

    /**
     * Définir l'établissement en cours
     */
    public function setCurrentSchool(School $school): void
    {
        $session = $this->requestStack->getSession();
        $session->set(self::SESSION_SCHOOL_KEY, $school->getId());
        
        // Charger automatiquement l'année en cours (globale)
        $currentYear = $this->schoolYearRepository->findCurrent();
        if ($currentYear) {
            $this->setCurrentSchoolYear($currentYear);
        }
    }

    /**
     * Obtenir l'année scolaire en cours
     */
    public function getCurrentSchoolYear(): ?SchoolYear
    {
        $session = $this->requestStack->getSession();
        $yearId = $session->get(self::SESSION_YEAR_KEY);

        if ($yearId) {
            return $this->schoolYearRepository->find($yearId);
        }

        // Si aucune année en session, prendre l'année courante globale
        $currentYear = $this->schoolYearRepository->findCurrent();
        if ($currentYear) {
            $this->setCurrentSchoolYear($currentYear);
            return $currentYear;
        }

        return null;
    }

    /**
     * Définir l'année scolaire en cours
     */
    public function setCurrentSchoolYear(SchoolYear $schoolYear): void
    {
        $session = $this->requestStack->getSession();
        $session->set(self::SESSION_YEAR_KEY, $schoolYear->getId());
    }

    /**
     * Obtenir tous les établissements disponibles
     */
    public function getAvailableSchools(): array
    {
        return $this->schoolRepository->findActive();
    }

    /**
     * Obtenir toutes les années scolaires disponibles (globales)
     */
    public function getAvailableSchoolYears(): array
    {
        return $this->schoolYearRepository->findAllOrdered();
    }
}

