<?php

namespace App\Command;

use App\Entity\School;
use App\Entity\User;
use App\Repository\SchoolRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:test-relation',
    description: 'Tester la relation User-School',
)]
class TestRelationCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserRepository $userRepository,
        private SchoolRepository $schoolRepository
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Récupérer un user et une school
        $user = $this->userRepository->find(1);
        $school = $this->schoolRepository->find(1);

        if (!$user || !$school) {
            $io->error('User ou School introuvable');
            return Command::FAILURE;
        }

        $io->info("User: {$user->getUsername()}");
        $io->info("School: {$school->getName()}");
        $io->info("Schools count avant: " . $user->getSchools()->count());

        // Ajouter la relation
        $user->addSchool($school);
        
        $io->info("Schools count après add: " . $user->getSchools()->count());

        // Persister explicitement
        $this->entityManager->persist($user);
        
        // Flush
        $this->entityManager->flush();

        $io->info("Flush effectué");

        // Vérifier en base
        $result = $this->entityManager->getConnection()->executeQuery(
            'SELECT * FROM user_school WHERE user_id = :user_id',
            ['user_id' => $user->getId()]
        )->fetchAllAssociative();

        $io->info("Relations en base: " . count($result));

        if (empty($result)) {
            $io->error('Aucune relation créée en base !');
            return Command::FAILURE;
        }

        $io->success('Relation créée avec succès !');
        return Command::SUCCESS;
    }
}

