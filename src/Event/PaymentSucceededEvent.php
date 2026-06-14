<?php

namespace App\Event;

use App\Entity\Payment;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Émis lorsqu'un paiement en ligne est confirmé (webhook succès, solde imputé).
 */
class PaymentSucceededEvent extends Event
{
    public function __construct(private readonly Payment $payment)
    {
    }

    public function getPayment(): Payment
    {
        return $this->payment;
    }
}
