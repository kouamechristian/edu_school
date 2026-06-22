<?php

namespace App\Service;

use App\Entity\Classroom;
use App\Entity\PreRegistration;
use App\Entity\Registration;
use App\Entity\Level;
use App\Entity\SchoolYear;
use App\Entity\Student;
use App\Repository\RegistrationRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Centralise la création/mise à jour des inscriptions (élève ↔ année scolaire).
 *
 * La Registration est l'unique source de vérité du rattachement scolaire
 * (année / classe / statut / redoublant / boursier) : Student n'a plus de colonnes
 * de scolarité, ses getters correspondants sont dérivés de l'inscription courante.
 */
class RegistrationManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private RegistrationRepository $registrationRepository
    ) {
    }

    /**
     * Crée (ou récupère) l'inscription d'un élève pour une année, met à jour ses
     * informations de scolarité et synchronise les colonnes legacy de l'élève.
     *
     * Le niveau et le statut ne sont plus stockés sur l'inscription : le niveau se
     * déduit de la classe, le statut de la présence d'une classe. Le paramètre
     * $level ne sert qu'à la synchronisation legacy quand il n'y a pas de classe
     * (ex. inscription sans affectation, niveau souhaité conservé sur l'élève).
     *
     * Ne flush pas : l'appelant maîtrise le moment du flush.
     */
    public function syncRegistration(
        Student $student,
        ?SchoolYear $year,
        ?Classroom $classroom,
        ?Level $level = null,
        bool $isRepeating = false,
        bool $boursier = false,
        ?PreRegistration $preRegistration = null
    ): ?Registration {
        // Sans année, pas d'inscription possible. Le paramètre $level n'est plus
        // utilisé (le niveau se déduit de la classe), conservé pour compatibilité.
        if (!$year) {
            return null;
        }

        $registration = $this->findRegistration($student, $year);

        if (!$registration) {
            $registration = new Registration();
            $registration->setSchoolYear($year);
            // L'inscription est rattachée à l'élève via sa préinscription d'origine
            // (l'élève n'est plus stocké directement sur l'inscription).
            if ($preRegistration !== null) {
                $registration->setPreRegistration($preRegistration);
            }
            $this->entityManager->persist($registration);
        }

        // Établissement : celui de l'élève, à défaut celui de la classe.
        $registration->setSchool($student->getSchool() ?? $classroom?->getSchool());
        $registration->setClassroom($classroom);
        $registration->setIsRepeating($isRepeating);
        $registration->setBoursier($boursier);

        return $registration;
    }

    /**
     * Recherche l'inscription d'un élève pour une année : d'abord parmi les
     * inscriptions chargées en mémoire (élève éventuellement pas encore flushé),
     * puis en base.
     */
    private function findRegistration(Student $student, SchoolYear $year): ?Registration
    {
        foreach ($student->getRegistrations() as $registration) {
            if ($registration->getSchoolYear()?->getId() === $year->getId()) {
                return $registration;
            }
        }

        if ($student->getId()) {
            return $this->registrationRepository->findOneByStudentAndYear($student->getId(), $year->getId());
        }

        return null;
    }
}
