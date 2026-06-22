<?php

namespace App\Service;

use App\Entity\PreRegistration;
use App\Entity\School;
use App\Entity\SchoolYear;
use App\Entity\Student;

/**
 * Construit une préinscription pré-remplie à partir d'un élève déjà connu de
 * l'établissement (réinscription / « ancien élève »).
 *
 * Mutualisé entre le back-office (PreRegistrationController::returning) et
 * l'espace parent (ParentPortalController::reenroll).
 */
class PreRegistrationFactory
{
    public function fromStudent(Student $student, School $school, ?SchoolYear $schoolYear): PreRegistration
    {
        $preRegistration = new PreRegistration();
        $preRegistration->setFirstName($student->getFirstName());
        $preRegistration->setLastName($student->getLastName());
        $preRegistration->setMatriculeNational($student->getMatriculeNational());
        $preRegistration->setDateOfBirth($student->getDateOfBirth());
        $preRegistration->setGender($student->getGender());
        $preRegistration->setPhone($student->getPhone());
        $preRegistration->setEmail($student->getEmail());
        $preRegistration->setAddress($student->getAddress());
        $preRegistration->setPlaceOfBirth($student->getPlaceOfBirth());
        $preRegistration->setNationality($student->getNationality());
        $preRegistration->setBirthCertificateNumber($student->getBirthCertificateNumber());
        $preRegistration->setCmuNumber($student->getCmuNumber());
        $preRegistration->setLastSchoolAttended($student->getLastSchoolAttended());
        $preRegistration->setIsRepeating($student->isRepeating());
        $preRegistration->setPhoto($student->getPhoto());
        $preRegistration->setParentName($student->getParentName());
        $preRegistration->setParentPhone($student->getParentPhone());
        $preRegistration->setParentEmail($student->getParentEmail());
        $preRegistration->setParentFunction($student->getParentFunction());
        $preRegistration->setParentAddress($student->getParentAddress());
        $preRegistration->setEmergencyContact($student->getEmergencyContact());
        $preRegistration->setEmergencyPhone($student->getEmergencyPhone());
        $preRegistration->setMedicalInfo($student->getMedicalInfo());
        $preRegistration->setNotes($student->getNotes());
        $preRegistration->setRequestedLevel($student->getLevel());
        $preRegistration->setSchool($school);
        $preRegistration->setSchoolYear($schoolYear);

        // Réinscription : on conserve le lien vers l'élève existant pour le réutiliser
        // à l'inscription (pas de duplication) — marque aussi le type « returning ».
        $preRegistration->setExistingStudent($student);

        return $preRegistration;
    }
}
