<?php

namespace App\Service\AI;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class AIService
{
    private const API_URL = 'https://api.anthropic.com/v1/messages';
    private const API_VERSION = '2023-06-01';
    private const TIMEOUT = 30;

    private int $totalCalls = 0;
    private int $cachedCalls = 0;
    private int $failedCalls = 0;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CacheInterface $cache,
        private readonly LoggerInterface $logger,
        private readonly string $apiKey,
        private readonly string $model,
        private readonly int $maxTokens,
        private readonly int $cacheTtl,
        private readonly bool $enabled,
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->enabled && $this->apiKey !== 'your_key_here';
    }

    public function ask(string $prompt, string $context = '', ?string $systemPrompt = null): string
    {
        if (!$this->isEnabled()) {
            $this->logger->warning('AI module is disabled or API key not configured');
            return $this->getFallbackResponse();
        }

        $this->totalCalls++;
        $cacheKey = 'ai_' . md5($prompt . $context . ($systemPrompt ?? ''));

        try {
            return $this->cache->get($cacheKey, function (ItemInterface $item) use ($prompt, $context, $systemPrompt) {
                $item->expiresAfter($this->cacheTtl);

                return $this->callApi($prompt, $context, $systemPrompt);
            });
        } catch (\Throwable $e) {
            $this->failedCalls++;
            $this->logger->error('AI request failed', [
                'error' => $e->getMessage(),
                'prompt_length' => strlen($prompt),
            ]);

            return $this->getFallbackResponse();
        }
    }

    public function askWithoutCache(string $prompt, string $context = '', ?string $systemPrompt = null): string
    {
        if (!$this->isEnabled()) {
            return $this->getFallbackResponse();
        }

        $this->totalCalls++;

        try {
            return $this->callApi($prompt, $context, $systemPrompt);
        } catch (\Throwable $e) {
            $this->failedCalls++;
            $this->logger->error('AI request failed (no cache)', [
                'error' => $e->getMessage(),
            ]);

            return $this->getFallbackResponse();
        }
    }

    private function callApi(string $prompt, string $context, ?string $systemPrompt): string
    {
        $messages = [];

        if ($context !== '') {
            $messages[] = [
                'role' => 'user',
                'content' => "Contexte :\n" . $context,
            ];
            $messages[] = [
                'role' => 'assistant',
                'content' => 'Compris, je prends en compte ce contexte.',
            ];
        }

        $messages[] = [
            'role' => 'user',
            'content' => $prompt,
        ];

        $body = [
            'model' => $this->model,
            'max_tokens' => $this->maxTokens,
            'messages' => $messages,
        ];

        if ($systemPrompt !== null) {
            $body['system'] = $systemPrompt;
        }

        $this->logger->info('AI API call', [
            'model' => $this->model,
            'prompt_length' => strlen($prompt),
            'has_context' => $context !== '',
        ]);

        $response = $this->httpClient->request('POST', self::API_URL, [
            'headers' => [
                'Content-Type' => 'application/json',
                'x-api-key' => $this->apiKey,
                'anthropic-version' => self::API_VERSION,
            ],
            'json' => $body,
            'timeout' => self::TIMEOUT,
        ]);

        $statusCode = $response->getStatusCode();
        if ($statusCode !== 200) {
            throw new \RuntimeException(sprintf('Anthropic API returned HTTP %d', $statusCode));
        }

        $data = $response->toArray();
        $text = $data['content'][0]['text'] ?? '';

        $this->logger->info('AI API response', [
            'status' => $statusCode,
            'response_length' => strlen($text),
            'usage' => $data['usage'] ?? [],
        ]);

        return trim($text);
    }

    private function getFallbackResponse(): string
    {
        return 'Le service IA est temporairement indisponible. Veuillez réessayer ultérieurement.';
    }

    public function getStats(): array
    {
        return [
            'total_calls' => $this->totalCalls,
            'cached_calls' => $this->cachedCalls,
            'failed_calls' => $this->failedCalls,
            'enabled' => $this->isEnabled(),
            'model' => $this->model,
        ];
    }

    public function clearCache(): void
    {
        if ($this->cache instanceof \Symfony\Contracts\Cache\TagAwareCacheInterface) {
            $this->cache->invalidateTags(['ai_cache']);
        }
    }
}
