<?php

namespace App\Service\GeniusPay;

/**
 * Identifiants marchands GeniusPay d'un établissement, déchiffrés et prêts à l'emploi.
 */
final class GeniusPayConfig
{
    public function __construct(
        public readonly string $baseUrl,
        public readonly string $apiKey,
        public readonly string $apiSecret,
        public readonly ?string $webhookSecret,
    ) {
    }

    /**
     * L'environnement se déduit du préfixe de la clé (`pk_live_…` / `pk_sandbox_…`),
     * plutôt que d'un réglage séparé qui pourrait le contredire.
     */
    public function environment(): string
    {
        return str_contains($this->apiKey, '_live_') ? 'live' : 'sandbox';
    }

    public function isLive(): bool
    {
        return $this->environment() === 'live';
    }
}
