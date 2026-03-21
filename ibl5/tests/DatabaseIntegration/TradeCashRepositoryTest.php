<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use Trading\TradeCashRepository;

/**
 * Tests TradeCashRepository against real MariaDB — cash trade offers, player records, salary queries.
 *
 * NOTE: The following methods reference columns (teamname, year1-year6, row) that do not exist
 * in the ibl_trade_cash table schema. They are dead code and are not tested:
 *   - getCashDetails()
 *   - insertPositiveCashTransaction()
 *   - insertNegativeCashTransaction()
 *   - deleteCashTransaction()
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
        self::assertSame($offerId, $row['tradeOfferID']);
        self::assertSame('Metros', $row['sendingTeam']);
        self::assertSame('Sharks', $row['receivingTeam']);
        self::assertSame(500, $row['cy1']);
        self::assertSame(600, $row['cy2']);
        self::assertSame(0, $row['cy3']);
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

        $this->insertTradeCashRow($offer1, 'Metros', 'Sharks', ['cy1' => 100]);
        $this->insertTradeCashRow($offer2, 'Sharks', 'Metros', ['cy1' => 200]);
        $this->insertTradeCashRow($offer3, 'Metros', 'Hawks', ['cy1' => 300]);

        $result = $this->repo->getCashTransactionsByOfferIds([$offer1, $offer2, $offer3]);

        self::assertCount(3, $result);
        self::assertArrayHasKey("$offer1:Metros", $result);
        self::assertArrayHasKey("$offer2:Sharks", $result);
        self::assertArrayHasKey("$offer3:Metros", $result);
        self::assertSame(100, $result["$offer1:Metros"]['cy1']);
        self::assertSame(200, $result["$offer2:Sharks"]['cy1']);
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

        $this->insertTradeCashRow($offer1, 'Metros', 'Sharks', ['cy1' => 100]);
        $this->insertTradeCashRow($offer2, 'Hawks', 'Metros', ['cy1' => 200]);

        $this->repo->deleteTradeCashByOfferId($offer1);

        $deleted = $this->repo->getCashTransactionByOffer($offer1, 'Metros');
        self::assertNull($deleted);

        $remaining = $this->repo->getCashTransactionByOffer($offer2, 'Hawks');
        self::assertNotNull($remaining);
        self::assertSame(200, $remaining['cy1']);
    }

    // ── Cash player record (inserts into ibl_plr) ───────────────

    public function testInsertCashPlayerRecordInsertsIntoPLR(): void
    {
        $this->repo->insertCashPlayerRecord([
            'ordinal' => 999,
            'pid' => 200032001,
            'name' => '|Cash TestPlr',
            'tid' => 3,
            'exp' => 0,
            'cy' => 1,
            'cyt' => 1,
            'cy1' => 500,
            'cy2' => 0,
            'cy3' => 0,
            'cy4' => 0,
            'cy5' => 0,
            'cy6' => 0,
            'retired' => 0,
        ]);

        $stmt = $this->db->prepare("SELECT name, tid, cy1 FROM ibl_plr WHERE pid = ?");
        self::assertNotFalse($stmt);
        $pid = 200032001;
        $stmt->bind_param('i', $pid);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        self::assertNotNull($row);
        self::assertSame('|Cash TestPlr', $row['name']);
        self::assertSame(3, $row['tid']);
        self::assertSame(500, $row['cy1']);
    }

    // ── Pipe-prefixed salary records ────────────────────────────

    public function testGetTeamCashRecordsForSalaryReturnsOnlyPipePrefixed(): void
    {
        // Regular player — should NOT appear
        $this->insertTestPlayer(200032002, 'Regular Player', ['tid' => 8, 'ordinal' => 100]);
        // Cash player (pipe-prefixed) — should appear
        $this->insertTestPlayer(200032003, '|Cash Salary', ['tid' => 8, 'ordinal' => 200]);

        $records = $this->repo->getTeamCashRecordsForSalary(8);

        $names = array_column($records, 'name');
        self::assertContains('|Cash Salary', $names);
        self::assertNotContains('Regular Player', $names);
    }

    // ── Clear all trade cash ────────────────────────────────────

    public function testClearTradeCashRemovesAllRows(): void
    {
        $offer = $this->insertTradeOfferRow();
        $this->insertTradeCashRow($offer, 'Metros', 'Sharks', ['cy1' => 100]);

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
