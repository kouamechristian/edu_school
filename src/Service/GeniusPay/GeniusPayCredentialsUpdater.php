<?php

namespace App\Service\GeniusPay;

use App\Entity\School;
use App\Service\SecretCipher;

/**
 * Applique les identifiants marchands saisis à un établissement, en les chiffrant.
 *
 * Unique implémentation partagée par les trois points de saisie (création,
 * modification, écran dédié) : dupliquer le chiffrement, ce serait s'offrir
 * trois occasions d'oublier de chiffrer une clé.
 *
 * Convention constante : un champ laissé vide ne touche pas à la valeur
 * existante. C'est ce qui permet de ne remplacer qu'une seule clé, et de ne
 * jamais avoir à réafficher un secret pour le conserver.
 */
class GeniusPayCredentialsUpdater
{
    public function __construct(private readonly SecretCipher $cipher)
    {
    }

    /**
     * @return string|null message d'erreur à présenter, ou null si tout est appliqué
     */
    public function apply(
        School $school,
        ?string $apiKey,
        ?string $apiSecret,
        ?string $webhookSecret,
        bool $enabled,
    ): ?string {
        $apiKey = trim((string) $apiKey);
        $apiSecret = trim((string) $apiSecret);
        $webhookSecret = trim((string) $webhookSecret);

        if ($apiKey !== '') {
            $school->setGeniuspayApiKey($this->cipher->encrypt($apiKey));
        }
        if ($apiSecret !== '') {
            $school->setGeniuspayApiSecret($this->cipher->encrypt($apiSecret));
        }
        if ($webhookSecret !== '') {
            $school->setGeniuspayWebhookSecret($this->cipher->encrypt($webhookSecret));
        }

        if (!$enabled) {
            $school->setGeniuspayEnabled(false);

            return null;
        }

        // Activer sans identifiants exploitables afficherait aux parents un
        // bouton de paiement voué à l'échec : on refuse l'activation.
        if ($school->getGeniuspayApiKey() === null || $school->getGeniuspayApiSecret() === null) {
            $school->setGeniuspayEnabled(false);

            return 'Le paiement en ligne n’a pas été activé : renseignez la clé API et la clé secrète.';
        }

        $school->setGeniuspayEnabled(true);

        // Sans secret de webhook, un paiement partirait mais ne serait jamais
        // crédité : on active quand même (la configuration peut se faire en deux
        // temps) mais on le dit clairement.
        if ($school->getGeniuspayWebhookSecret() === null) {
            return 'Paiement en ligne activé, mais le secret de webhook manque : '
                . 'les paiements ne pourront pas être confirmés automatiquement.';
        }

        return null;
    }
}
