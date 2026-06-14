<?php

namespace App\Service\Payment;

use App\Dto\GeniusPay\GeniusPayCredentials;
use App\Entity\School;
use App\Repository\MobileMoneyConfigRepository;

/**
 * Résout les identifiants GeniusPay à utiliser : ceux de l'établissement s'il
 * dispose d'une configuration active et renseignée, sinon les valeurs globales (.env).
 */
class GeniusPayCredentialsProvider
{
    public function __construct(
        private readonly MobileMoneyConfigRepository $configRepository,
        private readonly string $defaultBaseUrl,
        private readonly string $defaultApiKey,
        private readonly string $defaultApiSecret,
        private readonly string $defaultWebhookSecret,
    ) {
    }

    public function forSchool(?School $school): GeniusPayCredentials
    {
        if ($school) {
            $config = $this->configRepository->findOneBySchool($school);
            if ($config && $config->isActive() && $config->isConfigured()) {
                return new GeniusPayCredentials(
                    baseUrl: $config->getBaseUrl() ?: $this->defaultBaseUrl,
                    apiKey: (string) $config->getApiKey(),
                    apiSecret: (string) $config->getApiSecret(),
                );
            }
        }

        return new GeniusPayCredentials($this->defaultBaseUrl, $this->defaultApiKey, $this->defaultApiSecret);
    }

    /**
     * Tous les secrets de webhook candidats (établissements actifs + global),
     * pour la vérification de signature sans connaître l'école à l'avance.
     *
     * @return string[]
     */
    public function webhookSecrets(): array
    {
        $secrets = [];
        if ($this->defaultWebhookSecret !== '') {
            $secrets[] = $this->defaultWebhookSecret;
        }

        foreach ($this->configRepository->findActive() as $config) {
            $secret = (string) $config->getWebhookSecret();
            if ($secret !== '') {
                $secrets[] = $secret;
            }
        }

        return array_values(array_unique($secrets));
    }
}
