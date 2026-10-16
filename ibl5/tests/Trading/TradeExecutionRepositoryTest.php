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

    public function testClearTradeInfoDeletesRows(): void
    {
        $result = $this->repository->clearTradeInfo();

        $this->assertQueryExecuted('ibl_trade_info');
        $this->assertIsInt($result);
    }
}
