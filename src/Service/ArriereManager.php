<?php

namespace App\Service;

use App\Entity\Fee;
use App\Entity\Registration;
use App\Entity\School;
use App\Entity\Student;
use App\Entity\StudentFee;
use App\Entity\SchoolYear;
use App\Repository\FeeRepository;
use App\Repository\RegistrationRepository;
use App\Repository\StudentFeeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Création et report des arriérés.
 *
 * Centralise :
 *  - le « frais conteneur » d'arriérés par établissement (partagé par l'import Excel
 *    {@see ArriereImporter} et le report automatique) ;
 *  - la création d'une ligne d'arriéré pour un élève ;
 *  - le report automatique du solde impayé d'une année sur l'inscription suivante
 *    (appelé à la réinscription depuis {@see EnrollmentService}).
 */
class ArriereManager
{
    private const EPSILON = 0.005;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly FeeRepository $feeRepository,
        private readonly StudentFeeRepository $studentFeeRepository,
        private readonly RegistrationRepository $registrationRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Retrouve (ou crée) le frais « conteneur » d'arriérés de l'établissement pour une
     * année d'origine donnée. Le montant réel est porté par chaque StudentFee ; ce frais
     * ne sert que de rattachement commun (catégorie « autre_frais »).
     *
     * En mode $persist, le frais neuf est persisté ET flushé immédiatement : il obtient
     * son identifiant et est committé seul, avant tout StudentFee. Ce découplage est
     * nécessaire car un subscriber (journal d'activité) reflushe en postFlush ; insérer
     * un Fee neuf et des StudentFee dépendants dans un même flush n'est pas fiable.
     */
    public function resolveArriereFee(School $school, string $anneeOrigine, bool $persist): Fee
    {
        $code = sprintf('ARRIERE-%s-%d', $anneeOrigine, $school->getId());

        $fee = $this->feeRepository->findOneBy(['code' => $code]);
        if ($fee !== null) {
            return $fee;
        }

        $fee = new Fee();
        $fee->setName(sprintf('Arriéré %s', $anneeOrigine));
        $fee->setCode($code);
        $fee->setSchool($school);
        $fee->setAmount('0.00');
        $fee->setType('non_affecte');
        $fee->setCategory('autre_frais');
        $fee->setFrequency('unique');
        $fee->setIsActive(true);
        $fee->setDescription(sprintf('Report des impayés de l\'année %s.', $anneeOrigine));

        if ($persist) {
            $this->entityManager->persist($fee);
            $this->entityManager->flush();
        }

        return $fee;
    }

    /**
     * Crée une ligne d'arriéré pour un élève sur une inscription donnée, et flushe.
     * Idempotent : ne crée rien si l'élève a déjà un arriéré pour cette année d'origine.
     *
     * @return StudentFee|null la ligne créée, ou null si elle existait déjà / montant nul
     */
    public function addArriere(Student $student, Registration $registration, float $montant, string $anneeOrigine): ?StudentFee
    {
        if ($montant <= self::EPSILON) {
            return null;
        }

        $school = $student->getSchool();
        if ($school === null) {
            return null;
        }

        $fee = $this->resolveArriereFee($school, $anneeOrigine, true);

        if ($this->studentFeeRepository->findOneForStudentAndFee($student->getId(), $fee->getId()) !== null) {
            return null; // déjà un arriéré pour cette année d'origine
        }

        $studentFee = new StudentFee();
        $studentFee->setStudent($student);
        $studentFee->setFee($fee);
        $studentFee->setRegistration($registration);
        $studentFee->setAmount(number_format($montant, 2, '.', ''));
        $studentFee->setIsArriereAnterieur(true);
        $studentFee->setAnneeOrigine($anneeOrigine);

        $registration->addStudentFee($studentFee);
        $student->addStudentFee($studentFee);
        $fee->addStudentFee($studentFee);

        $this->entityManager->persist($studentFee);
        $this->entityManager->flush();

        return $studentFee;
    }

    /**
     * Reporte le solde impayé de l'inscription précédente de l'élève sur sa nouvelle
     * inscription, sous forme d'un arriéré étiqueté avec l'année d'origine.
     *
     * L'année précédente conserve ses chiffres réels (paiements et reste non modifiés) :
     * la dette y reste visible telle qu'elle était (« dette réelle » de l'année d'origine).
     * L'arriéré la rend recouvrable et payable sur la nouvelle année, et il est compté dans
     * le total de la nouvelle inscription. Le double comptage au niveau du total *toutes
     * années* de l'élève est évité côté {@see \App\Entity\Student::getTotalTuition()}, qui
     * exclut les lignes d'arriéré (la dette y est déjà portée par les frais réels de
     * l'année d'origine).
     *
     * @return StudentFee|null l'arriéré créé, ou null s'il n'y a rien à reporter
     */
    public function carryForwardPreviousBalance(Registration $newRegistration): ?StudentFee
    {
        $student = $newRegistration->getStudent();
        if ($student === null) {
            return null;
        }

        $previous = $this->findPreviousRegistration($student, $newRegistration);
        if ($previous === null) {
            return null;
        }

        $remaining = $previous->getRemainingTuition();
        if ($remaining <= self::EPSILON) {
            return null;
        }

        $anneeOrigine = $previous->getSchoolYear()?->getName();
        if ($anneeOrigine === null) {
            return null;
        }

        $arriere = $this->addArriere($student, $newRegistration, $remaining, $anneeOrigine);
        if ($arriere === null) {
            return null; // déjà reporté
        }

        $this->logger->info('Arriéré reporté automatiquement à la réinscription', [
            'student' => $student->getFullName(),
            'origine' => $anneeOrigine,
            'montant' => $remaining,
            'nouvelle_annee' => $newRegistration->getSchoolYear()?->getName(),
        ]);

        return $arriere;
    }

    /**
     * Inscription de l'élève à l'année immédiatement antérieure à la nouvelle, le cas échéant.
     *
     * On s'appuie sur l'historique récupéré en base ({@see RegistrationRepository::findHistoryForStudent})
     * plutôt que sur la reconstruction en mémoire {@see Student::getRegistrations()}, qui peut être
     * périmée juste après une réinscription (collection déjà hydratée). Le classement chronologique
     * se fait sur le NOM d'année (« 2024-2025 », toujours renseigné et trié correctement), avec la
     * date de début en repli : ainsi le report ne tombe plus silencieusement à l'eau lorsqu'une année
     * n'a pas de date de début saisie.
     */
    private function findPreviousRegistration(Student $student, Registration $newRegistration): ?Registration
    {
        $newKey = $this->yearSortKey($newRegistration->getSchoolYear());
        if ($newKey === null) {
            return null;
        }

        $previous = null;
        $previousKey = null;

        foreach ($this->registrationRepository->findHistoryForStudent($student) as $registration) {
            if ($registration->getId() !== null && $registration->getId() === $newRegistration->getId()) {
                continue;
            }
            $key = $this->yearSortKey($registration->getSchoolYear());
            if ($key === null || $key >= $newKey) {
                continue; // on ne garde que les années strictement antérieures
            }
            if ($previousKey === null || $key > $previousKey) {
                $previous = $registration;
                $previousKey = $key;
            }
        }

        return $previous;
    }

    /**
     * Clé de tri chronologique d'une année scolaire : son nom (« 2024-2025 » se trie
     * correctement) ou, à défaut, sa date de début. Retourne null si l'année est indéterminable.
     */
    private function yearSortKey(?SchoolYear $year): ?string
    {
        if ($year === null) {
            return null;
        }

        $name = $year->getName();
        if ($name !== null && $name !== '') {
            return $name;
        }

        return $year->getStartDate()?->format('Y-m-d');
    }
}
