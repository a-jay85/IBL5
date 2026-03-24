<?php

declare(strict_types=1);

namespace Tests\ApiKeys;

use ApiKeys\ApiKeysService;
use ApiKeys\Contracts\ApiKeysRepositoryInterface;
use PHPUnit\Framework\TestCase;

class ApiKeysServiceTest extends TestCase
{
    public function testGenerateKeyForUserReturnsRawKeyAndPrefix(): void
    {
        $mockRepo = $this->createMock(ApiKeysRepositoryInterface::class);
        $mockRepo->method('findByUserId')->willReturn(null);
        $mockRepo->expects($this->once())->method('createKey');

        $service = new ApiKeysService($mockRepo);
        $result = $service->generateKeyForUser(1, 'testuser');

        $this->assertArrayHasKey('raw_key', $result);
        $this->assertArrayHasKey('prefix', $result);
        $this->assertStringStartsWith('ibl_', $result['raw_key']);
        $this->assertSame(36, strlen($result['raw_key'])); // ibl_ (4) + 32 hex chars
        $this->assertSame('ibl_', substr($result['prefix'], 0, 4));
        $this->assertSame(8, strlen($result['prefix']));
    }

    public function testGenerateKeyForUserThrowsWhenActiveKeyExists(): void
    {
        $stubRepo = $this->createStub(ApiKeysRepositoryInterface::class);
        $stubRepo->method('findByUserId')->willReturn([
            'key_prefix' => 'ibl_test',
            'permission_level' => 'public',
            'rate_limit_tier' => 'standard',
            'is_active' => 1,
            'created_at' => '2026-01-01 00:00:00',
            'last_used_at' => null,
        ]);

        $service = new ApiKeysService($stubRepo);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('already has an active API key');

        $service->generateKeyForUser(1, 'testuser');
    }

    public function testGenerateKeyForUserStoresHashNotRawKey(): void
    {
        $mockRepo = $this->createMock(ApiKeysRepositoryInterface::class);
        $mockRepo->method('findByUserId')->willReturn(null);

        $capturedHash = '';
        $mockRepo->expects($this->once())
            ->method('createKey')
            ->willReturnCallback(function (int $userId, string $keyHash, string $keyPrefix, string $ownerName) use (&$capturedHash): void {
                $capturedHash = $keyHash;
            });

        $service = new ApiKeysService($mockRepo);
        $result = $service->generateKeyForUser(1, 'testuser');

        $this->assertSame(hash('sha256', $result['raw_key']), $capturedHash);
    }

    public function testGenerateKeyForUserPassesUsernameAsOwnerName(): void
    {
        $mockRepo = $this->createMock(ApiKeysRepositoryInterface::class);
        $mockRepo->method('findByUserId')->willReturn(null);

        $mockRepo->expects($this->once())
            ->method('createKey')
            ->with(42, $this->anything(), $this->anything(), 'someGM');

        $service = new ApiKeysService($mockRepo);
        $service->generateKeyForUser(42, 'someGM');
    }

    public function testRevokeKeyForUserDelegatesToRepository(): void
    {
        $mockRepo = $this->createMock(ApiKeysRepositoryInterface::class);
        $mockRepo->expects($this->once())
            ->method('revokeByUserId')
            ->with(5);

        $service = new ApiKeysService($mockRepo);
        $service->revokeKeyForUser(5);
    }

    public function testGetUserKeyStatusReturnsNullWhenNoKey(): void
    {
        $stubRepo = $this->createStub(ApiKeysRepositoryInterface::class);
        $stubRepo->method('findByUserId')->willReturn(null);

        $service = new ApiKeysService($stubRepo);
        $this->assertNull($service->getUserKeyStatus(1));
    }

    public function testGetUserKeyStatusReturnsNullForInactiveKey(): void
    {
        $stubRepo = $this->createStub(ApiKeysRepositoryInterface::class);
        $stubRepo->method('findByUserId')->willReturn([
            'key_prefix' => 'ibl_test',
            'permission_level' => 'public',
            'rate_limit_tier' => 'standard',
            'is_active' => 0,
            'created_at' => '2026-01-01 00:00:00',
            'last_used_at' => null,
        ]);

        $service = new ApiKeysService($stubRepo);
        $this->assertNull($service->getUserKeyStatus(1));
    }

    public function testGetUserKeyStatusReturnsActiveKey(): void
    {
        $keyData = [
            'key_prefix' => 'ibl_test',
            'permission_level' => 'public',
            'rate_limit_tier' => 'standard',
            'is_active' => 1,
            'created_at' => '2026-01-01 00:00:00',
            'last_used_at' => '2026-03-20 10:00:00',
        ];
        $stubRepo = $this->createStub(ApiKeysRepositoryInterface::class);
        $stubRepo->method('findByUserId')->willReturn($keyData);

        $service = new ApiKeysService($stubRepo);
        $this->assertSame($keyData, $service->getUserKeyStatus(1));
    }

    public function testGenerateKeyAllowsNewKeyAfterRevokedKey(): void
    {
        $mockRepo = $this->createMock(ApiKeysRepositoryInterface::class);
        $mockRepo->method('findByUserId')->willReturn([
            'key_prefix' => 'ibl_old_',
            'permission_level' => 'public',
            'rate_limit_tier' => 'standard',
            'is_active' => 0,
            'created_at' => '2026-01-01 00:00:00',
            'last_used_at' => null,
        ]);
        $mockRepo->expects($this->once())->method('createKey');

        $service = new ApiKeysService($mockRepo);
        $result = $service->generateKeyForUser(1, 'testuser');
        $this->assertStringStartsWith('ibl_', $result['raw_key']);
    }
}
