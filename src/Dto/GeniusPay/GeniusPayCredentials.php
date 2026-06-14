<?php

namespace App\Dto\GeniusPay;

/**
 * Identifiants de la passerelle GeniusPay utilisés pour un appel donné
 * (résolus par établissement, avec repli sur la configuration globale).
 */
final class GeniusPayCredentials
{
    public function __construct(
        public readonly string $baseUrl,
        public readonly string $apiKey,
        public readonly string $apiSecret,
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== '' && $this->apiSecret !== '';
    }
}
