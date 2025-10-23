<?php

namespace App\Command;

use App\Entity\DocumentType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:create-document-types',
    description: 'Créer les types de documents par défaut',
)]
class CreateDocumentTypesCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $documentTypes = [
            [
                'name' => 'Certificat de naissance',
                'description' => 'Certificat de naissance de l\'élève',
                'isRequired' => true,
                'allowedMimeTypes' => ['application/pdf', 'image/jpeg', 'image/png'],
                'maxFileSize' => 5242880, // 5MB
            ],
            [
                'name' => 'Bulletin de notes',
                'description' => 'Dernier bulletin de notes de l\'année précédente',
                'isRequired' => true,
                'allowedMimeTypes' => ['application/pdf', 'image/jpeg', 'image/png'],
                'maxFileSize' => 5242880, // 5MB
            ],
            [
                'name' => 'Photo d\'identité',
                'description' => 'Photo d\'identité récente de l\'élève',
                'isRequired' => true,
                'allowedMimeTypes' => ['image/jpeg', 'image/png'],
                'maxFileSize' => 2097152, // 2MB
            ],
            [
                'name' => 'Carnet de vaccination',
                'description' => 'Carnet de vaccination à jour',
                'isRequired' => false,
                'allowedMimeTypes' => ['application/pdf', 'image/jpeg', 'image/png'],
                'maxFileSize' => 5242880, // 5MB
            ],
            [
                'name' => 'Attestation de scolarité',
                'description' => 'Attestation de scolarité de l\'établissement précédent',
                'isRequired' => false,
                'allowedMimeTypes' => ['application/pdf', 'image/jpeg', 'image/png'],
                'maxFileSize' => 5242880, // 5MB
            ],
            [
                'name' => 'Autorisation parentale',
                'description' => 'Autorisation parentale pour l\'inscription',
                'isRequired' => true,
                'allowedMimeTypes' => ['application/pdf', 'image/jpeg', 'image/png'],
                'maxFileSize' => 5242880, // 5MB
            ],
        ];

        foreach ($documentTypes as $data) {
            $documentType = new DocumentType();
            $documentType->setName($data['name']);
            $documentType->setDescription($data['description']);
            $documentType->setIsRequired($data['isRequired']);
            $documentType->setIsActive(true);
            $documentType->setAllowedMimeTypes($data['allowedMimeTypes']);
            $documentType->setMaxFileSize($data['maxFileSize']);

            $this->entityManager->persist($documentType);
        }

        $this->entityManager->flush();

        $io->success('Types de documents créés avec succès !');

        return Command::SUCCESS;
    }
}
