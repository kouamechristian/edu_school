<?php

namespace App\DataFixtures;

use App\Entity\Room;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class Module3Fixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        // Récupérer les écoles des fixtures Module1
        $schools = [
            $this->getReference('school_1'),
            $this->getReference('school_2'),
            $this->getReference('school_3'),
        ];

        foreach ($schools as $index => $school) {
            $this->createRoomsForSchool($manager, $school, $index + 1);
        }

        $manager->flush();
    }

    private function createRoomsForSchool(ObjectManager $manager, $school, $schoolIndex): void
    {
        // Salles de classe
        $classrooms = [
            ['code' => 'S101', 'name' => 'Salle de classe 101', 'type' => 'classroom', 'building' => 'A', 'floor' => 'RDC', 'capacity' => 30],
            ['code' => 'S102', 'name' => 'Salle de classe 102', 'type' => 'classroom', 'building' => 'A', 'floor' => 'RDC', 'capacity' => 30],
            ['code' => 'S103', 'name' => 'Salle de classe 103', 'type' => 'classroom', 'building' => 'A', 'floor' => 'RDC', 'capacity' => 25],
            ['code' => 'S201', 'name' => 'Salle de classe 201', 'type' => 'classroom', 'building' => 'A', 'floor' => '1er', 'capacity' => 30],
            ['code' => 'S202', 'name' => 'Salle de classe 202', 'type' => 'classroom', 'building' => 'A', 'floor' => '1er', 'capacity' => 30],
            ['code' => 'S203', 'name' => 'Salle de classe 203', 'type' => 'classroom', 'building' => 'A', 'floor' => '1er', 'capacity' => 25],
        ];

        foreach ($classrooms as $data) {
            $room = new Room();
            $room->setSchool($school);
            $room->setCode($data['code']);
            $room->setName($data['name']);
            $room->setType($data['type']);
            $room->setBuilding($data['building']);
            $room->setFloor($data['floor']);
            $room->setCapacity($data['capacity']);
            $room->setIsActive(true);

            $manager->persist($room);
        }

        // Laboratoires
        $labs = [
            ['code' => 'LAB1', 'name' => 'Laboratoire de Sciences', 'type' => 'laboratory', 'building' => 'B', 'floor' => 'RDC', 'capacity' => 24, 'equipment' => 'Microscopes, Paillasses, Équipement de chimie'],
            ['code' => 'LAB2', 'name' => 'Laboratoire de Physique', 'type' => 'laboratory', 'building' => 'B', 'floor' => '1er', 'capacity' => 24, 'equipment' => 'Oscilloscopes, Générateurs, Multimètres'],
        ];

        foreach ($labs as $data) {
            $room = new Room();
            $room->setSchool($school);
            $room->setCode($data['code']);
            $room->setName($data['name']);
            $room->setType($data['type']);
            $room->setBuilding($data['building']);
            $room->setFloor($data['floor']);
            $room->setCapacity($data['capacity']);
            $room->setEquipment($data['equipment']);
            $room->setIsActive(true);

            $manager->persist($room);
        }

        // Salles informatiques
        $computerRooms = [
            ['code' => 'INFO1', 'name' => 'Salle informatique 1', 'type' => 'computer_room', 'building' => 'B', 'floor' => 'RDC', 'capacity' => 30, 'equipment' => '30 ordinateurs, Projecteur, Imprimante'],
            ['code' => 'INFO2', 'name' => 'Salle informatique 2', 'type' => 'computer_room', 'building' => 'B', 'floor' => '1er', 'capacity' => 20, 'equipment' => '20 ordinateurs, Tableau interactif'],
        ];

        foreach ($computerRooms as $data) {
            $room = new Room();
            $room->setSchool($school);
            $room->setCode($data['code']);
            $room->setName($data['name']);
            $room->setType($data['type']);
            $room->setBuilding($data['building']);
            $room->setFloor($data['floor']);
            $room->setCapacity($data['capacity']);
            $room->setEquipment($data['equipment']);
            $room->setIsActive(true);

            $manager->persist($room);
        }

        // Autres espaces
        $otherRooms = [
            ['code' => 'AMPHI', 'name' => 'Amphithéâtre', 'type' => 'amphitheater', 'building' => 'C', 'floor' => 'RDC', 'capacity' => 100, 'equipment' => 'Système audio, Projecteur, Écran géant'],
            ['code' => 'GYM', 'name' => 'Gymnase', 'type' => 'gym', 'building' => 'D', 'floor' => 'RDC', 'capacity' => 50, 'equipment' => 'Matériel sportif, Vestiaires'],
            ['code' => 'BIB', 'name' => 'Bibliothèque', 'type' => 'library', 'building' => 'C', 'floor' => '1er', 'capacity' => 40, 'equipment' => 'Livres, Ordinateurs, Postes de lecture'],
            ['code' => 'MULTI', 'name' => 'Salle polyvalente', 'type' => 'multipurpose', 'building' => 'C', 'floor' => 'RDC', 'capacity' => 80, 'equipment' => 'Tables mobiles, Chaises, Système audio'],
        ];

        foreach ($otherRooms as $data) {
            $room = new Room();
            $room->setSchool($school);
            $room->setCode($data['code']);
            $room->setName($data['name']);
            $room->setType($data['type']);
            $room->setBuilding($data['building']);
            $room->setFloor($data['floor']);
            $room->setCapacity($data['capacity']);
            $room->setEquipment($data['equipment'] ?? null);
            $room->setIsActive(true);

            $manager->persist($room);
        }
    }

    public function getDependencies(): array
    {
        return [
            Module1Fixtures::class,
        ];
    }
}
