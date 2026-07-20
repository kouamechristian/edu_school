<?php

namespace App\Service\GeniusPay;

use App\Entity\School;
use App\Service\SecretCipher;

/**
 * Seul point d'accès légitime aux secrets marchands d'un établissement.
 *
 * Les colonnes `geniuspay_*` de `school` sont chiffrées : personne d'autre ne
 * doit les lire directement.
 */
class GeniusPayConfigResolver
{
    public function __construct(
        private readonly SecretCipher $cipher,
        private readonly string $geniuspayBaseUrl,
    ) {
    }

    /**
     * Configuration utilisable, ou null si la passerelle est désactivée ou mal
     * configurée pour cet établissement. Un secret illisible (APP_SECRET changé)
     * se comporte comme « non configuré » : on ne tente pas d'appel voué à l'échec.
     */
    public function resolve(?School $school): ?GeniusPayConfig
    {
        if ($school === null || !$school->isGeniuspayEnabled()) {
            return null;
        }

        $key = $this->cipher->decrypt($school->getGeniuspayApiKey());
        $secret = $this->cipher->decrypt($school->getGeniuspayApiSecret());

        if ($key === null || $secret === null) {
            return null;
        }

        return new GeniusPayConfig(
            baseUrl: rtrim($this->geniuspayBaseUrl, '/'),
            apiKey: $key,
            apiSecret: $secret,
            webhookSecret: $this->cipher->decrypt($school->getGeniuspayWebhookSecret()),
        );
    }

    public function isConfigured(?School $school): bool
    {
        return $this->resolve($school) !== null;
    }

    /**
     * Formes masquées pour l'écran d'administration : on ne réaffiche jamais un
     * secret en clair, même à un administrateur.
     *
     * @return array{api_key: string, api_secret: string, webhook_secret: string, environment: string}
     */
    public function maskedSummary(School $school): array
    {
        $key = $this->cipher->decrypt($school->getGeniuspayApiKey());
        $secret = $this->cipher->decrypt($school->getGeniuspayApiSecret());
        $hook = $this->cipher->decrypt($school->getGeniuspayWebhookSecret());

        return [
            'api_key' => $this->cipher->mask($key),
            'api_secret' => $this->cipher->mask($secret),
            'webhook_secret' => $this->cipher->mask($hook),
            'environment' => $key === null ? '—' : (str_contains($key, '_live_') ? 'live' : 'sandbox'),
        ];
    }
}
