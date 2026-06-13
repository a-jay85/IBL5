<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration\Trading;

use PHPUnit\Framework\Attributes\Group;
use Tests\DatabaseIntegration\DatabaseTestCase;
use Trading\TradeItemType;
use Trading\TradeProcessor;

/** processTrade() commits internally — can't use DatabaseTestCase's outer-tx rollback; tearDown does manual cleanup. */
#[Group('database')]
class TradeProcessorIntegrationTest extends DatabaseTestCase
{
    private TradeProcessor $processor;

    /** @var list<int> PIDs of test-created players (for tearDown cleanup) */
    private array $createdPids = [];

    /** @var list<int> Auto-increment IDs from ibl_draft_picks (for tearDown cleanup) */
    private array $createdPickIds = [];

    /** @var list<int> Auto-increment IDs from ibl_trade_offers (for tearDown cleanup) */
    private array $createdOfferIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->db->commit();
        $_SERVER['SERVER_NAME'] = 'localhost';
        $commonRepository = new \Repositories\TeamIdentityRepository($this->db);
        $this->processor = new TradeProcessor($this->db, $commonRepository);
    }

    protected function tearDown(): void
    {
        if (!isset($this->db)) {
            parent::tearDown();
            return;
        }

        $this->db->query("DELETE FROM ibl_trade_queue");
        $this->db->query("DELETE FROM ibl_cash_considerations WHERE label LIKE 'Cash to%' OR label LIKE 'Cash from%'");
        $this->db->query("DELETE FROM nuke_stories WHERE sid > 2");

        foreach ($this->createdOfferIds as $offerId) {
            $this->db->query("DELETE FROM ibl_trade_info WHERE tradeofferid = $offerId");
            $this->db->query("DELETE FROM ibl_trade_cash WHERE trade_offer_id = $offerId");
            $this->db->query("DELETE FROM ibl_trade_offers WHERE id = $offerId");
        }

        foreach ($this->createdPickIds as $pickId) {
            $this->db->query("DELETE FROM ibl_draft_picks WHERE pickid = $pickId");
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

    // ── Scenario 1: Player-for-player ──────────────────────────

    public function testPlayerForPlayerTradeTransfersOwnership(): void
    {
        $pidA = 200040001;
        $pidB = 200040002;
        $this->seedPlayer($pidA, 'Trade Test A', 1, 'PG');
        $this->seedPlayer($pidB, 'Trade Test B', 2, 'SF');

        $offerId = $this->seedPendingTrade([
            ['id' => $pidA, 'type' => TradeItemType::Player->value, 'from' => 'Metros', 'to' => 'Stars'],
            ['id' => $pidB, 'type' => TradeItemType::Player->value, 'from' => 'Stars', 'to' => 'Metros'],
        ]);

        $result = $this->processor->processTrade($offerId);

        self::assertTrue($result['success']);
        self::assertSame(2, $this->getPlayerTeamId($pidA));
        self::assertSame(1, $this->getPlayerTeamId($pidB));
    }

    // ── Scenario 2: Player-for-pick ────────────────────────────

    public function testPlayerForPickTradeTransfersPickOwnership(): void
    {
        $pid = 200040003;
        $this->seedPlayer($pid, 'Trade Test C', 1, 'SG');
        $pickId = $this->seedPick(2, 'Stars', 2, 'Stars', 2028, 1);

        $offerId = $this->seedPendingTrade([
            ['id' => $pid, 'type' => TradeItemType::Player->value, 'from' => 'Metros', 'to' => 'Stars'],
            ['id' => $pickId, 'type' => TradeItemType::DraftPick->value, 'from' => 'Stars', 'to' => 'Metros'],
        ]);

        $result = $this->processor->processTrade($offerId);

        self::assertTrue($result['success']);
        self::assertSame(2, $this->getPlayerTeamId($pid));
        self::assertSame(1, $this->getPickOwnerTeamId($pickId));
    }

    // ── Scenario 3: Cash consideration ─────────────────────────

    public function testCashConsiderationCreatesPairedRecords(): void
    {
        $pidA = 200040004;
        $pidB = 200040005;
        $this->seedPlayer($pidA, 'Trade Test D', 1, 'C');
        $this->seedPlayer($pidB, 'Trade Test E', 2, 'PF');

        $offerId = $this->seedPendingTrade([
            ['id' => $pidA, 'type' => TradeItemType::Player->value, 'from' => 'Metros', 'to' => 'Stars'],
            ['id' => $pidB, 'type' => TradeItemType::Player->value, 'from' => 'Stars', 'to' => 'Metros'],
            ['id' => 0, 'type' => TradeItemType::Cash->value, 'from' => 'Metros', 'to' => 'Stars'],
        ]);

        $this->insertTradeCashRow($offerId, 'Metros', 'Stars', [
            'salary_yr1' => 200,
            'salary_yr2' => 200,
        ]);

        $result = $this->processor->processTrade($offerId);

        self::assertTrue($result['success']);

        $stmt = $this->db->prepare(
            "SELECT teamid, label, salary_yr1 FROM ibl_cash_considerations WHERE label LIKE 'Cash to Stars' OR label LIKE 'Cash from Metros'"
        );
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        self::assertCount(2, $rows);

        $positive = array_values(array_filter($rows, static fn (array $r): bool => $r['salary_yr1'] > 0));
        $negative = array_values(array_filter($rows, static fn (array $r): bool => $r['salary_yr1'] < 0));

        self::assertCount(1, $positive);
        self::assertCount(1, $negative);
        self::assertSame(200, $positive[0]['salary_yr1']);
        self::assertSame(-200, $negative[0]['salary_yr1']);
    }

    // ── Scenario 4: Invalid offer ID ───────────────────────────

    public function testInvalidOfferIdReturnsError(): void
    {
        $result = $this->processor->processTrade(999999);

        self::assertFalse($result['success']);
        self::assertArrayHasKey('error', $result);
    }

    // ── Scenario 5: Multi-asset trade ──────────────────────────

    public function testMultiAssetTradeTransfersAllAssets(): void
    {
        $pidA = 200040006;
        $pidB = 200040007;
        $pidC = 200040008;
        $this->seedPlayer($pidA, 'Multi Test A', 1, 'PG');
        $this->seedPlayer($pidB, 'Multi Test B', 2, 'SG');
        $this->seedPlayer($pidC, 'Multi Test C', 2, 'C');
        $pickId = $this->seedPick(1, 'Metros', 1, 'Metros', 2029, 1);

        $offerId = $this->seedPendingTrade([
            ['id' => $pidA, 'type' => TradeItemType::Player->value, 'from' => 'Metros', 'to' => 'Stars'],
            ['id' => $pickId, 'type' => TradeItemType::DraftPick->value, 'from' => 'Metros', 'to' => 'Stars'],
            ['id' => $pidB, 'type' => TradeItemType::Player->value, 'from' => 'Stars', 'to' => 'Metros'],
            ['id' => $pidC, 'type' => TradeItemType::Player->value, 'from' => 'Stars', 'to' => 'Metros'],
        ]);

        $result = $this->processor->processTrade($offerId);

        self::assertTrue($result['success']);
        self::assertSame(2, $this->getPlayerTeamId($pidA));
        self::assertSame(2, $this->getPickOwnerTeamId($pickId));
        self::assertSame(1, $this->getPlayerTeamId($pidB));
        self::assertSame(1, $this->getPlayerTeamId($pidC));
        self::assertStringContainsString('Multi Test A', $result['storytext']);
        self::assertStringContainsString('Multi Test B', $result['storytext']);
    }

    // ── Scenario 6: 3-team trade (happy path) ──────────────────

    public function testThreeTeamTradeTransfersAllPlayersAroundTheCycle(): void
    {
        // Metros(1) -> Stars(2) -> Cougars(3) -> Metros(1): a three-team cycle.
        $pidA = 200040020;
        $pidB = 200040021;
        $pidC = 200040022;
        $this->seedPlayer($pidA, 'ThreeTeam A', 1, 'PG');
        $this->seedPlayer($pidB, 'ThreeTeam B', 2, 'SF');
        $this->seedPlayer($pidC, 'ThreeTeam C', 3, 'C');

        $offerId = $this->seedPendingTrade([
            ['id' => $pidA, 'type' => TradeItemType::Player->value, 'from' => 'Metros', 'to' => 'Stars'],
            ['id' => $pidB, 'type' => TradeItemType::Player->value, 'from' => 'Stars', 'to' => 'Cougars'],
            ['id' => $pidC, 'type' => TradeItemType::Player->value, 'from' => 'Cougars', 'to' => 'Metros'],
        ]);

        $result = $this->processor->processTrade($offerId);

        self::assertTrue($result['success']);
        self::assertSame(2, $this->getPlayerTeamId($pidA));
        self::assertSame(3, $this->getPlayerTeamId($pidB));
        self::assertSame(1, $this->getPlayerTeamId($pidC));
        // 3-party trades use the joinPartyNames "A, B and C" story title.
        self::assertStringContainsString('Metros, Stars and Cougars make a trade.', $result['storytitle']);
    }

    // ── Scenario 7: 3-team atomic rollback ─────────────────────

    public function testThreeTeamTradeRollsBackAllTransfersOnMidExecutionFailure(): void
    {
        $pidA = 200040030;
        $pidB = 200040031;
        $pidC = 200040032;
        $this->seedPlayer($pidA, 'Rollback A', 1, 'PG');
        $this->seedPlayer($pidB, 'Rollback B', 2, 'SF');
        $this->seedPlayer($pidC, 'Rollback C', 3, 'C');

        $offerId = $this->seedPendingTrade([
            ['id' => $pidA, 'type' => TradeItemType::Player->value, 'from' => 'Metros', 'to' => 'Stars'],
            ['id' => $pidB, 'type' => TradeItemType::Player->value, 'from' => 'Stars', 'to' => 'Cougars'],
            ['id' => $pidC, 'type' => TradeItemType::Player->value, 'from' => 'Cougars', 'to' => 'Metros'],
        ]);

        // Asset repository that performs the FIRST real player transfer (committed
        // to the open transaction) then throws on the SECOND — a genuine
        // mid-execution failure. ibl_plr.teamid carries no FK, so a bad-team UPDATE
        // would not throw on its own; this injected double is the deterministic,
        // schema-independent trigger. Anonymous class kept inline (typed helper
        // returns would erase the subclass type for PHPStan).
        $throwingAsset = new class ($this->db) extends \Trading\TradeAssetRepository {
            public int $playerUpdateCalls = 0;

            public function updatePlayerTeam(int $playerId, int $teamId): int
            {
                $this->playerUpdateCalls++;
                if ($this->playerUpdateCalls >= 2) {
                    throw new \RuntimeException('forced mid-trade failure');
                }
                return parent::updatePlayerTeam($playerId, $teamId);
            }
        };

        $commonRepository = new \Repositories\TeamIdentityRepository($this->db);
        $processor = new TradeProcessor($this->db, $commonRepository, '', null, $throwingAsset);

        $threw = false;
        try {
            $processor->processTrade($offerId);
        } catch (\RuntimeException $e) {
            $threw = true;
            self::assertStringContainsString('forced mid-trade failure', $e->getMessage());
        }

        self::assertTrue($threw, 'processTrade should rethrow the mid-execution failure');

        // Every player must remain on its ORIGINAL team — the first (real) transfer
        // was rolled back along with everything else.
        self::assertSame(1, $this->getPlayerTeamId($pidA));
        self::assertSame(2, $this->getPlayerTeamId($pidB));
        self::assertSame(3, $this->getPlayerTeamId($pidC));

        // The offer itself must survive (not marked completed / deleted).
        self::assertNotSame([], $this->offerRowsFor($offerId));
    }

    // ── Helpers ─────────────────────────────────────────────────

    /** @return list<array<string, mixed>> */
    private function offerRowsFor(int $offerId): array
    {
        $stmt = $this->db->prepare("SELECT itemid FROM ibl_trade_info WHERE tradeofferid = ?");
        self::assertNotFalse($stmt);
        $stmt->bind_param('i', $offerId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    private function seedPlayer(int $pid, string $name, int $teamId, string $pos): void
    {
        $this->insertTestPlayer($pid, $name, [
            'teamid' => $teamId,
            'pos' => $pos,
            'cy' => 1,
            'cyt' => 3,
            'salary_yr1' => 1500,
            'salary_yr2' => 1600,
        ]);
        $this->createdPids[] = $pid;
    }

    private function seedPick(int $ownerTid, string $ownerName, int $teampickTid, string $teampickName, int $year, int $round): int
    {
        $id = $this->insertDraftPickRow($ownerTid, $teampickTid, $year, $round, [
            'ownerofpick' => $ownerName,
            'teampick' => $teampickName,
        ]);
        $this->createdPickIds[] = $id;
        return $id;
    }

    /** @param list<array{id: int, type: string, from: string, to: string}> $items */
    private function seedPendingTrade(array $items): int
    {
        $offerId = $this->insertTradeOfferRow();
        $this->createdOfferIds[] = $offerId;

        foreach ($items as $item) {
            $this->insertTradeInfoRow($offerId, $item['id'], $item['type'], $item['from'], $item['to']);
        }

        return $offerId;
    }

    private function getPlayerTeamId(int $pid): int
    {
        $stmt = $this->db->prepare("SELECT teamid FROM ibl_plr WHERE pid = ?");
        self::assertNotFalse($stmt);
        $stmt->bind_param('i', $pid);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row !== null ? (int) $row['teamid'] : -1;
    }

    private function getPickOwnerTeamId(int $pickId): int
    {
        $stmt = $this->db->prepare("SELECT owner_teamid FROM ibl_draft_picks WHERE pickid = ?");
        self::assertNotFalse($stmt);
        $stmt->bind_param('i', $pickId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row !== null ? (int) $row['owner_teamid'] : -1;
    }
}
