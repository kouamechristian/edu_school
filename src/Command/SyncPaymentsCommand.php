<?php

namespace App\Command;

use App\Repository\PaymentRepository;
use App\Service\Payment\PaymentStatusSynchronizer;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Synchronise auprès de la passerelle les paiements en ligne restés « en attente ».
 *
 * Filet de sécurité lorsque le webhook n'arrive pas (ex. parent qui ne revient pas
 * sur le site). À planifier (cron / Planificateur de tâches Windows), par ex. toutes
 * les 10 minutes :  php bin/console app:payments:sync
 */
#[AsCommand(
    name: 'app:payments:sync',
    description: 'Vérifie le statut des paiements en ligne en attente auprès de la passerelle.',
)]
class SyncPaymentsCommand extends Command
{
    public function __construct(
        private readonly PaymentRepository $paymentRepository,
        private readonly PaymentStatusSynchronizer $synchronizer,
        private readonly LoggerInterface $paymentLogger,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('max-age', null, InputOption::VALUE_REQUIRED, 'Âge max des paiements à vérifier (heures).', '72')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Nombre maximum de paiements à traiter.', '200');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $maxAge = (int) $input->getOption('max-age');
        $limit = (int) $input->getOption('limit');

        $payments = $this->paymentRepository->findPendingOnline($maxAge, $limit);

        if ($payments === []) {
            $io->success('Aucun paiement en ligne en attente à synchroniser.');

            return Command::SUCCESS;
        }

        $io->title(sprintf('Synchronisation de %d paiement(s) en attente', \count($payments)));

        $paid = 0;
        $failed = 0;
        $stillPending = 0;
        $errors = 0;

        foreach ($payments as $payment) {
            try {
                $this->synchronizer->synchronize($payment);
            } catch (\Throwable $e) {
                $errors++;
                $this->paymentLogger->error('Sync planifiée : erreur sur un paiement', [
                    'payment' => $payment->getId(),
                    'error' => $e->getMessage(),
                ]);
                continue;
            }

            match ($payment->getStatus()) {
                'payé' => $paid++,
                'annulé' => $failed++,
                default => $stillPending++,
            };
        }

        $io->table(
            ['Confirmés', 'Échoués/Annulés', 'Toujours en attente', 'Erreurs'],
            [[$paid, $failed, $stillPending, $errors]]
        );

        $this->paymentLogger->info('Sync planifiée terminée', [
            'traités' => \count($payments),
            'payés' => $paid,
            'annulés' => $failed,
            'en_attente' => $stillPending,
            'erreurs' => $errors,
        ]);

        $io->success('Synchronisation terminée.');

        return Command::SUCCESS;
    }
}
