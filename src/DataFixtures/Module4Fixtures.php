<?php

namespace App\DataFixtures;

use App\Entity\Evaluation;
use App\Entity\Grade;
use App\Entity\Period;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class Module4Fixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $schools = [
            $this->getReference('school_1'),
            $this->getReference('school_2'),
            $this->getReference('school_3'),
        ];

        $years = [
            $this->getReference('year_1'),
        ];

        foreach ($schools as $schoolIndex => $school) {
            foreach ($years as $year) {
                // Créer les périodes
                $periods = $this->createPeriods($manager, $school, $year);
                
                // Créer les évaluations et notes
                $this->createEvaluationsAndGrades($manager, $school, $periods);
            }
        }

        $manager->flush();
    }

    private function createPeriods(ObjectManager $manager, $school, $year): array
    {
        $periods = [];
        
        // 1er Trimestre
        $period1 = new Period();
        $period1->setSchool($school);
        $period1->setSchoolYear($year);
        $period1->setName('1er Trimestre');
        $period1->setCode('T1');
        $period1->setOrderNumber(1);
        $period1->setStartDate(new \DateTime('2024-09-01'));
        $period1->setEndDate(new \DateTime('2024-12-20'));
        $period1->setIsActive(true);
        $manager->persist($period1);
        $periods[] = $period1;

        // 2ème Trimestre
        $period2 = new Period();
        $period2->setSchool($school);
        $period2->setSchoolYear($year);
        $period2->setName('2ème Trimestre');
        $period2->setCode('T2');
        $period2->setOrderNumber(2);
        $period2->setStartDate(new \DateTime('2025-01-06'));
        $period2->setEndDate(new \DateTime('2025-03-28'));
        $period2->setIsActive(true);
        $manager->persist($period2);
        $periods[] = $period2;

        // 3ème Trimestre
        $period3 = new Period();
        $period3->setSchool($school);
        $period3->setSchoolYear($year);
        $period3->setName('3ème Trimestre');
        $period3->setCode('T3');
        $period3->setOrderNumber(3);
        $period3->setStartDate(new \DateTime('2025-04-14'));
        $period3->setEndDate(new \DateTime('2025-07-05'));
        $period3->setIsActive(true);
        $manager->persist($period3);
        $periods[] = $period3;

        return $periods;
    }

    private function createEvaluationsAndGrades(ObjectManager $manager, $school, array $periods): void
    {
        // Récupérer les classes de l'école (on prend les 2 premières)
        $classrooms = $manager->getRepository('App\Entity\Classroom')
            ->findBySchool($school->getId());

        if (empty($classrooms)) {
            return;
        }

        $classrooms = array_slice($classrooms, 0, 2);

        // Types d'évaluations
        $types = ['controle_continu', 'devoir_surveille', 'devoir_maison', 'examen'];
        
        foreach ($periods as $periodIndex => $period) {
            foreach ($classrooms as $classroom) {
                // Récupérer les matières de l'école
                $subjects = $manager->getRepository('App\Entity\Subject')
                    ->findBySchool($school->getId());

                if (empty($subjects)) {
                    continue;
                }

                // On prend les 3 premières matières
                $subjects = array_slice($subjects, 0, 3);

                foreach ($subjects as $subjectIndex => $subject) {
                    // Créer 2 évaluations par matière par période
                    for ($i = 1; $i <= 2; $i++) {
                        $evaluation = new Evaluation();
                        $evaluation->setClassroom($classroom);
                        $evaluation->setSubject($subject);
                        $evaluation->setPeriod($period);
                        $evaluation->setName("Évaluation #$i - " . $subject->getName());
                        $evaluation->setType($types[array_rand($types)]);
                        
                        // Date aléatoire dans la période
                        $startDate = $period->getStartDate();
                        $endDate = $period->getEndDate();
                        $randomDays = rand(7, 90);
                        $evalDate = (clone $startDate)->modify("+{$randomDays} days");
                        if ($evalDate > $endDate) {
                            $evalDate = $endDate;
                        }
                        $evaluation->setDate($evalDate);
                        
                        $evaluation->setMaxGrade('20.00');
                        $evaluation->setCoefficient($i == 2 ? '2.00' : '1.00'); // Deuxième éval a coef 2
                        $evaluation->setDescription("Évaluation sur le chapitre $i");
                        $evaluation->setIsPublished($periodIndex == 0); // Publier seulement le 1er trimestre
                        $evaluation->setIsActive(true);
                        
                        $manager->persist($evaluation);

                        // Créer les notes pour cette évaluation
                        $this->createGradesForEvaluation($manager, $evaluation);
                    }
                }
            }
        }
    }

    private function createGradesForEvaluation(ObjectManager $manager, Evaluation $evaluation): void
    {
        // Récupérer tous les élèves (on simule avec des users de type élève)
        $students = $manager->getRepository('App\Entity\User')
            ->findByClassroom($evaluation->getClassroom()->getId());

        if (empty($students)) {
            // Si pas d'élèves trouvés, on skip
            return;
        }

        // On prend max 10 élèves pour l'exemple
        $students = array_slice($students, 0, 10);

        foreach ($students as $student) {
            $grade = new Grade();
            $grade->setEvaluation($evaluation);
            $grade->setStudent($student);

            // 80% des élèves ont une note
            if (rand(1, 100) <= 80) {
                // Générer une note aléatoire entre 8 et 18 (sur 20)
                $value = rand(8, 18) + (rand(0, 3) * 0.25); // Ajouter 0, 0.25, 0.50 ou 0.75
                $grade->setValue(number_format($value, 2, '.', ''));
                $grade->setStatus(null);

                // 20% de chance d'avoir un commentaire
                if (rand(1, 100) <= 20) {
                    $comments = [
                        'Bon travail',
                        'Peut mieux faire',
                        'Excellent',
                        'À revoir',
                        'Satisfaisant',
                        'Très bien',
                        'Progrès notables'
                    ];
                    $grade->setComment($comments[array_rand($comments)]);
                }
            } else {
                // 10% absent, 5% dispensé, 5% non rendu
                $rand = rand(1, 100);
                if ($rand <= 50) {
                    $grade->setStatus('absent');
                } elseif ($rand <= 75) {
                    $grade->setStatus('dispense');
                } else {
                    $grade->setStatus('non_rendu');
                }
                $grade->setValue(null);
            }

            $manager->persist($grade);
        }
    }

    public function getDependencies(): array
    {
        return [
            Module1Fixtures::class,
            Module2Fixtures::class,
        ];
    }
}

