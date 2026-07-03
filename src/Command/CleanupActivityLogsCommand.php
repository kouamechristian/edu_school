<?php

namespace App\Command;

use App\Repository\ActivityLogRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Purge les anciennes traces du Mouchard (journal d'activité).
 *
 * Applique une politique de rétention : les lignes antérieures à N jours sont
 * supprimées afin d'éviter que la table activity_log ne grossisse indéfiniment.
 *
 *   php bin/console app:cleanup-logs                (rétention par défaut : 365 jours)
 *   php bin/console app:cleanup-logs --days=90      (conserve les 90 derniers jours)
 *   php bin/console app:cleanup-logs --dry-run      (simulation, ne supprime rien)
 */
#[AsCommand(
    name: 'app:cleanup-logs',
    description: 'Purge les anciennes traces du Mouchard (table activity_log) selon une rétention.',
)]
class CleanupActivityLogsCommand extends Command
{
    private const DEFAULT_DAYS = 365;

    public function __construct(private ActivityLogRepository $activityLogRepository)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'days',
                null,
                InputOption::VALUE_REQUIRED,
                'Nombre de jours de traces à conserver (les plus anciennes sont supprimées).',
                (string) self::DEFAULT_DAYS
            )
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Affiche le nombre de traces qui seraient supprimées sans rien supprimer.'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $days = (int) $input->getOption('days');
        $dryRun = (bool) $input->getOption('dry-run');

        if ($days < 1) {
            $io->error('L\'option --days doit être un entier positif (nombre de jours à conserver).');

            return Command::INVALID;
        }

        $threshold = (new \DateTimeImmutable('today'))->modify(sprintf('-%d days', $days));

        $io->title('Purge du Mouchard (journal d\'activité)');
        $io->text(sprintf('Rétention : %d jour(s) — suppression des traces antérieures au %s.', $days, $threshold->format('d/m/Y')));

        // Compte des traces concernées (sert au dry-run et au message final).
        $count = (int) $this->activityLogRepository->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.createdAt < :before')
            ->setParameter('before', $threshold)
            ->getQuery()
            ->getSingleScalarResult();

        if ($count === 0) {
            $io->success('Aucune trace à purger.');

            return Command::SUCCESS;
        }

        if ($dryRun) {
            $io->note('Mode simulation (--dry-run) : aucune trace ne sera supprimée.');
            $io->success(sprintf('%d trace(s) seraient supprimée(s).', $count));

            return Command::SUCCESS;
        }

        $deleted = $this->activityLogRepository->deleteOlderThan($threshold);

        $io->success(sprintf('%d trace(s) supprimée(s).', $deleted));

        return Command::SUCCESS;
    }
}
