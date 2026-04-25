<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use Trading\TradeCashRepository;

/**
 * Tests TradeCashRepository against real MariaDB — cash trade offers, player records, salary queries.
 */
class TradeCashRepositoryTest extends DatabaseTestCase
{
    private TradeCashRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new TradeCashRepository($this->db);
    }

    // ── Cash trade offer insert + retrieve ──────────────────────

    public function testInsertCashTradeOfferAndRetrieveByOffer(): void
    {
        $offerId = $this->insertTradeOfferRow();

        $this->repo->insertCashTradeOffer($offerId, 'Metros', 'Sharks', 500, 600, 0, 0, 0, 0);

        $row = $this->repo->getCashTransactionByOffer($offerId, 'Metros');
        self::assertNotNull($row);
        self::assertSame($offerId, $row['trade_offer_id']);
        self::assertSame('Metros', $row['sending_team']);
        self::assertSame('Sharks', $row['receiving_team']);
        self::assertSame(500, $row['salary_yr1']);
        self::assertSame(600, $row['salary_yr2']);
        self::assertSame(0, $row['salary_yr3']);
    }

    public function testGetCashTransactionByOfferReturnsNullWhenNone(): void
    {
        $result = $this->repo->getCashTransactionByOffer(999999, 'NoTeam');

        self::assertNull($result);
    }

    // ── Batch fetch by offer IDs (dynamic IN) ───────────────────

    public function testGetCashTransactionsByOfferIdsMultiple(): void
    {
        $offer1 = $this->insertTradeOfferRow();
        $offer2 = $this->insertTradeOfferRow();
        $offer3 = $this->insertTradeOfferRow();

        $this->insertTradeCashRow($offer1, 'Metros', 'Sharks', ['salary_yr1' => 100]);
        $this->insertTradeCashRow($offer2, 'Sharks', 'Metros', ['salary_yr1' => 200]);
        $this->insertTradeCashRow($offer3, 'Metros', 'Hawks', ['salary_yr1' => 300]);

        $result = $this->repo->getCashTransactionsByOfferIds([$offer1, $offer2, $offer3]);

        self::assertCount(3, $result);
        self::assertArrayHasKey("$offer1:Metros", $result);
        self::assertArrayHasKey("$offer2:Sharks", $result);
        self::assertArrayHasKey("$offer3:Metros", $result);
        self::assertSame(100, $result["$offer1:Metros"]['salary_yr1']);
        self::assertSame(200, $result["$offer2:Sharks"]['salary_yr1']);
    }

    public function testGetCashTransactionsByOfferIdsEmptyReturnsEmpty(): void
    {
        $result = $this->repo->getCashTransactionsByOfferIds([]);

        self::assertSame([], $result);
    }

    // ── Delete by offer ID ──────────────────────────────────────

    public function testDeleteTradeCashByOfferIdRemovesOnlyMatching(): void
    {
        $offer1 = $this->insertTradeOfferRow();
        $offer2 = $this->insertTradeOfferRow();

        $this->insertTradeCashRow($offer1, 'Metros', 'Sharks', ['salary_yr1' => 100]);
        $this->insertTradeCashRow($offer2, 'Hawks', 'Metros', ['salary_yr1' => 200]);

        $this->repo->deleteTradeCashByOfferId($offer1);

        $deleted = $this->repo->getCashTransactionByOffer($offer1, 'Metros');
        self::assertNull($deleted);

        $remaining = $this->repo->getCashTransactionByOffer($offer2, 'Hawks');
        self::assertNotNull($remaining);
        self::assertSame(200, $remaining['salary_yr1']);
    }

    // ── Clear all trade cash ────────────────────────────────────

    public function testClearTradeCashRemovesAllRows(): void
    {
        $offer = $this->insertTradeOfferRow();
        $this->insertTradeCashRow($offer, 'Metros', 'Sharks', ['salary_yr1' => 100]);

        $this->repo->clearTradeCash();

        $stmt = $this->db->prepare("SELECT COUNT(*) AS cnt FROM ibl_trade_cash");
        self::assertNotFalse($stmt);
        $stmt->execute();
        /** @var array{cnt: int} $row */
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        self::assertSame(0, $row['cnt']);
    }
}
