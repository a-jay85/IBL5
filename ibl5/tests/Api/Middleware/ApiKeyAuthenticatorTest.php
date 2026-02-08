<?php

declare(strict_types=1);

namespace Tests\Api\Middleware;

use Api\Middleware\ApiKeyAuthenticator;
use Api\Repository\ApiKeyRepository;
use PHPUnit\Framework\TestCase;

class ApiKeyAuthenticatorTest extends TestCase
{
    /** @var ApiKeyRepository&\PHPUnit\Framework\MockObject\MockObject */
    private ApiKeyRepository $mockRepo;
    private ApiKeyAuthenticator $authenticator;

    protected function setUp(): void
    {
        $this->mockRepo = $this->createMock(ApiKeyRepository::class);
        $this->authenticator = new ApiKeyAuthenticator($this->mockRepo);
    }

    protected function tearDown(): void
    {
        unset($_SERVER['HTTP_X_API_KEY']);
    }

    public function testReturnsNullWhenNoApiKeyHeader(): void
    {
        unset($_SERVER['HTTP_X_API_KEY']);

        $this->mockRepo->expects($this->never())->method('findByHash');

        $result = $this->authenticator->authenticate();
        $this->assertNull($result);
    }

    public function testReturnsNullWhenEmptyApiKeyHeader(): void
    {
        $_SERVER['HTTP_X_API_KEY'] = '';

        $this->mockRepo->expects($this->never())->method('findByHash');

        $result = $this->authenticator->authenticate();
        $this->assertNull($result);
    }

    public function testReturnsNullWhenKeyNotFoundInDatabase(): void
    {
        $_SERVER['HTTP_X_API_KEY'] = 'ibl_invalid_key_12345678901234567890';

        $this->mockRepo->expects($this->once())
            ->method('findByHash')
            ->with(hash('sha256', 'ibl_invalid_key_12345678901234567890'))
            ->willReturn(null);

        $result = $this->authenticator->authenticate();
        $this->assertNull($result);
    }

    public function testReturnsApiKeyRecordWhenValid(): void
    {
        $apiKey = 'ibl_test1234567890abcdef1234567890';
        $keyHash = hash('sha256', $apiKey);
        $record = [
            'key_hash' => $keyHash,
            'key_prefix' => 'ibl_test',
            'owner_name' => 'Test Bot',
            'permission_level' => 'public',
            'rate_limit_tier' => 'standard',
        ];

        $_SERVER['HTTP_X_API_KEY'] = $apiKey;

        $this->mockRepo->expects($this->once())
            ->method('findByHash')
            ->with($keyHash)
            ->willReturn($record);

        $this->mockRepo->expects($this->once())
            ->method('touchLastUsed')
            ->with($keyHash);

        $result = $this->authenticator->authenticate();
        $this->assertSame($record, $result);
    }

    public function testHashesKeyWithSha256(): void
    {
        $apiKey = 'ibl_abc123';
        $expectedHash = hash('sha256', $apiKey);

        $_SERVER['HTTP_X_API_KEY'] = $apiKey;

        $this->mockRepo->expects($this->once())
            ->method('findByHash')
            ->with($expectedHash)
            ->willReturn(null);

        $this->authenticator->authenticate();
    }
}
