<?php

namespace App\Service\Payment;

use Psr\Log\LoggerInterface;

/**
 * Vérifie l'authenticité d'un webhook GeniusPay.
 *
 * Formule officielle (guide GeniusPay) :
 *   $data = $timestamp . '.' . file_get_contents('php://input');
 *   $expected = hash_hmac('sha256', $data, $secret);   // en-tête X-Webhook-Signature (hex)
 *
 * Le secret pouvant être propre à chaque établissement, on accepte si la signature
 * correspond à l'un des secrets configurés (établissements actifs + global). On teste
 * aussi la variante json_encode($request->all()) (corps ré-encodé) par tolérance.
 * Comparaison à temps constant. Si aucun secret n'est configuré, on rejette.
 */
class GeniusPaySignatureVerifier
{
    public function __construct(
        private readonly GeniusPayCredentialsProvider $credentialsProvider,
        private readonly bool $verifyEnabled,
        private readonly LoggerInterface $paymentLogger,
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->credentialsProvider->webhookSecrets() !== [];
    }

    public function isValid(string $rawBody, ?string $signatureHeader, ?string $timestamp): bool
    {
        // Échappatoire de DÉVELOPPEMENT uniquement : ignore la vérification de signature.
        // Ne JAMAIS activer en production (l'endpoint webhook étant public).
        if (!$this->verifyEnabled) {
            $this->paymentLogger->warning('Vérification de signature webhook DÉSACTIVÉE (mode dev).');

            return true;
        }

        if ($signatureHeader === null || $signatureHeader === '' || $timestamp === null || $timestamp === '') {
            return false;
        }

        $signature = trim($signatureHeader);

        // Variantes de corps signé : brut (guide) puis ré-encodé (doc API).
        $candidates = [$timestamp . '.' . $rawBody];
        $decoded = json_decode($rawBody, true);
        if (\is_array($decoded)) {
            $candidates[] = $timestamp . '.' . json_encode($decoded);
        }

        foreach ($this->credentialsProvider->webhookSecrets() as $secret) {
            foreach ($candidates as $data) {
                if (hash_equals(hash_hmac('sha256', $data, $secret), $signature)) {
                    return true;
                }
            }
        }

        return false;
    }
}
