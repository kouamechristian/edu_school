<?php

namespace App\DataFixtures;

use App\Entity\Level;
use App\Entity\Period;
use App\Entity\School;
use App\Entity\SchoolGroup;
use App\Entity\SchoolYear;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class Module1Fixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        // Créer des groupes d'établissements
        $groups = $this->createSchoolGroups($manager);
        
        // Créer des établissements
        $schools = $this->createSchools($manager, $groups);
        
        // Créer des années scolaires
        $this->createSchoolYears($manager, $schools);
        
        // Créer des niveaux (globaux et par établissement)
        $this->createLevels($manager, $schools);
        
        $manager->flush();
    }

    private function createSchoolGroups(ObjectManager $manager): array
    {
        $groups = [];

        // Groupe 1 : Enseignement Maternel et Primaire
        $group1 = new SchoolGroup();
        $group1->setName('Groupe Enseignement Fondamental')
            ->setCode('GRP001')
            ->setDescription('Regroupement des établissements maternels et primaires')
            ->setIsActive(true);
        $manager->persist($group1);
        $groups[] = $group1;

        // Groupe 2 : Enseignement Secondaire
        $group2 = new SchoolGroup();
        $group2->setName('Groupe Enseignement Secondaire')
            ->setCode('GRP002')
            ->setDescription('Regroupement des collèges et lycées')
            ->setIsActive(true);
        $manager->persist($group2);
        $groups[] = $group2;

        // Groupe 3 : Enseignement Supérieur
        $group3 = new SchoolGroup();
        $group3->setName('Groupe Enseignement Supérieur')
            ->setCode('GRP003')
            ->setDescription('Regroupement des universités et grandes écoles')
            ->setIsActive(true);
        $manager->persist($group3);
        $groups[] = $group3;

        return $groups;
    }

    private function createSchools(ObjectManager $manager, array $groups): array
    {
        $schools = [];
        
        // Maternelle - Groupe 1
        $maternelle = new School();
        $maternelle->setName('École Maternelle Les Petits Bambins')
            ->setCode('MAT001')
            ->setType('maternelle')
            ->setDirector('Mme Marie DUPONT')
            ->setAddress('15 Rue des Fleurs, 75001 Paris')
            ->setPhone('01 23 45 67 89')
            ->setEmail('contact@maternelle-bambins.fr')
            ->setSchoolGroup($groups[0])
            ->setIsActive(true);
        $manager->persist($maternelle);
        $schools[] = $maternelle;

        // Primaire - Groupe 1
        $primaire = new School();
        $primaire->setName('École Primaire Jean Moulin')
            ->setCode('PRI001')
            ->setType('primaire')
            ->setDirector('M. Jean MARTIN')
            ->setAddress('25 Avenue Victor Hugo, 75002 Paris')
            ->setPhone('01 34 56 78 90')
            ->setEmail('contact@primaire-moulin.fr')
            ->setSchoolGroup($groups[0])
            ->setIsActive(true);
        $manager->persist($primaire);
        $schools[] = $primaire;

        // Collège - Groupe 2
        $college = new School();
        $college->setName('Collège Pierre et Marie Curie')
            ->setCode('COL001')
            ->setType('college')
            ->setDirector('Mme Sophie BERNARD')
            ->setAddress('30 Boulevard de la République, 75003 Paris')
            ->setPhone('01 45 67 89 01')
            ->setEmail('contact@college-curie.fr')
            ->setSchoolGroup($groups[1])
            ->setIsActive(true);
        $manager->persist($college);
        $schools[] = $college;

        // Lycée - Groupe 2
        $lycee = new School();
        $lycee->setName('Lycée Victor Hugo')
            ->setCode('LYC001')
            ->setType('lycee')
            ->setDirector('M. Pierre DUBOIS')
            ->setAddress('50 Rue de la Liberté, 75004 Paris')
            ->setPhone('01 56 78 90 12')
            ->setEmail('contact@lycee-hugo.fr')
            ->setSchoolGroup($groups[1])
            ->setIsActive(true);
        $manager->persist($lycee);
        $schools[] = $lycee;

        // Université - Groupe 3
        $universite = new School();
        $universite->setName('Université Paris Sciences')
            ->setCode('UNI001')
            ->setType('universite')
            ->setDirector('Pr. Jacques LEROY')
            ->setAddress('100 Avenue de la Connaissance, 75005 Paris')
            ->setPhone('01 67 89 01 23')
            ->setEmail('contact@univ-paris-sciences.fr')
            ->setSchoolGroup($groups[2])
            ->setIsActive(true);
        $manager->persist($universite);
        $schools[] = $universite;

        return $schools;
    }

    private function createSchoolYears(ObjectManager $manager, array $schools): void
    {
        // Année 2023-2024 (terminée)
        $year2023 = new SchoolYear();
        $year2023->setName('2023-2024')
            ->setStartDate(new \DateTime('2023-09-01'))
            ->setEndDate(new \DateTime('2024-06-30'))
            ->setIsCurrent(false);
        $manager->persist($year2023);

        // Année 2024-2025 (en cours)
        $year2024 = new SchoolYear();
        $year2024->setName('2024-2025')
            ->setStartDate(new \DateTime('2024-09-01'))
            ->setEndDate(new \DateTime('2025-06-30'))
            ->setIsCurrent(true);
        $manager->persist($year2024);

        // Année 2025-2026 (à venir)
        $year2025 = new SchoolYear();
        $year2025->setName('2025-2026')
            ->setStartDate(new \DateTime('2025-09-01'))
            ->setEndDate(new \DateTime('2026-06-30'))
            ->setIsCurrent(false);
        $manager->persist($year2025);

        // Créer les périodes pour l'année en cours (trimestres)
        $this->createPeriods($manager, $year2024);
    }

    private function createPeriods(ObjectManager $manager, SchoolYear $schoolYear): void
    {
        // 1er Trimestre
        $trimestre1 = new Period();
        $trimestre1->setSchoolYear($schoolYear)
            ->setName('1er Trimestre')
            ->setType('trimestre')
            ->setStartDate(new \DateTime('2024-09-01'))
            ->setEndDate(new \DateTime('2024-12-20'))
            ->setWeight('0.33');
        $manager->persist($trimestre1);

        // 2ème Trimestre
        $trimestre2 = new Period();
        $trimestre2->setSchoolYear($schoolYear)
            ->setName('2ème Trimestre')
            ->setType('trimestre')
            ->setStartDate(new \DateTime('2025-01-07'))
            ->setEndDate(new \DateTime('2025-03-31'))
            ->setWeight('0.33');
        $manager->persist($trimestre2);

        // 3ème Trimestre
        $trimestre3 = new Period();
        $trimestre3->setSchoolYear($schoolYear)
            ->setName('3ème Trimestre')
            ->setType('trimestre')
            ->setStartDate(new \DateTime('2025-04-01'))
            ->setEndDate(new \DateTime('2025-06-30'))
            ->setWeight('0.34');
        $manager->persist($trimestre3);
    }

    private function createLevels(ObjectManager $manager, array $schools): void
    {
        // Niveaux globaux (school = null, disponibles partout)
        $levels = [
            // Maternelle
            ['name' => 'Petite Section (PS)', 'category' => 'maternelle', 'order' => 1, 'description' => 'Première année de maternelle, 3 ans'],
            ['name' => 'Moyenne Section (MS)', 'category' => 'maternelle', 'order' => 2, 'description' => 'Deuxième année de maternelle, 4 ans'],
            ['name' => 'Grande Section (GS)', 'category' => 'maternelle', 'order' => 3, 'description' => 'Dernière année de maternelle, 5 ans'],
            
            // Primaire
            ['name' => 'CP (Cours Préparatoire)', 'category' => 'primaire', 'order' => 4, 'description' => 'Apprentissage de la lecture et de l\'écriture'],
            ['name' => 'CE1 (Cours Élémentaire 1)', 'category' => 'primaire', 'order' => 5, 'description' => 'Consolidation des apprentissages fondamentaux'],
            ['name' => 'CE2 (Cours Élémentaire 2)', 'category' => 'primaire', 'order' => 6, 'description' => 'Approfondissement des connaissances'],
            ['name' => 'CM1 (Cours Moyen 1)', 'category' => 'primaire', 'order' => 7, 'description' => 'Préparation au CM2'],
            ['name' => 'CM2 (Cours Moyen 2)', 'category' => 'primaire', 'order' => 8, 'description' => 'Dernière année de primaire'],
            
            // Collège
            ['name' => '6ème', 'category' => 'college', 'order' => 9, 'description' => 'Première année de collège, cycle 3'],
            ['name' => '5ème', 'category' => 'college', 'order' => 10, 'description' => 'Deuxième année de collège, cycle 4'],
            ['name' => '4ème', 'category' => 'college', 'order' => 11, 'description' => 'Troisième année de collège, cycle 4'],
            ['name' => '3ème', 'category' => 'college', 'order' => 12, 'description' => 'Dernière année de collège, préparation au brevet'],
            
            // Lycée
            ['name' => 'Seconde', 'category' => 'lycee', 'order' => 13, 'description' => 'Classe de détermination'],
            ['name' => 'Première', 'category' => 'lycee', 'order' => 14, 'description' => 'Première année du cycle terminal'],
            ['name' => 'Terminale', 'category' => 'lycee', 'order' => 15, 'description' => 'Année du baccalauréat'],
            
            // Université
            ['name' => 'Licence 1', 'category' => 'universite', 'order' => 16, 'description' => 'Première année de licence'],
            ['name' => 'Licence 2', 'category' => 'universite', 'order' => 17, 'description' => 'Deuxième année de licence'],
            ['name' => 'Licence 3', 'category' => 'universite', 'order' => 18, 'description' => 'Troisième année de licence'],
            ['name' => 'Master 1', 'category' => 'universite', 'order' => 19, 'description' => 'Première année de master'],
            ['name' => 'Master 2', 'category' => 'universite', 'order' => 20, 'description' => 'Deuxième année de master'],
        ];

        foreach ($levels as $levelData) {
            $level = new Level();
            $level->setName($levelData['name'])
                ->setCategory($levelData['category'])
                ->setOrderNumber($levelData['order'])
                ->setDescription($levelData['description'])
                ->setIsActive(true);
            $manager->persist($level);
        }
    }
}

