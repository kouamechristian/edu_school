<?php

namespace App\Service\Payment;

use App\Entity\Payment;
use App\Event\PaymentFailedEvent;
use App\Event\PaymentSucceededEvent;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Synchronise l'état d'un paiement en interrogeant directement la passerelle
 * (GET /payments/{id}). Sert d'alternative/complément au webhook : permet de
 * confirmer un paiement au **retour du checkout**, sans URL publique (utile en
 * local, ou en filet de sécurité si le webhook n'arrive pas).
 *
 * L'imputation au solde est idempotente (un paiement déjà « payé » n'est pas recrédité).
 */
class PaymentStatusSynchronizer
{
    private const SUCCESS = ['success', 'succeeded', 'completed', 'paid', 'successful'];
    private const FAILURE = ['failed', 'cancelled', 'canceled', 'declined', 'expired', 'refused'];

    public function __construct(
        private readonly GeniusPayClient $client,
        private readonly EntityManagerInterface $em,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly LoggerInterface $paymentLogger,
        private readonly GeniusPayCredentialsProvider $credentialsProvider,
    ) {
    }

    /**
     * Rafraîchit le statut d'un paiement depuis la passerelle si nécessaire.
     * Sans effet si le paiement est déjà finalisé ou non rattaché à une transaction.
     */
    public function synchronize(Payment $payment): Payment
    {
        if ($payment->getStatus() === 'payé'
            || $payment->getProvider() === null
            || $payment->getProviderTransactionId() === null
            || !$this->client->isConfigured()) {
            return $payment;
        }

        try {
            $response = $this->client->getPaymentStatus(
                $payment->getProviderTransactionId(),
                $this->credentialsProvider->forSchool($payment->getStudent()?->getSchool()),
            );
        } catch (PaymentGatewayException $e) {
            $this->paymentLogger->warning('Synchronisation statut impossible', [
                'payment' => $payment->getId(),
                'error' => $e->getMessage(),
            ]);

            return $payment;
        }

        $data = \is_array($response['data'] ?? null) ? $response['data'] : $response;
        $status = strtolower((string) ($data['status'] ?? $data['state'] ?? ''));
        $amount = isset($data['amount']) ? (float) $data['amount'] : null;

        if ($status === '') {
            return $payment;
        }

        $payment->setProviderStatus($status);

        // Numéro Mobile Money réellement utilisé (objet customer ou champ direct).
        if ($payment->getPayerPhone() === null) {
            $customer = \is_array($data['customer'] ?? null) ? $data['customer'] : [];
            $phone = $customer['phone'] ?? $customer['msisdn'] ?? $data['phone'] ?? $data['msisdn'] ?? null;
            if ($phone) {
                $payment->setPayerPhone((string) $phone);
            }
        }

        if (\in_array($status, self::SUCCESS, true) && $payment->getStatus() !== 'payé') {
            if ($amount !== null && abs($amount - (float) $payment->getAmount()) > 1.0) {
                $this->paymentLogger->error('Synchronisation : montant incohérent', [
                    'payment' => $payment->getId(),
                    'attendu' => $payment->getAmount(),
                    'reçu' => $amount,
                ]);
                $this->em->flush();

                return $payment;
            }

            $payment->setStatus('payé');

            $studentFee = $payment->getStudentFee();
            if ($studentFee) {
                $newPaid = (float) $studentFee->getPaidAmount() + (float) $payment->getAmount();
                $studentFee->setPaidAmount((string) number_format($newPaid, 2, '.', ''));
            }

            $this->em->flush();
            $this->dispatcher->dispatch(new PaymentSucceededEvent($payment));

            return $payment;
        }

        if (\in_array($status, self::FAILURE, true) && $payment->getStatus() === 'en_attente') {
            $payment->setStatus('annulé');
            $this->em->flush();
            $this->dispatcher->dispatch(new PaymentFailedEvent($payment));

            return $payment;
        }

        $this->em->flush();

        return $payment;
    }
}
