<?php

namespace App\Service;

use App\Entity\Absence;
use App\Entity\AccountingEntry;
use App\Entity\BulletinLine;
use App\Entity\Grade;
use App\Entity\OnlinePayment;
use App\Entity\Payment;
use App\Entity\PreRegistration;
use App\Entity\Registration;
use App\Entity\Student;
use App\Entity\StudentDropout;
use App\Entity\StudentFee;
use App\Entity\StudentTransfer;
use App\Repository\AccountingEntryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Suppression définitive EN CASCADE (réservée au super-administrateur) :
 *  - paiement      → ligne(s) du reçu + écriture comptable ; le payé du frais est recalculé ;
 *  - inscription   → ses frais de l'année + leurs paiements ;
 *  - préinscription→ son inscription (et ce qui en dépend) + ses documents ;
 *    l'élève éventuellement créé depuis cette préinscription est conservé ;
 *  - élève         → ses préinscriptions, inscriptions, frais, paiements, notes,
 *    absences, lignes de bulletin, exclusions, transferts, paiements en ligne.
 *
 * Chaque méthode publique s'exécute dans sa propre transaction : tout ou rien.
 */
class CascadeDeletionService
{
    public function __construct(
        private EntityManagerInterface $em,
        private AccountingEntryRepository $accountingEntryRepository,
        #[Autowire('%kernel.project_dir%')] private string $projectDir
    ) {
    }

    /**
     * Supprime des lignes de paiement (ex. toutes les lignes d'un reçu).
     *
     * @param Payment[] $payments
     */
    public function deletePayments(array $payments): void
    {
        $this->em->wrapInTransaction(function () use ($payments) {
            foreach ($payments as $payment) {
                $this->removePayment($payment, true);
            }
            $this->em->flush();
        });
    }

    public function deleteRegistration(Registration $registration): void
    {
        $this->em->wrapInTransaction(function () use ($registration) {
            $this->removeRegistration($registration);
            $this->em->flush();
        });
    }

    public function deletePreRegistration(PreRegistration $preRegistration): void
    {
        $this->em->wrapInTransaction(function () use ($preRegistration) {
            $this->removePreRegistration($preRegistration, false);
            $this->em->flush();
        });
    }

    public function deleteStudent(Student $student): void
    {
        $this->em->wrapInTransaction(function () use ($student) {
            // Préinscriptions (d'origine + réinscriptions) et leurs inscriptions.
            $preRegistrations = $student->getReinscriptions()->toArray();
            if ($student->getPreRegistration() !== null) {
                $preRegistrations[] = $student->getPreRegistration();
            }
            foreach ($preRegistrations as $preRegistration) {
                $this->removePreRegistration($preRegistration, true);
            }

            // Frais restants (sans inscription) et paiements restants (sans frais).
            foreach ($student->getStudentFees()->toArray() as $studentFee) {
                $this->removeStudentFee($studentFee);
            }
            foreach ($this->em->getRepository(Payment::class)->findBy(['student' => $student]) as $payment) {
                $this->removePayment($payment, false);
            }

            // Données scolaires et historiques sans logique métier : suppression directe.
            foreach ([Absence::class, Grade::class, BulletinLine::class, StudentDropout::class, StudentTransfer::class, OnlinePayment::class] as $class) {
                $this->em->createQuery(sprintf('DELETE FROM %s x WHERE x.student = :student', $class))
                    ->setParameter('student', $student)
                    ->execute();
            }

            $this->em->remove($student);
            $this->em->flush();
        });
    }

    private function removePreRegistration(PreRegistration $preRegistration, bool $withStudent): void
    {
        if ($registration = $preRegistration->getRegistration()) {
            $this->removeRegistration($registration);
        }

        foreach ($preRegistration->getDocuments() as $document) {
            $path = $document->getFilePath();
            if ($path) {
                foreach ([$path, $this->projectDir . '/public/' . ltrim($path, '/')] as $candidate) {
                    if (is_file($candidate)) {
                        @unlink($candidate);
                        break;
                    }
                }
            }
            $this->em->remove($document);
        }

        // L'élève créé depuis cette préinscription est conservé (sauf suppression de l'élève).
        if (!$withStudent && ($student = $preRegistration->getStudent()) !== null) {
            $student->setPreRegistration(null);
        }

        $this->em->remove($preRegistration);
    }

    private function removeRegistration(Registration $registration): void
    {
        foreach ($registration->getStudentFees()->toArray() as $studentFee) {
            $this->removeStudentFee($studentFee);
        }
        // Côté inverse du lien 1-1 : sans cela Doctrine retrouverait l'inscription supprimée.
        $registration->getPreRegistration()?->setRegistration(null);
        $this->em->remove($registration);
    }

    private function removeStudentFee(StudentFee $studentFee): void
    {
        foreach ($studentFee->getPayments()->toArray() as $payment) {
            $this->removePayment($payment, false);
        }
        $studentFee->getRegistration()?->removeStudentFee($studentFee);
        $studentFee->getStudent()?->removeStudentFee($studentFee);
        $this->em->remove($studentFee);
    }

    /**
     * @param bool $rollbackFee Retire le montant du payé du frais (inutile si le frais est lui-même supprimé)
     */
    private function removePayment(Payment $payment, bool $rollbackFee): void
    {
        $studentFee = $payment->getStudentFee();
        if ($rollbackFee && $studentFee && in_array($payment->getStatus(), ['payé', 'partiellement_payé'], true)) {
            $paid = max(0.0, (float) $studentFee->getPaidAmount() - (float) $payment->getAmount());
            $studentFee->setPaidAmount(number_format($paid, 2, '.', ''));
        }
        $studentFee?->removePayment($payment);

        if ($payment->getId() !== null
            && ($entry = $this->accountingEntryRepository->findOneBySource(AccountingEntry::SOURCE_PAYMENT, $payment->getId())) !== null) {
            $this->em->remove($entry);
        }

        $this->em->remove($payment);
    }
}
