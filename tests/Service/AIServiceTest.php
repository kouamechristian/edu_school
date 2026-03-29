<?php

namespace App\Tests\Service;

use App\Service\AI\AIService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class AIServiceTest extends TestCase
{
    private HttpClientInterface $httpClient;
    private CacheInterface $cache;
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->cache = $this->createMock(CacheInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    private function createService(
        string $apiKey = 'sk-ant-valid-key',
        string $model = 'claude-sonnet-4-20250514',
        int $maxTokens = 1000,
        int $cacheTtl = 3600,
        bool $enabled = true
    ): AIService {
        return new AIService(
            $this->httpClient,
            $this->cache,
            $this->logger,
            $apiKey,
            $model,
            $maxTokens,
            $cacheTtl,
            $enabled
        );
    }

    public function testIsEnabledWithValidConfig(): void
    {
        $service = $this->createService();
        $this->assertTrue($service->isEnabled());
    }

    public function testIsDisabledWhenFlagIsFalse(): void
    {
        $service = $this->createService(enabled: false);
        $this->assertFalse($service->isEnabled());
    }

    public function testIsDisabledWithPlaceholderKey(): void
    {
        $service = $this->createService(apiKey: 'your_key_here');
        $this->assertFalse($service->isEnabled());
    }

    public function testAskReturnsFallbackWhenDisabled(): void
    {
        $service = $this->createService(enabled: false);

        $result = $service->ask('Bonjour');

        $this->assertStringContainsString('indisponible', $result);
    }

    public function testAskUsesCacheOnSecondCall(): void
    {
        $this->cache->method('get')
            ->willReturn('Réponse cachée');

        $service = $this->createService();
        $result = $service->ask('Bonjour');

        $this->assertSame('Réponse cachée', $result);
    }

    public function testAskReturnsFallbackOnException(): void
    {
        $this->cache->method('get')
            ->willThrowException(new \RuntimeException('API error'));

        $service = $this->createService();
        $result = $service->ask('Bonjour');

        $this->assertStringContainsString('indisponible', $result);
    }

    public function testAskWithoutCacheReturnsFallbackWhenDisabled(): void
    {
        $service = $this->createService(enabled: false);

        $result = $service->askWithoutCache('Bonjour');

        $this->assertStringContainsString('indisponible', $result);
    }

    public function testGetStatsReturnsCorrectStructure(): void
    {
        $service = $this->createService();
        $stats = $service->getStats();

        $this->assertArrayHasKey('total_calls', $stats);
        $this->assertArrayHasKey('cached_calls', $stats);
        $this->assertArrayHasKey('failed_calls', $stats);
        $this->assertArrayHasKey('enabled', $stats);
        $this->assertArrayHasKey('model', $stats);
        $this->assertSame(0, $stats['total_calls']);
        $this->assertSame('claude-sonnet-4-20250514', $stats['model']);
    }

    public function testGetStatsIncrementsTotalCallsOnAsk(): void
    {
        $this->cache->method('get')
            ->willReturn('OK');

        $service = $this->createService();
        $service->ask('test');

        $stats = $service->getStats();
        $this->assertSame(1, $stats['total_calls']);
    }

    public function testGetStatsIncrementsFailedCallsOnError(): void
    {
        $this->cache->method('get')
            ->willThrowException(new \RuntimeException('fail'));

        $service = $this->createService();
        $service->ask('test');

        $stats = $service->getStats();
        $this->assertSame(1, $stats['total_calls']);
        $this->assertSame(1, $stats['failed_calls']);
    }
}
