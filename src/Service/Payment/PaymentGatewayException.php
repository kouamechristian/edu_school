<?php

namespace App\Service\Payment;

/**
 * Erreur liée à la passerelle de paiement (transport, réponse invalide, refus…).
 */
class PaymentGatewayException extends \RuntimeException
{
}
