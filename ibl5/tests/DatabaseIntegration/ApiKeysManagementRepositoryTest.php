<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use ApiKeys\ApiKeysRepository;
use PHPUnit\Framework\Attributes\Group;

#[Group('database')]
class ApiKeysManagementRepositoryTest extends DatabaseTestCase
{
    private ApiKeysRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new ApiKeysRepository($this->db);
    }

    public function testFindByUserIdReturnsNullWhenNoKey(): void
    {
        $result = $this->repo->findByUserId(1);
        self::assertNull($result);
    }

    public function testFindByUserIdReturnsKeyWhenExists(): void
    {
        $this->insertRow('ibl_api_keys', [
            'user_id' => 1,
            'key_hash' => str_repeat('a', 64),
            'key_prefix' => 'ibl_test',
            'owner_name' => 'testowner',
            'permission_level' => 'public',
            'rate_limit_tier' => 'standard',
            'is_active' => 1,
        ]);

        $result = $this->repo->findByUserId(1);

        self::assertNotNull($result);
        self::assertSame('ibl_test', $result['key_prefix']);
        self::assertSame('public', $result['permission_level']);
        self::assertSame('standard', $result['rate_limit_tier']);
        self::assertSame(1, $result['is_active']);
        self::assertArrayHasKey('created_at', $result);
        self::assertNull($result['last_used_at']);
    }

    public function testFindByUserIdReturnsLatestByCreatedAt(): void
    {
        $this->insertRow('ibl_api_keys', [
            'user_id' => 1,
            'key_hash' => str_repeat('a', 64),
            'key_prefix' => 'ibl_old_',
            'owner_name' => 'testowner',
            'permission_level' => 'public',
            'rate_limit_tier' => 'standard',
            'is_active' => 0,
            'created_at' => '2025-01-01 00:00:00',
        ]);

        $this->insertRow('ibl_api_keys', [
            'user_id' => 1,
            'key_hash' => str_repeat('b', 64),
            'key_prefix' => 'ibl_new_',
            'owner_name' => 'testowner',
            'permission_level' => 'public',
            'rate_limit_tier' => 'standard',
            'is_active' => 1,
            'created_at' => '2025-06-01 00:00:00',
        ]);

        $result = $this->repo->findByUserId(1);

        self::assertNotNull($result);
        self::assertSame('ibl_new_', $result['key_prefix']);
    }

    public function testCreateKeyInsertsRow(): void
    {
        $this->repo->createKey(1, str_repeat('c', 64), 'ibl_crea', 'creator');

        $result = $this->repo->findByUserId(1);

        self::assertNotNull($result);
        self::assertSame('ibl_crea', $result['key_prefix']);
        self::assertSame('public', $result['permission_level']);
        self::assertSame('standard', $result['rate_limit_tier']);
        self::assertSame(1, $result['is_active']);
    }

    public function testRevokeByUserIdDeactivatesActiveKey(): void
    {
        $this->insertRow('ibl_api_keys', [
            'user_id' => 2,
            'key_hash' => str_repeat('d', 64),
            'key_prefix' => 'ibl_rev_',
            'owner_name' => 'revoketest',
            'permission_level' => 'public',
            'rate_limit_tier' => 'standard',
            'is_active' => 1,
        ]);

        $this->repo->revokeByUserId(2);

        $result = $this->repo->findByUserId(2);
        self::assertNotNull($result);
        self::assertSame(0, $result['is_active']);
    }

    public function testRevokeByUserIdDoesNotAffectAlreadyInactiveKey(): void
    {
        $this->insertRow('ibl_api_keys', [
            'user_id' => 1,
            'key_hash' => str_repeat('e', 64),
            'key_prefix' => 'ibl_ina_',
            'owner_name' => 'inactivetest',
            'permission_level' => 'public',
            'rate_limit_tier' => 'standard',
            'is_active' => 0,
        ]);

        $this->repo->revokeByUserId(1);

        $result = $this->repo->findByUserId(1);
        self::assertNotNull($result);
        self::assertSame(0, $result['is_active']);
    }
}
