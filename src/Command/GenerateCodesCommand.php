<?php

namespace App\Command;

use App\Service\CodeGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:generate-codes',
    description: 'Régénère les codes vides des données existantes (établissements, matières, salles, etc.)',
)]
class GenerateCodesCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CodeGenerator $codeGenerator
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'dry-run',
            null,
            InputOption::VALUE_NONE,
            'Affiche les codes qui seraient générés sans rien enregistrer.'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');

        $io->title('Régénération des codes vides');
        if ($dryRun) {
            $io->note('Mode simulation (--dry-run) : aucune donnée ne sera modifiée.');
        }

        $totalUpdated = 0;

        foreach (CodeGenerator::PREFIXES as $class => $prefix) {
            $shortName = (new \ReflectionClass($class))->getShortName();

            // Entités dont le code est vide (NULL ou chaîne vide).
            $entities = $this->entityManager->createQueryBuilder()
                ->select('e')
                ->from($class, 'e')
                ->where('e.code IS NULL')
                ->orWhere("e.code = ''")
                ->getQuery()
                ->getResult();

            if (count($entities) === 0) {
                $io->writeln(sprintf('<comment>%s</comment> : aucun code vide.', $shortName));
                continue;
            }

            $rows = [];
            foreach ($entities as $entity) {
                $code = $this->codeGenerator->generate($this->entityManager, $class, $prefix);
                $entity->setCode($code);

                $label = method_exists($entity, 'getName') ? (string) $entity->getName() : ('#' . $entity->getId());
                $rows[] = [$entity->getId(), $label, $code];
                $totalUpdated++;
            }

            $io->section(sprintf('%s (%d)', $shortName, count($rows)));
            $io->table(['ID', 'Libellé', 'Code généré'], $rows);
        }

        if ($totalUpdated === 0) {
            $io->success('Aucun code vide à régénérer.');

            return Command::SUCCESS;
        }

        if ($dryRun) {
            $io->success(sprintf('%d code(s) seraient générés (simulation).', $totalUpdated));

            return Command::SUCCESS;
        }

        $this->entityManager->flush();
        $io->success(sprintf('%d code(s) régénéré(s) avec succès.', $totalUpdated));

        return Command::SUCCESS;
    }
}
