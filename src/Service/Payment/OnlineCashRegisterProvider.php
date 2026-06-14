<?php

namespace App\Service\Payment;

use App\Entity\CashRegister;
use App\Entity\School;
use App\Repository\CashRegisterRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Fournit la caisse « en ligne » par défaut d'un établissement.
 *
 * Créée à la demande (au premier paiement mobile/passerelle) puis réutilisée pour
 * tous les paiements suivants du même établissement. Sans caissier humain, validée
 * d'office, marquée isOnline pour la distinguer des caisses physiques.
 */
class OnlineCashRegisterProvider
{
    public function __construct(
        private readonly CashRegisterRepository $cashRegisterRepository,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $paymentLogger,
    ) {
    }

    public function getForSchool(School $school): CashRegister
    {
        $existing = $this->cashRegisterRepository->findOnlineForSchool($school);
        if ($existing) {
            return $existing;
        }

        $cashRegister = new CashRegister();
        $cashRegister->setSchool($school);
        $cashRegister->setIsOnline(true);
        $cashRegister->setCashier(null);
        $cashRegister->setStatus('open');
        $cashRegister->setIsValidated(true);
        $cashRegister->setOpeningBalance('0.00');

        $this->em->persist($cashRegister);
        $this->em->flush();

        $this->paymentLogger->info('Caisse en ligne créée pour l\'établissement', [
            'school' => $school->getId(),
            'cashRegister' => $cashRegister->getId(),
        ]);

        return $cashRegister;
    }
}
