<?php

declare(strict_types=1);

namespace Tests\Api\Repository;

use Api\Repository\ApiKeyRepository;
use Tests\WideUnit\WideUnitTestCase;

class ApiKeyRepositoryTest extends WideUnitTestCase
{
    private ApiKeyRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new ApiKeyRepository($this->mockDb);
    }

    public function testFindByHashReturnsActiveKey(): void
    {
        $this->mockDb->setMockData([
            ['key_hash' => 'h', 'key_prefix' => 'ibl_abcd', 'owner_name' => 'Team', 'permission_level' => 'public', 'rate_limit_tier' => 'standard'],
        ]);

        $result = $this->repository->findByHash('h');

        $this->assertIsArray($result);
        $this->assertSame('Team', $result['owner_name']);
    }

    public function testFindByHashReturnsNullForUnknownOrRevokedHash(): void
    {
        $this->mockDb->setMockData([]);

        $result = $this->repository->findByHash('nope');

        $this->assertNull($result);
        $this->assertQueryExecuted('is_active = 1');
    }

    public function testTouchLastUsedIssuesUpdate(): void
    {
        $this->repository->touchLastUsed('h');

        $this->assertQueryExecuted('UPDATE ibl_api_keys');
        $this->assertQueryExecuted('last_used_at = NOW()');
    }
}
