<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-admin',
    description: 'Créer un utilisateur administrateur',
)]
class CreateAdminCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('🎓 Création d\'un Administrateur EDU-SCHOOL');

        $helper = $this->getHelper('question');

        // Username
        $usernameQuestion = new Question('Nom d\'utilisateur: ');
        $usernameQuestion->setValidator(function ($answer) {
            if (empty($answer)) {
                throw new \RuntimeException('Le nom d\'utilisateur ne peut pas être vide');
            }
            if (strlen($answer) < 3) {
                throw new \RuntimeException('Le nom d\'utilisateur doit contenir au moins 3 caractères');
            }
            return $answer;
        });
        $username = $helper->ask($input, $output, $usernameQuestion);

        // Email
        $emailQuestion = new Question('Email: ');
        $emailQuestion->setValidator(function ($answer) {
            if (empty($answer)) {
                throw new \RuntimeException('L\'email ne peut pas être vide');
            }
            if (!filter_var($answer, FILTER_VALIDATE_EMAIL)) {
                throw new \RuntimeException('L\'email n\'est pas valide');
            }
            return $answer;
        });
        $email = $helper->ask($input, $output, $emailQuestion);

        // Password
        $passwordQuestion = new Question('Mot de passe: ');
        $passwordQuestion->setHidden(true);
        $passwordQuestion->setValidator(function ($answer) {
            if (empty($answer)) {
                throw new \RuntimeException('Le mot de passe ne peut pas être vide');
            }
            if (strlen($answer) < 6) {
                throw new \RuntimeException('Le mot de passe doit contenir au moins 6 caractères');
            }
            return $answer;
        });
        $password = $helper->ask($input, $output, $passwordQuestion);

        // Confirm password
        $confirmQuestion = new Question('Confirmer le mot de passe: ');
        $confirmQuestion->setHidden(true);
        $confirmQuestion->setValidator(function ($answer) use ($password) {
            if ($answer !== $password) {
                throw new \RuntimeException('Les mots de passe ne correspondent pas');
            }
            return $answer;
        });
        $helper->ask($input, $output, $confirmQuestion);

        // Role
        $roleQuestion = new ChoiceQuestion(
            'Rôle à attribuer',
            ['ROLE_ADMIN', 'ROLE_SUPER_ADMIN'],
            0
        );
        $role = $helper->ask($input, $output, $roleQuestion);

        // First Name
        $firstNameQuestion = new Question('Prénom (optionnel): ');
        $firstName = $helper->ask($input, $output, $firstNameQuestion);

        // Last Name
        $lastNameQuestion = new Question('Nom (optionnel): ');
        $lastName = $helper->ask($input, $output, $lastNameQuestion);

        // Créer l'utilisateur
        $user = new User();
        $user->setUsername($username)
            ->setEmail($email)
            ->setPassword($this->passwordHasher->hashPassword($user, $password))
            ->setRoles([$role])
            ->setUserType('admin')
            ->setIsActive(true);

        if ($firstName) {
            $user->setFirstName($firstName);
        }
        if ($lastName) {
            $user->setLastName($lastName);
        }

        try {
            $this->entityManager->persist($user);
            $this->entityManager->flush();

            $io->success([
                'Administrateur créé avec succès !',
                '',
                "Username: {$username}",
                "Email: {$email}",
                "Rôle: {$role}",
                '',
                'Vous pouvez maintenant vous connecter à l\'application.',
            ]);

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Erreur lors de la création de l\'administrateur: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}

