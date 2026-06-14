<?php

namespace App\Command;

use App\Entity\PreRegistration;
use App\Entity\Student;
use App\Service\MatriculeGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:reformat-matricules',
    description: 'Reformate les anciens matricules internes au nouveau format AAAA-NNNNN (élèves et préinscriptions).',
)]
class ReformatMatriculesCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MatriculeGenerator $matriculeGenerator
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'dry-run',
            null,
            InputOption::VALUE_NONE,
            'Affiche les matricules qui seraient générés sans rien enregistrer.'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');

        $io->title('Reformatage des matricules internes');
        if ($dryRun) {
            $io->note('Mode simulation (--dry-run) : aucune donnée ne sera modifiée.');
        }

        $entities = [
            Student::class => 'Élèves',
            PreRegistration::class => 'Préinscriptions',
        ];

        $total = 0;

        foreach ($entities as $class => $label) {
            $records = $this->entityManager->getRepository($class)->findBy([], ['id' => 'ASC']);
            $rows = [];

            foreach ($records as $record) {
                $current = $record->getMatriculeInterne();

                // Déjà au bon format : on ne touche pas.
                if ($current && preg_match('/^\d{4}-\d{5}$/', $current)) {
                    continue;
                }

                $year = $this->resolveYear($current, $record);
                $new = $this->matriculeGenerator->generate($this->entityManager, $class, 'matriculeInterne', $year);

                $rows[] = [$record->getId(), $current ?: '(vide)', $new];
                $record->setMatriculeInterne($new);
                $total++;
            }

            if (count($rows) > 0) {
                $io->section(sprintf('%s (%d)', $label, count($rows)));
                $io->table(['ID', 'Ancien', 'Nouveau'], $rows);
            } else {
                $io->writeln(sprintf('<comment>%s</comment> : aucun matricule à reformater.', $label));
            }
        }

        if ($total === 0) {
            $io->success('Aucun matricule à reformater.');
            return Command::SUCCESS;
        }

        if ($dryRun) {
            $io->success(sprintf('%d matricule(s) seraient reformatés (simulation).', $total));
            return Command::SUCCESS;
        }

        $this->entityManager->flush();
        $io->success(sprintf('%d matricule(s) reformatés avec succès.', $total));

        return Command::SUCCESS;
    }

    /**
     * Détermine l'année : préfixe de l'ancien matricule, sinon date de création, sinon année courante.
     */
    private function resolveYear(?string $current, object $record): int
    {
        if ($current && preg_match('/^(\d{4})/', $current, $m)) {
            return (int) $m[1];
        }

        if (method_exists($record, 'getCreatedAt') && $record->getCreatedAt()) {
            return (int) $record->getCreatedAt()->format('Y');
        }

        return (int) date('Y');
    }
}
