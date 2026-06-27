<?php

declare(strict_types=1);

namespace Tests\ApiKeys;

use ApiKeys\ApiKeysRepository;
use Tests\WideUnit\WideUnitTestCase;

class ApiKeysRepositoryTest extends WideUnitTestCase
{
    private ApiKeysRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new ApiKeysRepository($this->mockDb);
    }

    public function testFindByUserIdReturnsLatestKeyRow(): void
    {
        $this->mockDb->setMockData([
            ['key_prefix' => 'ibl_abcd', 'permission_level' => 'public', 'rate_limit_tier' => 'standard', 'is_active' => 1, 'created_at' => '2026-01-02', 'last_used_at' => null],
        ]);

        $result = $this->repository->findByUserId(1);

        $this->assertIsArray($result);
        $this->assertSame('ibl_abcd', $result['key_prefix']);
        $this->assertSame(1, $result['is_active']);
    }

    public function testFindByUserIdReturnsNullWhenNoKey(): void
    {
        $this->mockDb->setMockData([]);

        $result = $this->repository->findByUserId(999);

        $this->assertNull($result);
    }

    public function testRevokeByUserIdScopesToActiveKeys(): void
    {
        $this->repository->revokeByUserId(7);

        $this->assertQueryExecuted('UPDATE ibl_api_keys');
        $this->assertQueryExecuted('is_active = 0');
        $this->assertQueryExecuted('is_active = 1');
    }

    public function testCreateKeyInsertsRow(): void
    {
        $this->repository->createKey(1, 'hash', 'ibl_abcd', 'TeamName');

        $this->assertQueryExecuted('INSERT INTO ibl_api_keys');
    }
}
