<?php

namespace App\Theme;

use App\Entity\School;
use App\Entity\User;
use App\Repository\StudentRepository;
use App\Service\ParentPortalService;
use App\Service\SchoolContextService;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Établissement dont les portails (élève, parent, enseignant) doivent reprendre
 * l'identité visuelle.
 *
 * Élèves et parents ne sont pas rattachés à un établissement (User::getSchools()
 * vide) : SchoolContextService leur renverrait le premier établissement actif,
 * sans rapport avec eux. On part donc de leurs fiches élèves.
 *  - élève : l'établissement de sa fiche ;
 *  - parent : l'établissement commun à ses enfants ; s'ils sont scolarisés dans
 *    plusieurs établissements, aucun (thème par défaut) ;
 *  - personnel (enseignant…) : l'établissement courant.
 */
final class PortalSchoolResolver
{
    private bool $resolved = false;
    private ?School $school = null;

    public function __construct(
        private readonly Security $security,
        private readonly SchoolContextService $context,
        private readonly StudentRepository $students,
        private readonly ParentPortalService $parentPortal,
    ) {
    }

    public function resolve(): ?School
    {
        if (!$this->resolved) {
            $this->school = $this->doResolve();
            $this->resolved = true;
        }

        return $this->school;
    }

    private function doResolve(): ?School
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return null;
        }

        $roles = $user->getRoles();

        if (in_array('ROLE_ELEVE', $roles, true)) {
            return $this->students->findOneBy(['studentUser' => $user])?->getSchool();
        }

        if (in_array('ROLE_PARENT', $roles, true)) {
            $schools = [];
            foreach ($this->parentPortal->getChildren($user) as $child) {
                if ($school = $child->getSchool()) {
                    $schools[$school->getId()] = $school;
                }
            }

            return count($schools) === 1 ? reset($schools) : null;
        }

        return $this->context->getCurrentSchool();
    }
}
