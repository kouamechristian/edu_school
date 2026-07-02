<?php

namespace App\Command;

use App\Entity\CashDeposit;
use App\Entity\Depense;
use App\Entity\Payment;
use App\Entity\School;
use App\Service\AccountingService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Initialise le plan comptable par défaut de chaque établissement et reconstruit
 * le journal comptable à partir des mouvements existants (paiements encaissés,
 * dépenses, versements). Idempotent : peut être relancée sans créer de doublon.
 */
#[AsCommand(
    name: 'app:accounting:sync',
    description: 'Amorce le plan comptable et reconstruit le journal depuis les paiements, dépenses et versements existants.',
)]
class SyncAccountingCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private AccountingService $accountingService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Synchronisation de la comptabilité');

        // 1. Plan comptable par défaut pour chaque établissement.
        $accountsCreated = 0;
        foreach ($this->em->getRepository(School::class)->findAll() as $school) {
            $accountsCreated += $this->accountingService->ensureDefaultAccounts($school);
        }
        $this->em->flush();
        $io->writeln(sprintf('Plan comptable : <info>%d</info> compte(s) créé(s).', $accountsCreated));

        // 2. Journal : paiements encaissés.
        $count = 0;
        foreach ($this->em->getRepository(Payment::class)->findAll() as $payment) {
            $this->accountingService->syncPayment($payment, $payment->getRecordedBy());
            $count++;
        }
        $this->em->flush();
        $io->writeln(sprintf('Paiements traités : <info>%d</info>.', $count));

        // 3. Journal : dépenses.
        $count = 0;
        foreach ($this->em->getRepository(Depense::class)->findAll() as $depense) {
            $this->accountingService->syncDepense($depense, $depense->getRecordedBy());
            $count++;
        }
        $this->em->flush();
        $io->writeln(sprintf('Dépenses traitées : <info>%d</info>.', $count));

        // 4. Journal : versements.
        $count = 0;
        foreach ($this->em->getRepository(CashDeposit::class)->findAll() as $deposit) {
            $this->accountingService->syncDeposit($deposit, $deposit->getRecordedBy());
            $count++;
        }
        $this->em->flush();
        $io->writeln(sprintf('Versements traités : <info>%d</info>.', $count));

        $io->success('Comptabilité synchronisée avec succès.');

        return Command::SUCCESS;
    }
}
