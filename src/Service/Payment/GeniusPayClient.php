<?php

namespace App\Service\Payment;

use App\Dto\GeniusPay\CreatePaymentResult;
use App\Dto\GeniusPay\GeniusPayCredentials;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Client HTTP de la passerelle GeniusPay.
 *
 * ⚠️ Hypothèses sur le contrat d'API (à confirmer avec la doc marchand) — toutes
 * centralisées ici pour un ajustement en un seul endroit :
 *   - Authentification : en-têtes X-API-Key et X-API-Secret.
 *   - Création : POST {base}/payments — corps { amount, currency, description,
 *     reference, callback_url, return_url, customer{...} }. Si payment_method est
 *     omis, GeniusPay renvoie une URL de checkout.
 *   - Réponse : identifiant de transaction et URL de checkout (noms de champs
 *     absorbés via firstKey() pour tolérer les variantes).
 *   - Statut : GET {base}/payments/{id}.
 */
class GeniusPayClient
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $baseUrl,
        private readonly string $apiKey,
        private readonly string $apiSecret,
        private readonly LoggerInterface $paymentLogger,
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== '' && $this->apiSecret !== '';
    }

    /**
     * Crée un paiement et renvoie l'URL de checkout.
     *
     * @param array{name?: string, email?: string, phone?: string} $customer
     *
     * @throws PaymentGatewayException
     */
    public function createPayment(
        float $amount,
        string $reference,
        string $description,
        string $returnUrl,
        ?string $callbackUrl = null,
        array $customer = [],
        ?GeniusPayCredentials $credentials = null,
    ): CreatePaymentResult {
        $creds = $credentials ?? $this->defaultCredentials();
        if (!$creds->isConfigured()) {
            throw new PaymentGatewayException('La passerelle GeniusPay n\'est pas configurée (clés manquantes).');
        }

        // Noms de champs confirmés via le sandbox GeniusPay (réponse 201) :
        // external_reference = notre référence, success_url/error_url = retours navigateur,
        // callback_url = webhook serveur. La corrélation principale se fait ensuite via
        // l'identifiant de transaction renvoyé (data.id).
        $body = [
            'amount' => (int) round($amount),
            'currency' => 'XOF',
            'description' => $description,
            'external_reference' => $reference,
            'success_url' => $returnUrl,
            'error_url' => $returnUrl,
            'metadata' => ['reference' => $reference],
        ];
        if ($callbackUrl !== null) {
            $body['callback_url'] = $callbackUrl;
        }
        if ($customer !== []) {
            $body['customer'] = $customer;
        }

        $this->paymentLogger->info('GeniusPay createPayment request', [
            'reference' => $reference,
            'amount' => $body['amount'],
        ]);

        try {
            $response = $this->httpClient->request('POST', $this->endpoint($creds, '/payments'), [
                'headers' => $this->authHeaders($creds),
                'json' => $body,
                'timeout' => 20,
            ]);

            $status = $response->getStatusCode();
            $data = $this->decode($response->getContent(false));
        } catch (\Throwable $e) {
            $this->paymentLogger->error('GeniusPay createPayment transport error', [
                'reference' => $reference,
                'error' => $e->getMessage(),
            ]);
            throw new PaymentGatewayException('Impossible de joindre la passerelle de paiement.', 0, $e);
        }

        if ($status >= 400) {
            $this->paymentLogger->error('GeniusPay createPayment error response', [
                'reference' => $reference,
                'status' => $status,
                'body' => $data,
            ]);
            // Message lisible renvoyé par la passerelle (ex. « montant minimum 200 »).
            $message = $data['error']['message'] ?? $data['message'] ?? null;
            throw new PaymentGatewayException(
                $message ?: sprintf('La passerelle a refusé la création du paiement (HTTP %d).', $status)
            );
        }

        $payload = \is_array($data['data'] ?? null) ? $data['data'] : $data;

        $transactionId = $this->firstKey($payload, ['id', 'transaction_id', 'transactionId', 'payment_id']);
        $gatewayReference = $this->firstKey($payload, ['reference', 'payment_reference']);
        $checkoutUrl = $this->firstKey($payload, ['checkout_url', 'checkoutUrl', 'payment_url', 'paymentUrl', 'url', 'redirect_url']);
        $providerStatus = $this->firstKey($payload, ['status', 'state']);

        // Identifiant de corrélation : la référence publique (ex. SANDBOX_…) est
        // privilégiée car c'est elle qui figure dans l'URL de checkout et, le plus
        // souvent, dans les webhooks. On retombe sur l'id numérique à défaut.
        $correlationId = $gatewayReference ?? $transactionId;

        if ($correlationId === null) {
            $this->paymentLogger->error('GeniusPay createPayment: identifiant de transaction absent', [
                'reference' => $reference,
                'body' => $data,
            ]);
            throw new PaymentGatewayException('Réponse invalide de la passerelle (transaction manquante).');
        }

        $this->paymentLogger->info('GeniusPay createPayment success', [
            'reference' => $reference,
            'transactionId' => $transactionId,
            'gatewayReference' => $gatewayReference,
        ]);

        return new CreatePaymentResult((string) $correlationId, $checkoutUrl, $providerStatus, $gatewayReference, $payload);
    }

    /**
     * Consulte le statut d'une transaction.
     *
     * @return array<string, mixed>
     *
     * @throws PaymentGatewayException
     */
    public function getPaymentStatus(string $transactionId, ?GeniusPayCredentials $credentials = null): array
    {
        $creds = $credentials ?? $this->defaultCredentials();

        try {
            $response = $this->httpClient->request('GET', $this->endpoint($creds, '/payments/' . rawurlencode($transactionId)), [
                'headers' => $this->authHeaders($creds),
                'timeout' => 20,
            ]);

            return $this->decode($response->getContent(false));
        } catch (\Throwable $e) {
            $this->paymentLogger->error('GeniusPay getPaymentStatus error', [
                'transactionId' => $transactionId,
                'error' => $e->getMessage(),
            ]);
            throw new PaymentGatewayException('Impossible de consulter le statut du paiement.', 0, $e);
        }
    }

    private function defaultCredentials(): GeniusPayCredentials
    {
        return new GeniusPayCredentials($this->baseUrl, $this->apiKey, $this->apiSecret);
    }

    /**
     * @return array<string, string>
     */
    private function authHeaders(GeniusPayCredentials $creds): array
    {
        return [
            'X-API-Key' => $creds->apiKey,
            'X-API-Secret' => $creds->apiSecret,
            'Accept' => 'application/json',
        ];
    }

    private function endpoint(GeniusPayCredentials $creds, string $path): string
    {
        return rtrim($creds->baseUrl, '/') . $path;
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(string $content): array
    {
        if ($content === '') {
            return [];
        }

        $decoded = json_decode($content, true);

        return \is_array($decoded) ? $decoded : [];
    }

    /**
     * Retourne la première valeur non vide parmi une liste de clés possibles.
     *
     * @param array<string, mixed> $data
     * @param string[]             $keys
     */
    private function firstKey(array $data, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (isset($data[$key]) && $data[$key] !== '') {
                return (string) $data[$key];
            }
        }

        return null;
    }
}
