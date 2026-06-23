<?php

namespace App\Service;

use App\Entity\Classroom;
use App\Entity\PreRegistration;
use App\Entity\Registration;
use App\Entity\SchoolYear;
use App\Entity\Student;
use App\Repository\RegistrationRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Inscription d'un élève à partir d'une préinscription validée.
 *
 * Centralise le workflow :
 *  - nouvel élève   → crée l'enregistrement Student puis sa registration de l'année ;
 *  - ancien élève   → réutilise le Student existant (met à jour les infos modifiables)
 *                     et crée une nouvelle registration, sans dupliquer l'élève.
 *
 * Garantit qu'un élève n'est inscrit qu'une seule fois par année scolaire et
 * conserve l'historique des registrations des années précédentes.
 */
class EnrollmentService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private RegistrationManager $registrationManager,
        private FeeAssignmentService $feeAssignmentService,
        private RegistrationRepository $registrationRepository,
        private MatriculeGenerator $matriculeGenerator,
    ) {
    }

    /**
     * Inscrit la préinscription dans la classe donnée et retourne la registration créée.
     *
     * @throws \RuntimeException si la préinscription n'est pas inscriptible, si
     *         l'année est absente, ou si l'élève est déjà inscrit pour cette année.
     */
    public function enrollFromPreRegistration(
        PreRegistration $preRegistration,
        Classroom $classroom,
        ?SchoolYear $fallbackYear = null
    ): Registration {
        if (!$preRegistration->canBeEnrolled()) {
            throw new \RuntimeException('Cette préinscription ne peut pas être inscrite dans son état actuel (elle doit être validée).');
        }

        $year = $preRegistration->getSchoolYear() ?? $fallbackYear;
        if (!$year) {
            throw new \RuntimeException('Aucune année scolaire n\'est définie pour cette inscription.');
        }

        $isReturning = $preRegistration->isReturning() && $preRegistration->getExistingStudent() !== null;

        $student = $isReturning
            ? $this->reuseExistingStudent($preRegistration)
            : $this->createStudent($preRegistration);

        // Contrainte : un élève ne peut être inscrit qu'une seule fois par année.
        if ($student->getId() !== null
            && $this->registrationRepository->findOneByStudentAndYear($student->getId(), $year->getId()) !== null) {
            throw new \RuntimeException(sprintf(
                '%s est déjà inscrit(e) pour l\'année scolaire %s.',
                $student->getFullName(),
                $year->getName()
            ));
        }

        $level = $classroom->getLevel() ?? $preRegistration->getRequestedLevel();

        $registration = $this->registrationManager->syncRegistration(
            $student,
            $year,
            $classroom,
            $level,
            $preRegistration->isRepeating(),
            false,
            $preRegistration
        );

        // Marque la préinscription comme inscrite.
        $preRegistration->setStatus('enrolled');
        $preRegistration->setEnrolledAt(new \DateTime());

        // Nouvel élève : on matérialise le lien d'origine OneToOne.
        // Ancien élève : le lien est déjà porté par existingStudent (réinscription).
        if (!$isReturning) {
            $student->setPreRegistration($preRegistration);
            $preRegistration->setStudent($student);
        }

        $this->entityManager->flush();

        // Affectation automatique des frais de scolarité du niveau à la registration.
        // (assignScolariteFeesForRegistration crée les frais manquants ET rattache à
        // l'inscription les frais déjà affectés à la validation de la préinscription.)
        if ($registration !== null) {
            $this->feeAssignmentService->assignScolariteFeesForRegistration($registration);
            $this->entityManager->flush();
        }

        return $registration;
    }

    /**
     * Crée un nouvel élève (référentiel) à partir de la préinscription.
     */
    private function createStudent(PreRegistration $pre): Student
    {
        $student = new Student();

        // Identité (figée à la création).
        $student->setFirstName($pre->getFirstName());
        $student->setLastName($pre->getLastName());
        $student->setDateOfBirth($pre->getDateOfBirth());
        $student->setGender($pre->getGender());
        $student->setPlaceOfBirth($pre->getPlaceOfBirth());
        $student->setNationality($pre->getNationality());
        $student->setBirthCertificateNumber($pre->getBirthCertificateNumber());
        $student->setSchool($pre->getSchool());
        $student->setMatriculeNational($pre->getMatriculeNational());
        $student->setMatriculeInterne(
            $this->matriculeGenerator->generate($this->entityManager, Student::class)
        );

        // Informations modifiables (contact / tuteur / scolaires).
        $this->applyMutableData($student, $pre);

        $this->entityManager->persist($student);

        return $student;
    }

    /**
     * Réutilise l'élève existant (ancien élève) et met à jour uniquement les
     * informations susceptibles d'avoir changé.
     */
    private function reuseExistingStudent(PreRegistration $pre): Student
    {
        $student = $pre->getExistingStudent();

        // Mise à jour des informations modifiables (sans toucher à l'identité).
        $this->applyMutableData($student, $pre);

        return $student;
    }

    /**
     * Reporte les champs modifiables de la préinscription vers l'élève (contact,
     * tuteur, infos scolaires/médicales). N'écrase jamais l'identité.
     */
    private function applyMutableData(Student $student, PreRegistration $pre): void
    {
        $student->setPhone($pre->getPhone());
        $student->setEmail($pre->getEmail());
        $student->setAddress($pre->getAddress());
        $student->setCmuNumber($pre->getCmuNumber());
        $student->setLastSchoolAttended($pre->getLastSchoolAttended());
        $student->setParentName($pre->getParentName());
        $student->setParentPhone($pre->getParentPhone());
        $student->setParentEmail($pre->getParentEmail());
        $student->setParentFunction($pre->getParentFunction());
        $student->setParentAddress($pre->getParentAddress());
        $student->setEmergencyContact($pre->getEmergencyContact());
        $student->setEmergencyPhone($pre->getEmergencyPhone());
        $student->setMedicalInfo($pre->getMedicalInfo());
        $student->setNotes($pre->getNotes());

        // Photo : ne remplace que si la préinscription en fournit une.
        if ($pre->getPhoto()) {
            $student->setPhoto($pre->getPhoto());
        }
    }
}
