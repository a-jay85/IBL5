<?php

declare(strict_types=1);

namespace Tests\Trading;

use Tests\WideUnit\WideUnitTestCase;
use Trading\TradeExecutionRepository;

class TradeExecutionRepositoryTest extends WideUnitTestCase
{
    private TradeExecutionRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new TradeExecutionRepository($this->mockDb);
    }

    public function testInsertTradeQueueInsertsRow(): void
    {
        $result = $this->repository->insertTradeQueue('player_transfer', ['pid' => 5, 'teamid' => 2], 'A for B');

        $this->assertQueryExecuted('ibl_trade_queue');
        $this->assertIsInt($result);
    }

    public function testGetQueuedTradesReturnsRows(): void
    {
        $this->mockDb->setMockData([
            ['id' => 1, 'operation_type' => 'player_transfer', 'params' => '{"pid":5}', 'tradeline' => 'A for B'],
            ['id' => 2, 'operation_type' => 'pick_transfer',   'params' => '{"pickid":10}', 'tradeline' => 'C for D'],
        ]);

        $result = $this->repository->getQueuedTrades();

        $this->assertSame(2, count($result));
    }

    public function testGetQueuedTradesReturnsEmptyWhenQueueEmpty(): void
    {
        $this->mockDb->setMockData([]);

        $result = $this->repository->getQueuedTrades();

        $this->assertSame([], $result);
    }

    public function testExecuteQueuedPlayerTransferIssuesUpdate(): void
    {
        $result = $this->repository->executeQueuedPlayerTransfer(5, 2);

        $this->assertQueryExecuted('ibl_plr');
        $this->assertIsInt($result);
    }

    public function testExecuteQueuedPickTransferIssuesUpdate(): void
    {
        $result = $this->repository->executeQueuedPickTransfer(10, 'Boston', 3);

        $this->assertQueryExecuted('ibl_draft_picks');
        $this->assertIsInt($result);
    }

    public function testDeleteQueuedTradeIssuesDelete(): void
    {
        $result = $this->repository->deleteQueuedTrade(1);

        $this->assertQueryExecuted('DELETE FROM ibl_trade_queue');
        $this->assertIsInt($result);
    }

    public function testClearTradeQueueTruncatesTable(): void
    {
        $result = $this->repository->clearTradeQueue();

        $this->assertQueryExecuted('ibl_trade_queue');
        $this->assertIsInt($result);
    }

    public function testClearTradeInfoDeletesRows(): void
    {
        $result = $this->repository->clearTradeInfo();

        $this->assertQueryExecuted('ibl_trade_info');
        $this->assertIsInt($result);
    }
}
