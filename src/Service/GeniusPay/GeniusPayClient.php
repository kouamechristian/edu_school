<?php

namespace App\Service\GeniusPay;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Appels HTTP à l'API marchand GeniusPay.
 *
 * Volontairement bête : cette classe parle HTTP et rien d'autre. Toute la
 * logique métier (imputation, idempotence, comptabilité) vit dans GeniusPayService.
 */
class GeniusPayClient
{
    /** Au-delà, on considère la passerelle injoignable plutôt que faire attendre le parent. */
    private const TIMEOUT_SECONDS = 20;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Crée un paiement. Sans `payment_method`, GeniusPay renvoie une URL vers sa
     * page de checkout où le parent choisit Wave / Orange / MTN / carte.
     *
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed> Le contenu de `data`
     *
     * @throws GeniusPayException
     */
    public function createPayment(GeniusPayConfig $config, array $payload): array
    {
        return $this->request($config, 'POST', '/payments', $payload);
    }

    /**
     * Relit une transaction chez GeniusPay. C'est la source de vérité utilisée
     * au retour du parent, pour ne pas dépendre uniquement du webhook.
     *
     * @return array<string, mixed>
     *
     * @throws GeniusPayException
     */
    public function getPayment(GeniusPayConfig $config, string $reference): array
    {
        return $this->request($config, 'GET', '/payments/' . rawurlencode($reference));
    }

    /**
     * @param array<string, mixed>|null $payload
     *
     * @return array<string, mixed>
     *
     * @throws GeniusPayException
     */
    private function request(GeniusPayConfig $config, string $method, string $path, ?array $payload = null): array
    {
        $options = [
            'headers' => [
                'X-API-Key' => $config->apiKey,
                'X-API-Secret' => $config->apiSecret,
                'Accept' => 'application/json',
            ],
            'timeout' => self::TIMEOUT_SECONDS,
        ];

        if ($payload !== null) {
            $options['json'] = $payload;
        }

        try {
            $response = $this->httpClient->request($method, $config->baseUrl . $path, $options);
            $status = $response->getStatusCode();
            // false : on veut lire le corps même sur 4xx/5xx, il porte le code d'erreur.
            $body = $response->getContent(false);
        } catch (HttpExceptionInterface $e) {
            // Ne jamais journaliser $options : il contient les secrets marchands.
            $this->logger->error('GeniusPay injoignable', [
                'method' => $method,
                'path' => $path,
                'error' => $e->getMessage(),
            ]);

            throw new GeniusPayException('La passerelle de paiement est injoignable. Réessayez dans un instant.', 0, $e);
        }

        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            $this->logger->error('GeniusPay : réponse illisible', ['path' => $path, 'status' => $status, 'body' => mb_substr($body, 0, 500)]);

            throw new GeniusPayException('Réponse illisible de la passerelle de paiement.');
        }

        if ($status >= 400 || ($decoded['success'] ?? false) !== true) {
            // `code`, `error` et `message` peuvent être des tableaux (erreurs de
            // validation détaillées par champ) : on les aplatit, sans quoi la
            // concaténation masquerait le vrai motif du refus.
            $code = $this->flatten($decoded['code'] ?? $decoded['error'] ?? 'UNKNOWN');
            $message = $this->flatten($decoded['message'] ?? $decoded['errors'] ?? 'Erreur de la passerelle de paiement.');

            $this->logger->error('GeniusPay : erreur API', [
                'path' => $path,
                'status' => $status,
                'code' => $code,
                'message' => $message,
            ]);

            throw new GeniusPayException(sprintf('%s (%s)', $message, $code), $status);
        }

        $data = $decoded['data'] ?? null;

        if (!is_array($data)) {
            throw new GeniusPayException('Réponse inattendue de la passerelle : « data » manquant.');
        }

        return $data;
    }

    /**
     * Réduit une valeur d'erreur à une chaîne lisible, qu'elle arrive sous forme
     * de scalaire ou de tableau imbriqué (« champ => [messages] »).
     */
    private function flatten(mixed $value): string
    {
        if (is_scalar($value)) {
            return (string) $value;
        }

        if (!is_array($value)) {
            return 'inconnu';
        }

        $parts = [];
        array_walk_recursive($value, static function ($leaf) use (&$parts): void {
            if (is_scalar($leaf)) {
                $parts[] = (string) $leaf;
            }
        });

        return $parts === [] ? 'inconnu' : mb_substr(implode(' ; ', $parts), 0, 300);
    }
}
