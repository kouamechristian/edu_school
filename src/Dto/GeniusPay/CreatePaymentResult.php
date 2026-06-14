<?php

namespace App\Dto\GeniusPay;

/**
 * Résultat normalisé d'une création de paiement GeniusPay.
 *
 * Les noms de champs renvoyés par GeniusPay sont absorbés ici (voir GeniusPayClient),
 * de sorte que le reste de l'application ne dépende pas du format brut de l'API.
 */
final class CreatePaymentResult
{
    public function __construct(
        public readonly string $transactionId,
        public readonly ?string $checkoutUrl,
        public readonly ?string $status,
        public readonly ?string $reference = null,
        /** @var array<string, mixed> */
        public readonly array $raw = [],
    ) {
    }
}
