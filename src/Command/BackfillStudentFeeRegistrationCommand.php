<?php

namespace App\Command;

use App\Entity\StudentFee;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Rattache les frais d'élève « orphelins » (student_fee.registration_id IS NULL)
 * à l'inscription courante de l'élève.
 *
 * Corrige rétroactivement les frais affectés à la main (ex. « article ») saisis
 * avant le correctif : sans inscription rattachée, ils n'étaient pas comptés dans
 * Registration::getTotalTuition() (le « montant total » de la page de paiement).
 *
 *   php bin/console app:student-fees:backfill-registration            (aperçu)
 *   php bin/console app:student-fees:backfill-registration --force    (applique)
 */
#[AsCommand(
    name: 'app:student-fees:backfill-registration',
    description: 'Rattache les frais d\'élève sans inscription à l\'inscription courante de l\'élève.',
)]
class BackfillStudentFeeRegistrationCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('force', null, InputOption::VALUE_NONE, 'Applique réellement les modifications (sinon simple aperçu).');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $force = (bool) $input->getOption('force');

        /** @var StudentFee[] $orphans */
        $orphans = $this->entityManager->getRepository(StudentFee::class)
            ->createQueryBuilder('sf')
            ->andWhere('sf.registration IS NULL')
            ->getQuery()
            ->getResult();

        if ($orphans === []) {
            $io->success('Aucun frais orphelin : tout est déjà rattaché à une inscription.');

            return Command::SUCCESS;
        }

        $io->title(sprintf('%d frais d\'élève sans inscription', \count($orphans)));

        $linked = 0;
        $skipped = 0;
        $rows = [];

        foreach ($orphans as $studentFee) {
            $student = $studentFee->getStudent();
            $registration = $student?->getLatestRegistration();

            if ($registration === null) {
                $skipped++;
                $rows[] = [
                    $student?->getFullName() ?? '—',
                    $studentFee->getFee()?->getName() ?? '—',
                    number_format((float) $studentFee->getAmount(), 0, ',', ' '),
                    'IGNORÉ (aucune inscription)',
                ];
                continue;
            }

            if ($force) {
                $studentFee->setRegistration($registration);
                $registration->addStudentFee($studentFee);
            }

            $linked++;
            $rows[] = [
                $student->getFullName(),
                $studentFee->getFee()?->getName() ?? '—',
                number_format((float) $studentFee->getAmount(), 0, ',', ' '),
                $registration->getSchoolYear()?->getName() ?? '—',
            ];
        }

        $io->table(['Élève', 'Frais', 'Montant', 'Inscription'], $rows);

        if ($force) {
            $this->entityManager->flush();
            $io->success(sprintf('%d frais rattachés. %d ignorés (élève sans inscription).', $linked, $skipped));
        } else {
            $io->note(sprintf(
                'Aperçu uniquement : %d frais seraient rattachés, %d ignorés. Relancez avec --force pour appliquer.',
                $linked,
                $skipped
            ));
        }

        return Command::SUCCESS;
    }
}
