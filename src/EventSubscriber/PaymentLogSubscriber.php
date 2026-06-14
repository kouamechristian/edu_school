<?php

namespace App\EventSubscriber;

use App\Event\PaymentFailedEvent;
use App\Event\PaymentSucceededEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Journalise (canal "payment") l'aboutissement des paiements en ligne.
 *
 * Point d'extension naturel pour des effets de bord ultérieurs : envoi d'un reçu
 * par e-mail, notification au parent, etc.
 */
class PaymentLogSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly LoggerInterface $paymentLogger)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PaymentSucceededEvent::class => 'onSucceeded',
            PaymentFailedEvent::class => 'onFailed',
        ];
    }

    public function onSucceeded(PaymentSucceededEvent $event): void
    {
        $p = $event->getPayment();
        $this->paymentLogger->info('Paiement confirmé', [
            'payment' => $p->getId(),
            'number' => $p->getPaymentNumber(),
            'student' => $p->getStudent()?->getId(),
            'amount' => $p->getAmount(),
            'transactionId' => $p->getProviderTransactionId(),
        ]);
    }

    public function onFailed(PaymentFailedEvent $event): void
    {
        $p = $event->getPayment();
        $this->paymentLogger->warning('Paiement échoué/annulé', [
            'payment' => $p->getId(),
            'number' => $p->getPaymentNumber(),
            'providerStatus' => $p->getProviderStatus(),
            'transactionId' => $p->getProviderTransactionId(),
        ]);
    }
}
