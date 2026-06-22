<?php

namespace App\Service;

use App\Entity\School;
use App\Entity\SchoolYear;
use App\Entity\User;
use App\Repository\SchoolRepository;
use App\Repository\SchoolYearRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

class SchoolContextService
{
    private const SESSION_SCHOOL_KEY = 'current_school_id';
    private const SESSION_YEAR_KEY = 'current_school_year_id';

    public function __construct(
        private RequestStack $requestStack,
        private SchoolRepository $schoolRepository,
        private SchoolYearRepository $schoolYearRepository,
        private Security $security,
        private EntityManagerInterface $entityManager
    ) {
    }

    /**
     * Obtenir l'établissement en cours
     */
    public function getCurrentSchool(): ?School
    {
        $user = $this->security->getUser();

        // Sans utilisateur authentifié (page de login, requête publique, sous-requête
        // sans contexte de sécurité), on ne touche NI la session NI la base : sinon la
        // page de login amorcerait current_school_id avec le premier établissement et
        // écraserait le choix réel de l'utilisateur juste après sa connexion.
        if (!$user instanceof User) {
            $available = $this->getAvailableSchools();
            return $available[0] ?? null;
        }

        $session = $this->requestStack->getSession();
        $schoolId = $session->get(self::SESSION_SCHOOL_KEY);

        $available = $this->getAvailableSchools();

        if ($schoolId) {
            $school = $this->schoolRepository->find($schoolId);
            // L'établissement en session doit rester dans la liste autorisée.
            if ($school && $this->isSchoolAllowed($school, $available)) {
                // On reflète l'établissement actif sur l'utilisateur pour qu'il
                // soit restauré après déconnexion, même sans clic explicite sur la bascule.
                $this->rememberLastSchool($school);
                return $school;
            }
        }

        // Aucun établissement en session (déconnexion, expiration, 1re visite) :
        // on restaure le dernier choix persisté de l'utilisateur s'il est encore autorisé.
        $lastSchool = $user->getLastSchool();
        if ($lastSchool && $this->isSchoolAllowed($lastSchool, $available)) {
            $this->setCurrentSchool($lastSchool);
            return $lastSchool;
        }

        // Sinon, prendre le premier établissement autorisé.
        if (!empty($available)) {
            $school = $available[0];
            $this->setCurrentSchool($school);
            return $school;
        }

        return null;
    }

    /**
     * Vérifie qu'un établissement fait partie de la liste autorisée pour l'utilisateur.
     *
     * @param School[] $available
     */
    public function isSchoolAllowed(School $school, ?array $available = null): bool
    {
        $available ??= $this->getAvailableSchools();
        foreach ($available as $s) {
            if ($s->getId() === $school->getId()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Définir l'établissement en cours
     */
    public function setCurrentSchool(School $school): void
    {
        $session = $this->requestStack->getSession();
        $session->set(self::SESSION_SCHOOL_KEY, $school->getId());

        // Mémoriser le choix sur l'utilisateur pour qu'il survive à la déconnexion
        // et à l'expiration de la session.
        $this->rememberLastSchool($school);

        // Charger automatiquement l'année en cours (globale)
        $currentYear = $this->schoolYearRepository->findCurrent();
        if ($currentYear) {
            $this->setCurrentSchoolYear($currentYear);
        }
    }

    /**
     * Persiste sur l'utilisateur connecté le dernier établissement utilisé.
     *
     * On re-charge l'utilisateur depuis l'EntityManager par son identifiant pour
     * être certain de manipuler une entité suivie (l'instance du jeton de sécurité
     * peut être détachée selon le contexte), garantissant ainsi le flush en base.
     */
    private function rememberLastSchool(School $school): void
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return;
        }

        $managedUser = $this->entityManager->getRepository(User::class)->find($user->getId());
        if (!$managedUser instanceof User) {
            return;
        }

        if ($managedUser->getLastSchool()?->getId() === $school->getId()) {
            return;
        }

        $managedUser->setLastSchool($school);
        $this->entityManager->flush();
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
     * Obtenir les établissements disponibles pour l'utilisateur courant.
     *
     * Un utilisateur ne voit que les établissements (actifs) auxquels il est rattaché.
     * Les super-administrateurs (ou les utilisateurs sans rattachement) voient tous les
     * établissements actifs.
     */
    public function getAvailableSchools(): array
    {
        $user = $this->security->getUser();

        // Seul un super-administrateur « réel » (rôle stocké, hors héritage) voit tout.
        // Le fondateur, bien qu'héritant de ROLE_SUPER_ADMIN, reste limité à ses établissements.
        $isRealSuperAdmin = $user instanceof User && in_array('ROLE_SUPER_ADMIN', $user->getRoles(), true);

        if ($user instanceof User && !$isRealSuperAdmin) {
            $schools = [];
            foreach ($user->getSchools() as $school) {
                if ($school->isActive()) {
                    $schools[] = $school;
                }
            }

            // Si l'utilisateur est rattaché à au moins un établissement, on se limite à ceux-ci.
            if (!empty($schools)) {
                usort($schools, fn (School $a, School $b) => strcmp((string) $a->getName(), (string) $b->getName()));
                return $schools;
            }
        }

        // Super-admin ou utilisateur sans rattachement : tous les établissements actifs.
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

