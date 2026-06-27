<?php

declare(strict_types=1);

namespace Tests\Api\Repository;

use Api\Repository\RateLimitRepository;
use Tests\WideUnit\WideUnitTestCase;

class RateLimitRepositoryTest extends WideUnitTestCase
{
    private RateLimitRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new RateLimitRepository($this->mockDb);
    }

    public function testGetRequestCountReturnsStoredCount(): void
    {
        $this->mockDb->setMockData([['request_count' => 42]]);

        $this->assertSame(42, $this->repository->getRequestCount('h'));
    }

    public function testGetRequestCountReturnsZeroWhenNoWindowRow(): void
    {
        $this->mockDb->setMockData([]);

        $this->assertSame(0, $this->repository->getRequestCount('h'));
    }

    public function testIncrementIssuesUpsert(): void
    {
        $this->repository->increment('h');

        $this->assertQueryExecuted('INSERT INTO ibl_api_rate_limits');
        $this->assertQueryExecuted('ON DUPLICATE KEY UPDATE');
    }

    public function testPruneOldEntriesIssuesDelete(): void
    {
        $this->repository->pruneOldEntries();

        $this->assertQueryExecuted('DELETE FROM ibl_api_rate_limits');
    }
}
