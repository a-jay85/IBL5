<?php

declare(strict_types=1);

namespace Tests\Trading;

use Tests\WideUnit\WideUnitTestCase;
use Trading\TradeAssetRepository;

class TradeAssetRepositoryTest extends WideUnitTestCase
{
    private TradeAssetRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new TradeAssetRepository($this->mockDb);
    }

    public function testGetDraftPicksByIdsMapsKeyedByPickid(): void
    {
        $this->mockDb->setMockData([
            ['pickid' => 10, 'ownerofpick' => 'A'],
            ['pickid' => 20, 'ownerofpick' => 'B'],
        ]);

        $result = $this->repository->getDraftPicksByIds([10, 20]);

        $this->assertArrayHasKey(10, $result);
        $this->assertArrayHasKey(20, $result);
        $this->assertSame('A', $result[10]['ownerofpick']);
        $this->assertSame('B', $result[20]['ownerofpick']);
    }

    public function testGetDraftPicksByIdsEmptyInputShortCircuits(): void
    {
        $result = $this->repository->getDraftPicksByIds([]);

        $this->assertSame([], $result);
        $this->assertQueryNotExecuted('ibl_draft_picks');
    }

    public function testGetDraftPickByIdReturnsNullWhenNotFound(): void
    {
        $this->mockDb->setMockData([]);

        $result = $this->repository->getDraftPickById(50);

        $this->assertNull($result);
    }

    public function testUpdateDraftPickOwnerByIdIssuesUpdate(): void
    {
        $result = $this->repository->updateDraftPickOwnerById(10, 'Boston', 3);

        $this->assertQueryExecuted('UPDATE ibl_draft_picks');
        $this->assertIsInt($result);
    }

    public function testPlayerIdExistsReturnsFalseWhenAbsent(): void
    {
        $result = $this->repository->playerIdExists(123);

        $this->assertFalse($result);
    }
}
