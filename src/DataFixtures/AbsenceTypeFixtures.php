<?php

namespace App\DataFixtures;

use App\Entity\AbsenceType;
use App\Entity\School;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;

class AbsenceTypeFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        // Récupérer toutes les écoles
        $schools = $manager->getRepository(School::class)->findAll();

        foreach ($schools as $school) {
            $this->createAbsenceTypes($manager, $school);
        }

        $manager->flush();
    }

    private function createAbsenceTypes(ObjectManager $manager, School $school): void
    {
        $absenceTypes = [
            // Types d'absences
            [
                'name' => 'Absence justifiée',
                'code' => 'ABS_JUST',
                'description' => 'Absence avec justificatif valide (maladie, rendez-vous médical, etc.)',
                'type' => 'absence',
                'requiresJustification' => true,
                'countsAsAbsence' => false,
                'penaltyPoints' => null,
                'color' => '#28a745',
            ],
            [
                'name' => 'Absence non justifiée',
                'code' => 'ABS_NJUST',
                'description' => 'Absence sans justificatif valide',
                'type' => 'absence',
                'requiresJustification' => false,
                'countsAsAbsence' => true,
                'penaltyPoints' => '2.00',
                'color' => '#dc3545',
            ],
            [
                'name' => 'Absence excusée',
                'code' => 'ABS_EXC',
                'description' => 'Absence excusée par la direction',
                'type' => 'absence',
                'requiresJustification' => false,
                'countsAsAbsence' => false,
                'penaltyPoints' => null,
                'color' => '#17a2b8',
            ],

            // Types de retards
            [
                'name' => 'Retard justifié',
                'code' => 'RET_JUST',
                'description' => 'Retard avec justificatif valide',
                'type' => 'retard',
                'requiresJustification' => true,
                'countsAsAbsence' => false,
                'penaltyPoints' => null,
                'color' => '#ffc107',
            ],
            [
                'name' => 'Retard non justifié',
                'code' => 'RET_NJUST',
                'description' => 'Retard sans justificatif valide',
                'type' => 'retard',
                'requiresJustification' => false,
                'countsAsAbsence' => false,
                'penaltyPoints' => '0.50',
                'color' => '#fd7e14',
            ],

            // Types de sorties anticipées
            [
                'name' => 'Sortie anticipée justifiée',
                'code' => 'SORT_JUST',
                'description' => 'Sortie anticipée avec autorisation',
                'type' => 'sortie_anticipee',
                'requiresJustification' => true,
                'countsAsAbsence' => false,
                'penaltyPoints' => null,
                'color' => '#20c997',
            ],
            [
                'name' => 'Sortie anticipée non justifiée',
                'code' => 'SORT_NJUST',
                'description' => 'Sortie anticipée sans autorisation',
                'type' => 'sortie_anticipee',
                'requiresJustification' => false,
                'countsAsAbsence' => true,
                'penaltyPoints' => '1.00',
                'color' => '#e83e8c',
            ],

            // Autres types
            [
                'name' => 'Exclusion temporaire',
                'code' => 'EXCL_TEMP',
                'description' => 'Exclusion temporaire de l\'établissement',
                'type' => 'absence',
                'requiresJustification' => false,
                'countsAsAbsence' => true,
                'penaltyPoints' => '5.00',
                'color' => '#6f42c1',
            ],
            [
                'name' => 'Dispense sport',
                'code' => 'DISP_SPORT',
                'description' => 'Dispense de cours d\'éducation physique',
                'type' => 'absence',
                'requiresJustification' => true,
                'countsAsAbsence' => false,
                'penaltyPoints' => null,
                'color' => '#6c757d',
            ],
            [
                'name' => 'Sortie pédagogique',
                'code' => 'SORT_PED',
                'description' => 'Sortie pédagogique ou voyage scolaire',
                'type' => 'absence',
                'requiresJustification' => false,
                'countsAsAbsence' => false,
                'penaltyPoints' => null,
                'color' => '#007bff',
            ],
        ];

        foreach ($absenceTypes as $absenceTypeData) {
            $absenceType = new AbsenceType();
            $absenceType->setName($absenceTypeData['name']);
            $absenceType->setCode($absenceTypeData['code']);
            $absenceType->setDescription($absenceTypeData['description']);
            $absenceType->setType($absenceTypeData['type']);
            $absenceType->setRequiresJustification($absenceTypeData['requiresJustification']);
            $absenceType->setCountsAsAbsence($absenceTypeData['countsAsAbsence']);
            $absenceType->setPenaltyPoints($absenceTypeData['penaltyPoints']);
            $absenceType->setColor($absenceTypeData['color']);
            $absenceType->setSchool($school);
            $absenceType->setIsActive(true);

            $manager->persist($absenceType);
        }
    }

    public function getDependencies(): array
    {
        return [
            SchoolFixtures::class,
        ];
    }
}
