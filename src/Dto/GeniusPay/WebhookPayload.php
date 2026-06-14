<?php

namespace App\Dto\GeniusPay;

/**
 * Représentation normalisée d'un webhook GeniusPay (après décodage du corps JSON).
 */
final class WebhookPayload
{
    public function __construct(
        public readonly string $eventId,
        public readonly string $type,
        public readonly ?string $transactionId,
        public readonly ?string $reference,
        public readonly ?string $status,
        public readonly ?float $amount,
        public readonly ?string $payerPhone = null,
        /** @var array<string, mixed> */
        public readonly array $raw = [],
    ) {
    }

    public function isSuccess(): bool
    {
        return \in_array($this->type, ['payment.success', 'payment.succeeded', 'payment.completed'], true)
            || \in_array(strtolower((string) $this->status), ['success', 'succeeded', 'completed', 'paid'], true);
    }

    public function isFailure(): bool
    {
        return \in_array($this->type, ['payment.failed', 'payment.cancelled', 'payment.canceled'], true)
            || \in_array(strtolower((string) $this->status), ['failed', 'cancelled', 'canceled', 'declined'], true);
    }
}
