<?php

declare(strict_types=1);

namespace Tests\Trading;

use PHPUnit\Framework\TestCase;
use Trading\TradeCashRepository;
use Tests\WideUnit\Mocks\MockDatabase;

/**
 * TradeCashRepositoryTest - Tests for TradeCashRepository database operations
 *
 * Tests cash transaction operations: cash trade offers, team cash salary records, and bulk clear.
 */
class TradeCashRepositoryTest extends TestCase
{
    private MockDatabase $mockDb;

    protected function setUp(): void
    {
        $this->mockDb = new MockDatabase();
        $GLOBALS['mysqli_db'] = $this->mockDb;
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['mysqli_db']);
    }

    // ============================================
    // CONSTRUCTOR TESTS
    // ============================================

    public function testRepositoryImplementsCorrectInterface(): void
    {
        self::assertContains(
            \Trading\Contracts\TradeCashRepositoryInterface::class,
            (array) class_implements(TradeCashRepository::class)
        );
    }

    // ============================================
    // GET CASH TRANSACTION BY OFFER TESTS
    // ============================================

    public function testGetCashTransactionByOfferReturnsDataWhenFound(): void
    {
        $repository = new TradeCashRepository($this->mockDb);
        $this->mockDb->setMockData([
            ['trade_offer_id' => 5, 'sending_team' => 'Boston', 'receiving_team' => 'Denver', 'salary_yr1' => 50, 'salary_yr2' => 75, 'salary_yr3' => 0, 'salary_yr4' => 0, 'salary_yr5' => 0, 'salary_yr6' => 0],
        ]);

        $result = $repository->getCashTransactionByOffer(5, 'Boston');

        $this->assertIsArray($result);
        $this->assertSame(5, $result['trade_offer_id']);
    }

    public function testGetCashTransactionByOfferReturnsNullWhenNotFound(): void
    {
        $repository = new TradeCashRepository($this->mockDb);
        $this->mockDb->setMockData([]);

        $result = $repository->getCashTransactionByOffer(999, 'Nobody');

        $this->assertNull($result);
    }

    // ============================================
    // CLEAR TRADE CASH TESTS
    // ============================================

    public function testClearTradeCashExecutesDelete(): void
    {
        $repository = new TradeCashRepository($this->mockDb);

        $result = $repository->clearTradeCash();

        $this->assertIsInt($result);
    }

    // ============================================
    // INSERT CASH TRADE OFFER TESTS
    // ============================================

    public function testInsertCashTradeOfferExecutesInsert(): void
    {
        $repository = new TradeCashRepository($this->mockDb);

        $result = $repository->insertCashTradeOffer(1, 'Miami', 'Chicago', 100, 200, 0, 0, 0, 0);

        $this->assertIsInt($result);
    }

    // ============================================
    // DELETE TRADE CASH BY OFFER ID TESTS
    // ============================================

    public function testDeleteTradeCashByOfferIdExecutesDelete(): void
    {
        $repository = new TradeCashRepository($this->mockDb);

        $result = $repository->deleteTradeCashByOfferId(1);

        $this->assertIsInt($result);
    }
}
