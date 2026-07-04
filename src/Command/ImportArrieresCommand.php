<?php

namespace App\Command;

use App\Service\ArriereImporter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Importe les arriérés (impayés d'une année antérieure) depuis un fichier .xlsx.
 *
 * La logique est portée par {@see ArriereImporter} (partagée avec l'import web).
 * Le fichier doit contenir les colonnes « matricule » et « montant_arriere ».
 *
 *   php bin/console app:import-arrieres var/arrieres.xlsx           (aperçu)
 *   php bin/console app:import-arrieres var/arrieres.xlsx --force    (applique)
 */
#[AsCommand(
    name: 'app:import-arrieres',
    description: 'Importe les arriérés antérieurs depuis un fichier .xlsx (colonnes : matricule, montant_arriere).',
)]
class ImportArrieresCommand extends Command
{
    public function __construct(
        private readonly ArriereImporter $arriereImporter,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('file', InputArgument::REQUIRED, 'Chemin du fichier .xlsx à importer.')
            ->addOption('annee', null, InputOption::VALUE_REQUIRED, 'Année scolaire d\'inscription cible.', '2025-2026')
            ->addOption('annee-origine', null, InputOption::VALUE_REQUIRED, 'Année d\'origine des arriérés (libellé + montant reporté).', '2024-2025')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Applique réellement l\'import (sinon simple aperçu).');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $file = (string) $input->getArgument('file');
        $anneeCible = (string) $input->getOption('annee');
        $anneeOrigine = (string) $input->getOption('annee-origine');
        $apply = (bool) $input->getOption('force');

        $io->title(sprintf(
            'Import des arriérés %s → inscriptions %s%s',
            $anneeOrigine,
            $anneeCible,
            $apply ? '' : ' (APERÇU)'
        ));

        $result = $this->arriereImporter->import($file, $anneeCible, $anneeOrigine, $apply);

        if ($result['error'] !== null) {
            $io->error($result['error']);

            return Command::FAILURE;
        }

        $table = array_map(
            static fn (array $r): array => [$r['line'], $r['matricule'], $r['montant'], $r['status']],
            $result['rows']
        );
        if ($table !== []) {
            $io->table(['Ligne', 'Matricule', 'Montant', 'Résultat'], $table);
        }

        if ($apply) {
            $io->success(sprintf('Import terminé : %d importés, %d ignorés.', $result['imported'], $result['skipped']));
        } else {
            $io->note(sprintf(
                'Aperçu uniquement : %d seraient importés, %d ignorés. Relancez avec --force pour appliquer.',
                $result['imported'],
                $result['skipped']
            ));
        }

        return Command::SUCCESS;
    }
}
