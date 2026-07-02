<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Supprime les anciens reçus de paiement stockés sur disque.
 *
 * Depuis que les reçus sont générés à la volée (plus enregistrés), les fichiers
 * de public/uploads/receipts/ sont devenus orphelins et peuvent être supprimés.
 */
#[AsCommand(
    name: 'app:cleanup-receipts',
    description: 'Supprime les anciens reçus PDF stockés dans public/uploads/receipts/.',
)]
class CleanupReceiptsCommand extends Command
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'dry-run',
            null,
            InputOption::VALUE_NONE,
            'Affiche les fichiers qui seraient supprimés sans rien supprimer.'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');

        $dir = rtrim($this->projectDir, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'receipts';

        $io->title('Nettoyage des anciens reçus PDF');
        if ($dryRun) {
            $io->note('Mode simulation (--dry-run) : aucun fichier ne sera supprimé.');
        }

        if (!is_dir($dir)) {
            $io->success('Aucun dossier de reçus : rien à nettoyer.');

            return Command::SUCCESS;
        }

        $files = glob($dir . DIRECTORY_SEPARATOR . '*.pdf') ?: [];
        if ($files === []) {
            $io->success('Aucun reçu à supprimer.');

            return Command::SUCCESS;
        }

        $deleted = 0;
        $freed = 0;
        foreach ($files as $file) {
            $freed += (int) (@filesize($file) ?: 0);
            if ($dryRun) {
                $io->writeln('  ' . basename($file));
                $deleted++;
                continue;
            }
            if (@unlink($file)) {
                $deleted++;
            } else {
                $io->warning('Impossible de supprimer : ' . basename($file));
            }
        }

        $mb = round($freed / 1048576, 2);
        if ($dryRun) {
            $io->success(sprintf('%d reçu(s) seraient supprimés (~%s Mo).', $deleted, $mb));
        } else {
            $io->success(sprintf('%d reçu(s) supprimé(s) (~%s Mo libérés).', $deleted, $mb));
        }

        return Command::SUCCESS;
    }
}
