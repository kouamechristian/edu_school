<?php

namespace App\Service\Payment;

use App\Dto\GeniusPay\WebhookPayload;
use App\Entity\Payment;
use App\Entity\PaymentWebhookEvent;
use App\Event\PaymentFailedEvent;
use App\Event\PaymentSucceededEvent;
use App\Repository\PaymentRepository;
use App\Repository\PaymentWebhookEventRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Traite un webhook GeniusPay : vérification de signature, idempotence (anti-rejeu),
 * puis imputation au solde de l'élève en cas de succès. Émet les événements de paiement.
 */
class WebhookProcessor
{
    public const PROVIDER = 'geniuspay';

    // Codes de résultat (mappés en HTTP par le contrôleur).
    public const RESULT_OK = 'ok';
    public const RESULT_DUPLICATE = 'duplicate';
    public const RESULT_INVALID_SIGNATURE = 'invalid_signature';
    public const RESULT_INVALID_PAYLOAD = 'invalid_payload';
    public const RESULT_UNKNOWN_PAYMENT = 'unknown_payment';
    public const RESULT_AMOUNT_MISMATCH = 'amount_mismatch';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PaymentRepository $paymentRepository,
        private readonly PaymentWebhookEventRepository $eventRepository,
        private readonly GeniusPaySignatureVerifier $signatureVerifier,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly LoggerInterface $paymentLogger,
    ) {
    }

    public function handle(string $rawBody, ?string $signatureHeader, ?string $timestamp = null): string
    {
        $signatureValid = $this->signatureVerifier->isValid($rawBody, $signatureHeader, $timestamp);

        $payload = $this->parse($rawBody);
        if (!$payload) {
            $this->paymentLogger->error('Webhook GeniusPay : corps illisible');

            return self::RESULT_INVALID_PAYLOAD;
        }

        // Idempotence : un même eventId n'est traité qu'une fois.
        $existing = $this->eventRepository->findOneByProviderEvent(self::PROVIDER, $payload->eventId);
        if ($existing && $existing->getProcessedAt() !== null) {
            $this->paymentLogger->info('Webhook GeniusPay ignoré (déjà traité)', ['eventId' => $payload->eventId]);

            return self::RESULT_DUPLICATE;
        }

        $event = $existing ?? (new PaymentWebhookEvent())
            ->setProvider(self::PROVIDER)
            ->setEventId($payload->eventId);
        $event->setType($payload->type);
        $event->setPayload($rawBody);
        $event->setSignatureValid($signatureValid);

        try {
            $this->em->persist($event);
            $this->em->flush();
        } catch (UniqueConstraintViolationException) {
            // Deux livraisons simultanées du même événement → on considère comme doublon.
            return self::RESULT_DUPLICATE;
        }

        if (!$signatureValid) {
            $this->paymentLogger->error('Webhook GeniusPay : signature invalide', ['eventId' => $payload->eventId]);

            return self::RESULT_INVALID_SIGNATURE;
        }

        $payment = $this->locatePayment($payload);
        if (!$payment) {
            $this->paymentLogger->warning('Webhook GeniusPay : paiement introuvable', [
                'transactionId' => $payload->transactionId,
                'reference' => $payload->reference,
            ]);
            $event->markProcessed();
            $this->em->flush();

            return self::RESULT_UNKNOWN_PAYMENT;
        }

        $result = $this->applyToPayment($payment, $payload);

        $event->markProcessed();
        $this->em->flush();

        return $result;
    }

    private function applyToPayment(Payment $payment, WebhookPayload $payload): string
    {
        if ($payload->status !== null) {
            $payment->setProviderStatus($payload->status);
        }

        // Numéro Mobile Money réellement utilisé pour la transaction.
        if ($payload->payerPhone !== null && $payment->getPayerPhone() === null) {
            $payment->setPayerPhone($payload->payerPhone);
        }

        if ($payload->isSuccess()) {
            // Déjà encaissé : rien à faire (idempotent au niveau métier).
            if ($payment->getStatus() === 'payé') {
                return self::RESULT_OK;
            }

            // Garde-fou : le montant du webhook doit correspondre au montant attendu.
            if ($payload->amount !== null && abs($payload->amount - (float) $payment->getAmount()) > 1.0) {
                $this->paymentLogger->error('Webhook GeniusPay : montant incohérent', [
                    'payment' => $payment->getId(),
                    'attendu' => $payment->getAmount(),
                    'reçu' => $payload->amount,
                ]);

                return self::RESULT_AMOUNT_MISMATCH;
            }

            $payment->setStatus('payé');

            $studentFee = $payment->getStudentFee();
            if ($studentFee) {
                $newPaid = (float) $studentFee->getPaidAmount() + (float) $payment->getAmount();
                $studentFee->setPaidAmount((string) number_format($newPaid, 2, '.', ''));
            }

            $this->dispatcher->dispatch(new PaymentSucceededEvent($payment));

            return self::RESULT_OK;
        }

        if ($payload->isFailure()) {
            if ($payment->getStatus() === 'en_attente') {
                $payment->setStatus('annulé');
            }
            $this->dispatcher->dispatch(new PaymentFailedEvent($payment));
        }

        return self::RESULT_OK;
    }

    private function locatePayment(WebhookPayload $payload): ?Payment
    {
        // On corrèle par l'identifiant stocké (référence GeniusPay) en testant aussi
        // bien l'id que la référence éventuellement présents dans le webhook.
        foreach ([$payload->transactionId, $payload->reference] as $candidate) {
            if ($candidate !== null) {
                $payment = $this->paymentRepository->findOneByProviderTransactionId(self::PROVIDER, $candidate);
                if ($payment) {
                    return $payment;
                }
            }
        }

        // Repli : notre propre numéro de paiement (external_reference).
        if ($payload->reference !== null) {
            return $this->paymentRepository->findOneBy(['paymentNumber' => $payload->reference]);
        }

        return null;
    }

    private function parse(string $rawBody): ?WebhookPayload
    {
        $decoded = json_decode($rawBody, true);
        if (!\is_array($decoded)) {
            return null;
        }

        $data = \is_array($decoded['data'] ?? null) ? $decoded['data'] : $decoded;

        $eventId = $this->pick($decoded, ['id', 'event_id', 'eventId'])
            ?? $this->pick($data, ['event_id', 'eventId'])
            ?? sha1($rawBody);

        $amountRaw = $this->pick($data, ['amount']);

        // Téléphone Mobile Money réellement utilisé (objet customer ou champ direct).
        $customer = \is_array($data['customer'] ?? null) ? $data['customer'] : [];
        $payerPhone = $this->pick($customer, ['phone', 'msisdn', 'phone_number'])
            ?? $this->pick($data, ['phone', 'msisdn', 'payer_phone', 'customer_phone']);

        return new WebhookPayload(
            eventId: (string) $eventId,
            type: (string) ($this->pick($decoded, ['event', 'type']) ?? $this->pick($data, ['event', 'type']) ?? ''),
            transactionId: $this->pick($data, ['id', 'transaction_id', 'transactionId', 'payment_id']),
            reference: $this->pick($data, ['reference', 'merchant_reference']),
            status: $this->pick($data, ['status', 'state']),
            amount: $amountRaw !== null ? (float) $amountRaw : null,
            payerPhone: $payerPhone,
            raw: $decoded,
        );
    }

    /**
     * @param array<string, mixed> $data
     * @param string[]             $keys
     */
    private function pick(array $data, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (isset($data[$key]) && $data[$key] !== '') {
                return (string) $data[$key];
            }
        }

        return null;
    }
}
