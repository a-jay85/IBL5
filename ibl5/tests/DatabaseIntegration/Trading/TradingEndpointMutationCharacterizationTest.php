<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration\Trading;

use PHPUnit\Framework\Attributes\Group;
use Tests\DatabaseIntegration\DatabaseTestCase;
use Trading\TradeOffer;

/**
 * Pin 1 (three cases): submit orchestration via createTradeOffer().
 *
 * Findings A, B, C recorded in the plan — no tests added for them.
 *
 * Pattern: createTradeOffer() commits internally; tearDown does manual FK-safe cleanup.
 */
#[Group('database')]
class TradingEndpointMutationCharacterizationTest extends DatabaseTestCase
{
    private TradeOffer $tradeOffer;

    /** @var list<int> PIDs of test-created players (for tearDown cleanup) */
    private array $createdPids = [];

    /** @var list<int> IDs from ibl_trade_offers (for tearDown cleanup) */
    private array $createdOfferIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->db->commit();  // release outer tx — createTradeOffer() inserts autocommit
        $_SERVER['SERVER_NAME'] = 'localhost';
        $teamIdentityRepo = new \Repositories\TeamIdentityRepository($this->db);
        $this->tradeOffer = new TradeOffer($this->db, $teamIdentityRepo, 'localhost');
    }

    protected function tearDown(): void
    {
        if (!isset($this->db)) {
            parent::tearDown();
            return;
        }

        // FK-safe order: cash → info → offers → players
        foreach ($this->createdOfferIds as $offerId) {
            $this->db->query("DELETE FROM ibl_trade_cash WHERE trade_offer_id = $offerId");
            $this->db->query("DELETE FROM ibl_trade_info WHERE tradeofferid = $offerId");
            $this->db->query("DELETE FROM ibl_trade_offers WHERE id = $offerId");
        }

        foreach ($this->createdPids as $pid) {
            $this->db->query("DELETE FROM ibl_plr WHERE pid = $pid");
        }

        unset($_SERVER['SERVER_NAME']);

        try {
            $this->db->close();
        } catch (\Throwable) {
        }

        parent::tearDown();
    }

    // ── Helpers ─────────────────────────────────────────────────

    private function seedPlayer(int $pid, string $name, int $teamId): void
    {
        $this->insertTestPlayer($pid, $name, [
            'teamid' => $teamId,
            'cy' => 1,
            'cyt' => 3,
            'salary_yr1' => 100,
            'salary_yr2' => 100,
        ]);
        $this->createdPids[] = $pid;
    }

    // ── Pin 1a: Player-for-player ────────────────────────────────

    public function testSubmitPlayerForPlayerWritesExactTradeInfoRows(): void
    {
        $metrosPid = 200140001;
        $starsPid  = 200140002;
        $this->seedPlayer($metrosPid, 'Char Test A', 1);
        $this->seedPlayer($starsPid, 'Char Test B', 2);

        // Balanced 1-for-1: Metros (index 0) → Stars (index 1)
        $tradeData = [
            'offeringTeam'   => 'Metros',
            'listeningTeam'  => 'Stars',
            'switchCounter'  => 1,
            'fieldsCounter'  => 2,
            'check'          => [0 => 'on', 1 => 'on'],
            'index'          => [0 => (string) $metrosPid, 1 => (string) $starsPid],
            'type'           => [0 => '1', 1 => '1'],
            'contract'       => [0 => '100', 1 => '100'],
            'userSendsCash'  => [],
            'partnerSendsCash' => [],
        ];

        $result = $this->tradeOffer->createTradeOffer($tradeData);

        self::assertTrue($result['success']);
        $offerId = $result['tradeOfferId'];
        $this->createdOfferIds[] = $offerId;

        // Exactly 2 trade_info rows for this offer
        $stmt = $this->db->prepare("SELECT itemid, itemtype, trade_from, trade_to, approval FROM ibl_trade_info WHERE tradeofferid = ?");
        self::assertNotFalse($stmt);
        $stmt->bind_param('i', $offerId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        self::assertCount(2, $rows);

        $byItem = [];
        foreach ($rows as $row) {
            $byItem[(int) $row['itemid']] = $row;
        }

        // Metros player row
        self::assertArrayHasKey($metrosPid, $byItem, 'Metros player ibl_trade_info row missing');
        self::assertSame('1',      $byItem[$metrosPid]['itemtype']);
        self::assertSame('Metros', $byItem[$metrosPid]['trade_from']);
        self::assertSame('Stars',  $byItem[$metrosPid]['trade_to']);
        self::assertSame('test',   $byItem[$metrosPid]['approval']); // guard #1: localhost rewrite

        // Stars player row
        self::assertArrayHasKey($starsPid, $byItem, 'Stars player ibl_trade_info row missing');
        self::assertSame('1',      $byItem[$starsPid]['itemtype']);
        self::assertSame('Stars',  $byItem[$starsPid]['trade_from']);
        self::assertSame('Metros', $byItem[$starsPid]['trade_to']);
        self::assertSame('test',   $byItem[$starsPid]['approval']); // guard #1

        // Negative boundary: no cash rows (cash branch not triggered)
        $stmt2 = $this->db->prepare("SELECT COUNT(*) AS cnt FROM ibl_trade_cash WHERE trade_offer_id = ?");
        self::assertNotFalse($stmt2);
        $stmt2->bind_param('i', $offerId);
        $stmt2->execute();
        $row2 = $stmt2->get_result()->fetch_assoc();
        $stmt2->close();
        self::assertSame(0, (int) ($row2['cnt'] ?? -1));
    }

    // ── Pin 1b: Submit with cash ─────────────────────────────────

    public function testSubmitWithCashWritesCashRowAndCompositeItem(): void
    {
        $metrosPid = 200140003;
        $this->seedPlayer($metrosPid, 'Char Test C', 1);

        // Metros sends player + cash; Stars sends nothing
        $tradeData = [
            'offeringTeam'   => 'Metros',
            'listeningTeam'  => 'Stars',
            'switchCounter'  => 1,
            'fieldsCounter'  => 1,
            'check'          => [0 => 'on'],
            'index'          => [0 => (string) $metrosPid],
            'type'           => [0 => '1'],
            'contract'       => [0 => '100'],
            // guard #3: 100 in both yr1+yr2 so cap passes regardless of Season mock phase
            'userSendsCash'  => [1 => 100, 2 => 100],
            'partnerSendsCash' => [],
        ];

        $result = $this->tradeOffer->createTradeOffer($tradeData);

        self::assertTrue($result['success']);
        $offerId = $result['tradeOfferId'];
        $this->createdOfferIds[] = $offerId;

        // Exactly 1 trade_cash row with correct sending/receiving/amount
        $stmt = $this->db->prepare("SELECT * FROM ibl_trade_cash WHERE trade_offer_id = ?");
        self::assertNotFalse($stmt);
        $stmt->bind_param('i', $offerId);
        $stmt->execute();
        $cashRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        self::assertCount(1, $cashRows);
        self::assertSame('Metros', $cashRows[0]['sending_team']);
        self::assertSame('Stars',  $cashRows[0]['receiving_team']);
        self::assertSame(100,      (int) $cashRows[0]['salary_yr1']);

        // Cash trade_info row: itemtype='cash', composite itemid = (int)('1'.'0'.'2'.'0') = 1020
        $expectedCashItemId = 1020; // Metros tid=1, Stars tid=2
        $stmt2 = $this->db->prepare(
            "SELECT itemid, itemtype FROM ibl_trade_info WHERE tradeofferid = ? AND itemtype = 'cash'"
        );
        self::assertNotFalse($stmt2);
        $stmt2->bind_param('i', $offerId);
        $stmt2->execute();
        $cashInfoRows = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt2->close();

        self::assertCount(1, $cashInfoRows);
        self::assertSame($expectedCashItemId, (int) $cashInfoRows[0]['itemid']);
        self::assertSame('cash',              $cashInfoRows[0]['itemtype']);

        // Player row still carries approval='test' (guard #1)
        $stmt3 = $this->db->prepare(
            "SELECT approval FROM ibl_trade_info WHERE tradeofferid = ? AND itemid = ?"
        );
        self::assertNotFalse($stmt3);
        $stmt3->bind_param('ii', $offerId, $metrosPid);
        $stmt3->execute();
        $playerRow = $stmt3->get_result()->fetch_assoc();
        $stmt3->close();

        self::assertNotNull($playerRow, 'Player ibl_trade_info row missing');
        self::assertSame('test', $playerRow['approval']); // guard #1
    }

    // ── Pin 1c: Negative — illegal cash amount ───────────────────

    public function testIllegalCashAmountWritesNoItemsButLeavesOfferShell(): void
    {
        $metrosPid = 200140004;
        $this->seedPlayer($metrosPid, 'Char Test D', 1);

        // Snapshot MAX before the call — failure path returns no tradeOfferId
        $maxRow = $this->db->query("SELECT MAX(id) AS max_id FROM ibl_trade_offers")->fetch_assoc();
        $maxOfferIdBefore = (int) ($maxRow['max_id'] ?? 0);

        // Sub-minimum cash (50 < 100) to trip validateMinimumCashAmounts — guard #2 inverted
        $tradeData = [
            'offeringTeam'   => 'Metros',
            'listeningTeam'  => 'Stars',
            'switchCounter'  => 1,
            'fieldsCounter'  => 1,
            'check'          => [0 => 'on'],
            'index'          => [0 => (string) $metrosPid],
            'type'           => [0 => '1'],
            'contract'       => [0 => '100'],
            'userSendsCash'  => [1 => 50], // illegal: < 100 minimum per season
            'partnerSendsCash' => [],
        ];

        $result = $this->tradeOffer->createTradeOffer($tradeData);

        self::assertFalse($result['success']);
        self::assertArrayHasKey('error', $result);
        self::assertStringContainsString('minimum amount of cash', $result['error']);

        // No ibl_trade_info rows for any new offer id
        $stmt = $this->db->prepare("SELECT COUNT(*) AS cnt FROM ibl_trade_info WHERE tradeofferid > ?");
        self::assertNotFalse($stmt);
        $stmt->bind_param('i', $maxOfferIdBefore);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        self::assertSame(0, (int) ($row['cnt'] ?? -1), 'No trade_info rows expected on failure path');

        // No ibl_trade_cash rows for any new offer id
        $stmt2 = $this->db->prepare("SELECT COUNT(*) AS cnt FROM ibl_trade_cash WHERE trade_offer_id > ?");
        self::assertNotFalse($stmt2);
        $stmt2->bind_param('i', $maxOfferIdBefore);
        $stmt2->execute();
        $row2 = $stmt2->get_result()->fetch_assoc();
        $stmt2->close();
        self::assertSame(0, (int) ($row2['cnt'] ?? -1), 'No trade_cash rows expected on failure path');

        // Orphan ibl_trade_offers shell exists — id generated pre-validation, not rolled back
        // (TradeOffer.php: generateTradeOfferId() on line ~98 precedes validateMinimumCashAmounts on line ~101)
        $stmt3 = $this->db->prepare("SELECT id FROM ibl_trade_offers WHERE id > ?");
        self::assertNotFalse($stmt3);
        $stmt3->bind_param('i', $maxOfferIdBefore);
        $stmt3->execute();
        $orphans = $stmt3->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt3->close();

        self::assertNotEmpty($orphans, 'Expected an orphan ibl_trade_offers shell (pre-validation insert not rolled back)');

        // Register orphan IDs for tearDown cleanup
        foreach ($orphans as $orphan) {
            $this->createdOfferIds[] = (int) $orphan['id'];
        }
    }
}
