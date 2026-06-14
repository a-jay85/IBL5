<?php

declare(strict_types=1);

namespace Tests\TradeBlock;

use PHPUnit\Framework\TestCase;
use Tests\WideUnit\Mocks\MockDatabase;
use TradeBlock\TradeBlockRepository;

class TradeBlockRepositoryTest extends TestCase
{
    private MockDatabase $mockDb;
    private TradeBlockRepository $repository;

    protected function setUp(): void
    {
        $this->mockDb = new MockDatabase();
        $this->repository = new TradeBlockRepository($this->mockDb);
    }

    public function testSetOnBlockExecutesParameterizedUpsert(): void
    {
        $this->mockDb->setReturnTrue(true);

        $result = $this->repository->setOnBlock(23, 'on the block');

        self::assertTrue($result);

        $queries = $this->mockDb->getExecutedQueries();
        self::assertCount(1, $queries);
        self::assertStringContainsString('INSERT INTO gm_trade_block', $queries[0]);
        self::assertStringContainsString('ON DUPLICATE KEY UPDATE', $queries[0]);
        // Bound pid value appears (parameterization, not string interpolation of raw input).
        self::assertStringContainsString('(23,', $queries[0]);
        self::assertStringContainsString("'on the block'", $queries[0]);
    }

    public function testRemoveFromBlockExecutesParameterizedDelete(): void
    {
        $this->mockDb->setReturnTrue(true);

        $result = $this->repository->removeFromBlock(23);

        self::assertTrue($result);

        $queries = $this->mockDb->getExecutedQueries();
        self::assertCount(1, $queries);
        self::assertStringContainsString('DELETE FROM gm_trade_block', $queries[0]);
        self::assertStringContainsString('WHERE pid = 23', $queries[0]);
    }

    public function testUpsertSeekingNoteExecutesParameterizedUpsert(): void
    {
        $this->mockDb->setReturnTrue(true);

        $result = $this->repository->upsertSeekingNote(3, 'seeking shooting');

        self::assertTrue($result);

        $queries = $this->mockDb->getExecutedQueries();
        self::assertCount(1, $queries);
        self::assertStringContainsString('INSERT INTO gm_trade_seeking', $queries[0]);
        self::assertStringContainsString('ON DUPLICATE KEY UPDATE', $queries[0]);
        self::assertStringContainsString('(3,', $queries[0]);
        self::assertStringContainsString("'seeking shooting'", $queries[0]);
    }

    public function testUpsertSeekingNoteAcceptsEmptyString(): void
    {
        $this->mockDb->setReturnTrue(true);

        $result = $this->repository->upsertSeekingNote(3, '');

        self::assertTrue($result);

        $queries = $this->mockDb->getExecutedQueries();
        self::assertStringContainsString('INSERT INTO gm_trade_seeking', $queries[0]);
    }
}
