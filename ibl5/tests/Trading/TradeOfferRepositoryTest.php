<?php

declare(strict_types=1);

namespace Tests\Trading;

use Tests\WideUnit\WideUnitTestCase;
use Trading\Contracts\TradeCashRepositoryInterface;
use Trading\TradeItemType;
use Trading\TradeOfferRepository;

class TradeOfferRepositoryTest extends WideUnitTestCase
{
    public function testGenerateNextTradeOfferIdReturnsNewId(): void
    {
        $repo = new TradeOfferRepository($this->mockDb, 'iblhoops.net');
        $this->mockDb->onQuery('LAST_INSERT_ID', [['id' => 7]]);

        $result = $repo->generateNextTradeOfferId();

        $this->assertSame(7, $result);
    }

    public function testGenerateNextTradeOfferIdThrowsWhenIdZero(): void
    {
        $repo = new TradeOfferRepository($this->mockDb, 'iblhoops.net');
        $this->mockDb->onQuery('LAST_INSERT_ID', [['id' => 0]]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to generate trade offer ID');

        $repo->generateNextTradeOfferId();
    }

    public function testDeleteTradeOfferIsTransactionalAndCoordinatesCash(): void
    {
        $cash = $this->createMock(TradeCashRepositoryInterface::class);
        $cash->expects($this->once())->method('deleteTradeCashByOfferId')->with(5);

        $repo = new TradeOfferRepository($this->mockDb, 'iblhoops.net', $cash);
        $repo->deleteTradeOffer(5);

        $log = $this->mockDb->getOperationLog();
        $beginIdx = array_search('BEGIN', $log, true);
        $commitIdx = array_search('COMMIT', $log, true);
        $this->assertNotFalse($beginIdx, 'Expected BEGIN in operation log');
        $this->assertNotFalse($commitIdx, 'Expected COMMIT in operation log');
        $this->assertLessThan($commitIdx, $beginIdx, 'BEGIN must precede COMMIT');

        $this->assertQueryExecuted('DELETE FROM ibl_trade_info');
        $this->assertQueryExecuted('DELETE FROM ibl_trade_offers');
    }

    public function testGetAllTradeOffersExcludesCompleted(): void
    {
        $this->mockDb->setMockTradeInfo([['tradeofferid' => 1, 'approval' => 'pending']]);
        $repo = new TradeOfferRepository($this->mockDb, 'iblhoops.net');

        $result = $repo->getAllTradeOffers();

        $this->assertNotEmpty($result);
        $this->assertQueryExecuted("approval != 'completed'");
    }

    public function testMarkTradeInfoCompletedIssuesUpdate(): void
    {
        $repo = new TradeOfferRepository($this->mockDb, 'iblhoops.net');

        $result = $repo->markTradeInfoCompleted(3);

        $this->assertIsInt($result);
        $this->assertQueryExecuted('UPDATE ibl_trade_info');
    }

    public function testInsertTradeItemUsesTestApprovalOnLocalhost(): void
    {
        $repo = new TradeOfferRepository($this->mockDb, 'localhost');

        $repo->insertTradeItem(1, 99, TradeItemType::Player, 'Boston', 'Denver', 'approval_value');

        $this->assertQueryExecuted('INSERT INTO ibl_trade_info');
    }
}
