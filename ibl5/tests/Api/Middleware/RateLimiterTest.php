<?php

declare(strict_types=1);

namespace Tests\Api\Middleware;

use Api\Middleware\RateLimiter;
use Api\Repository\RateLimitRepository;
use PHPUnit\Framework\TestCase;

class RateLimiterTest extends TestCase
{
    /**
     * @return array{key_hash: string, permission_level: string, rate_limit_tier: string}
     */
    private function makeApiKey(string $tier = 'standard'): array
    {
        return [
            'key_hash' => 'abc123hash',
            'permission_level' => 'public',
            'rate_limit_tier' => $tier,
        ];
    }

    public function testAllowsRequestUnderLimit(): void
    {
        $mockRepo = $this->createMock(RateLimitRepository::class);
        $rateLimiter = new RateLimiter($mockRepo);
        $apiKey = $this->makeApiKey();

        $mockRepo->expects($this->once())
            ->method('increment')
            ->with('abc123hash');

        $mockRepo->expects($this->once())
            ->method('getRequestCount')
            ->with('abc123hash')
            ->willReturn(30);

        $result = $rateLimiter->check($apiKey);
        $this->assertNull($result);
    }

    public function testAllowsRequestAtExactLimit(): void
    {
        $stubRepo = $this->createStub(RateLimitRepository::class);
        $rateLimiter = new RateLimiter($stubRepo);
        $apiKey = $this->makeApiKey();

        $stubRepo->method('getRequestCount')->willReturn(60);

        $result = $rateLimiter->check($apiKey);
        $this->assertNull($result);
    }

    public function testRejectsRequestOverLimit(): void
    {
        $stubRepo = $this->createStub(RateLimitRepository::class);
        $rateLimiter = new RateLimiter($stubRepo);
        $apiKey = $this->makeApiKey();

        $stubRepo->method('getRequestCount')->willReturn(61);

        $result = $rateLimiter->check($apiKey);
        $this->assertNotNull($result);
        $this->assertSame('60', $result['Retry-After']);
        $this->assertSame('60', $result['X-RateLimit-Limit']);
        $this->assertSame('0', $result['X-RateLimit-Remaining']);
        $this->assertArrayHasKey('X-RateLimit-Reset', $result);
    }

    public function testElevatedTierHasHigherLimit(): void
    {
        $stubRepo = $this->createStub(RateLimitRepository::class);
        $rateLimiter = new RateLimiter($stubRepo);
        $apiKey = $this->makeApiKey('elevated');

        $stubRepo->method('getRequestCount')->willReturn(200);

        $result = $rateLimiter->check($apiKey);
        $this->assertNull($result);
    }

    public function testElevatedTierRejectsOver300(): void
    {
        $stubRepo = $this->createStub(RateLimitRepository::class);
        $rateLimiter = new RateLimiter($stubRepo);
        $apiKey = $this->makeApiKey('elevated');

        $stubRepo->method('getRequestCount')->willReturn(301);

        $result = $rateLimiter->check($apiKey);
        $this->assertNotNull($result);
        $this->assertSame('300', $result['X-RateLimit-Limit']);
    }

    public function testUnlimitedTierNeverRejects(): void
    {
        $mockRepo = $this->createMock(RateLimitRepository::class);
        $rateLimiter = new RateLimiter($mockRepo);
        $apiKey = $this->makeApiKey('unlimited');

        $mockRepo->expects($this->once())
            ->method('increment')
            ->with('abc123hash');

        $mockRepo->expects($this->never())
            ->method('getRequestCount');

        $result = $rateLimiter->check($apiKey);
        $this->assertNull($result);
    }

    public function testUnknownTierDefaultsToStandard(): void
    {
        $stubRepo = $this->createStub(RateLimitRepository::class);
        $rateLimiter = new RateLimiter($stubRepo);
        $apiKey = $this->makeApiKey('unknown_tier');

        $stubRepo->method('getRequestCount')->willReturn(61);

        $result = $rateLimiter->check($apiKey);
        $this->assertNotNull($result);
        $this->assertSame('60', $result['X-RateLimit-Limit']);
    }

    public function testIncrementsBeforeChecking(): void
    {
        $mockRepo = $this->createMock(RateLimitRepository::class);
        $rateLimiter = new RateLimiter($mockRepo);
        $apiKey = $this->makeApiKey();
        $callOrder = [];

        $mockRepo->expects($this->once())
            ->method('increment')
            ->willReturnCallback(function () use (&$callOrder): void {
                $callOrder[] = 'increment';
            });

        $mockRepo->expects($this->once())
            ->method('getRequestCount')
            ->willReturnCallback(function () use (&$callOrder): int {
                $callOrder[] = 'getRequestCount';
                return 1;
            });

        $rateLimiter->check($apiKey);
        $this->assertSame(['increment', 'getRequestCount'], $callOrder);
    }
}
