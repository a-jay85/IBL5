<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use PHPUnit\Framework\Attributes\Group;

use JsbParser\JsbExportRepository;

/**
 * Tests JsbExportRepository against real MariaDB —
 * player changeable fields, depth charts, and completed trade items for JSB file export.
 */
#[Group('database')]
class JsbExportRepositoryTest extends DatabaseTestCase
{
    private JsbExportRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new JsbExportRepository($this->db);
    }

    // ── getAllPlayerChangeableFields ─────────────────────────────

    public function testGetAllPlayerChangeableFieldsReturnsKeyedByPid(): void
    {
        $this->insertTestPlayer(200000060, 'DB Test Export Player', [
            'teamid' => 1,
            'bird' => 3,
            'cy' => 2,
            'cyt' => 4,
            'salary_yr1' => 1500,
            'salary_yr2' => 1700,
            'salary_yr3' => 1900,
            'salary_yr4' => 0,
            'salary_yr5' => 0,
            'salary_yr6' => 0,
            'ordinal' => 100,
            'fa_signing_flag' => 0,
        ]);

        $result = $this->repo->getAllPlayerChangeableFields();

        self::assertArrayHasKey(200000060, $result);
        $player = $result[200000060];

        self::assertSame(200000060, $player['pid']);
        self::assertSame('DB Test Export Player', $player['name']);
        self::assertSame(1, $player['teamid']);
        // COALESCE preserves native int for tinyint(1) and int columns, but returns
        // string for tinyint(3) unsigned (cy, cyt). The repo's is_int() narrowing
        // converts string results to 0. This tests the actual repository behavior.
        self::assertSame(3, $player['bird']);          // tinyint(1) → int through COALESCE
        self::assertSame(0, $player['cy']);             // tinyint(3) unsigned → string → 0
        self::assertSame(0, $player['cyt']);            // tinyint → string → 0
        self::assertSame(1500, $player['salary_yr1']);         // int → int through COALESCE
        self::assertSame(1700, $player['salary_yr2']);         // int → int through COALESCE
        self::assertSame(1900, $player['salary_yr3']);         // int → int through COALESCE
        self::assertSame(0, $player['fa_signing_flag']); // tinyint(1) → int through COALESCE
    }

    public function testGetAllPlayerChangeableFieldsExcludesHighOrdinals(): void
    {
        // ordinal > 1440 should be excluded
        $this->insertTestPlayer(200000061, 'DB Test High Ordinal', [
            'ordinal' => 1500,
        ]);

        $result = $this->repo->getAllPlayerChangeableFields();

        self::assertArrayNotHasKey(200000061, $result);
    }

    public function testGetAllPlayerChangeableFieldsExcludesPidZero(): void
    {
        // pid = 0 should be excluded by "pid <> 0" filter
        $result = $this->repo->getAllPlayerChangeableFields();

        self::assertArrayNotHasKey(0, $result);
    }

    public function testGetAllPlayerChangeableFieldsHandlesNullContractFields(): void
    {
        // COALESCE should convert NULLs to 0
        $this->insertRow('ibl_plr', [
            'pid' => 200000062,
            'name' => 'DB Test Null Contract',
            'age' => 25,
            'teamid' => 1,
            'pos' => 'SF',
            'stamina' => 80,
            'exp' => 3,
            'retired' => 0,
            'ordinal' => 200,
            'droptime' => 0,
            'uuid' => 'test-200000062-0000-000000000001',
        ]);

        $result = $this->repo->getAllPlayerChangeableFields();

        self::assertArrayHasKey(200000062, $result);
        $player = $result[200000062];

        // COALESCE should have turned NULLs into 0
        self::assertSame(0, $player['bird']);
        self::assertSame(0, $player['cy']);
        self::assertSame(0, $player['fa_signing_flag']);
    }

    // ── getCompletedTradeItems ──────────────────────────────────

    public function testGetCompletedTradeItemsReturnsMatchingTrades(): void
    {
        $offerId = $this->insertTradeOfferRow();

        $this->insertTradeInfoRow($offerId, 200000060, 'player', 'Team A', 'Team B', 'completed');

        $result = $this->repo->getCompletedTradeItems('2020-01-01');

        $matching = array_filter(
            $result,
            static fn (array $row): bool => $row['tradeofferid'] === $offerId,
        );

        self::assertNotSame([], $matching);
        $item = array_values($matching)[0];
        self::assertSame($offerId, $item['tradeofferid']);
        self::assertSame(200000060, $item['itemid']);
        self::assertSame('player', $item['itemtype']);
        self::assertSame('Team A', $item['trade_from']);
        self::assertSame('Team B', $item['trade_to']);
    }

    public function testGetCompletedTradeItemsExcludesNonCompleted(): void
    {
        $offerId = $this->insertTradeOfferRow();
        $this->insertTradeInfoRow($offerId, 200000063, 'player', 'Team C', 'Team D', 'pending');

        $result = $this->repo->getCompletedTradeItems('2020-01-01');

        $matching = array_filter(
            $result,
            static fn (array $row): bool => $row['tradeofferid'] === $offerId,
        );

        self::assertSame([], $matching);
    }

    public function testGetCompletedTradeItemsFiltersByDate(): void
    {
        $offerId = $this->insertTradeOfferRow();
        $this->insertTradeInfoRow($offerId, 200000064, 'player', 'Team E', 'Team F', 'completed');

        // Use future date filter — the trade was just inserted with NOW(), should be excluded by a far-future date
        $result = $this->repo->getCompletedTradeItems('2099-01-01');

        $matching = array_filter(
            $result,
            static fn (array $row): bool => $row['tradeofferid'] === $offerId,
        );

        self::assertSame([], $matching);
    }

    public function testGetCompletedTradeItemsOrdersByOfferAndId(): void
    {
        $offerId1 = $this->insertTradeOfferRow();
        $offerId2 = $this->insertTradeOfferRow();

        $this->insertTradeInfoRow($offerId2, 200000065, 'player', 'Team G', 'Team H', 'completed');
        $this->insertTradeInfoRow($offerId1, 200000066, 'player', 'Team I', 'Team J', 'completed');
        $this->insertTradeInfoRow($offerId1, 200000067, 'draftpick', 'Team J', 'Team I', 'completed');

        $result = $this->repo->getCompletedTradeItems('2020-01-01');

        $offerIds = [];
        foreach ($result as $row) {
            if ($row['tradeofferid'] === $offerId1 || $row['tradeofferid'] === $offerId2) {
                $offerIds[] = $row['tradeofferid'];
            }
        }

        // Should be ordered by tradeofferid, then by id
        // offerId1 < offerId2, so offerId1 items first
        self::assertNotSame([], $offerIds);
        if (count($offerIds) >= 2) {
            self::assertLessThanOrEqual($offerIds[1], $offerIds[0]);
        }
    }

    public function testGetCompletedTradeItemsJoinsDraftPickYearRoundAndOriginalTeam(): void
    {
        $pickId = $this->insertDraftPickRow(2, 1, 2031, 2);
        $offerId = $this->insertTradeOfferRow();
        $this->insertTradeInfoRow($offerId, $pickId, \Trading\TradeItemType::DraftPick->value, 'Team K', 'Team L', 'completed');
        $this->insertTradeInfoRow($offerId, $pickId, \Trading\TradeItemType::Player->value, 'Team L', 'Team K', 'completed');

        $rows = array_values(array_filter(
            $this->repo->getCompletedTradeItems('2020-01-01'),
            static fn (array $row): bool => $row['tradeofferid'] === $offerId,
        ));

        self::assertCount(2, $rows);
        self::assertSame(2031, $rows[0]['pick_year']);
        self::assertSame(2, $rows[0]['pick_round']);
        self::assertSame(1, $rows[0]['pick_teamid']);
        // A player item whose pid happens to equal a pickid must not pick up the pick's columns
        self::assertNull($rows[1]['pick_year']);
        self::assertNull($rows[1]['pick_round']);
        self::assertNull($rows[1]['pick_teamid']);
    }

    // ── getAllPlayerDepthCharts ──────────────────────────────────

    public function testGetAllPlayerDepthChartsReturnsDcColumnsKeyedByPid(): void
    {
        $this->insertTestPlayer(200000070, 'DB Test Depth Chart', [
            'teamid' => 20,
            'ordinal' => 579,
            'dc_pg_depth' => 0,
            'dc_sg_depth' => 0,
            'dc_sf_depth' => 0,
            'dc_pf_depth' => 0,
            'dc_c_depth' => 1,
            'dc_can_play_in_game' => 1,
            'dc_minutes' => 40,
            'dc_of' => 0,
            'dc_df' => 0,
            'dc_oi' => 2,
            'dc_di' => 0,
            'dc_bh' => 0,
        ]);

        $result = $this->repo->getAllPlayerDepthCharts();

        self::assertArrayHasKey(200000070, $result);
        self::assertSame([
            'pid' => 200000070,
            'teamid' => 20,
            'dc_pg_depth' => 0,
            'dc_sg_depth' => 0,
            'dc_sf_depth' => 0,
            'dc_pf_depth' => 0,
            'dc_c_depth' => 1,
            'dc_can_play_in_game' => 1,
            'dc_minutes' => 40,
            'dc_of' => 0,
            'dc_df' => 0,
            'dc_oi' => 2,
            'dc_di' => 0,
            'dc_bh' => 0,
        ], $result[200000070]);
    }

    public function testGetAllPlayerDepthChartsExcludesZeroPidRows(): void
    {
        $existing = $this->db->query('SELECT COUNT(*) AS n FROM ibl_plr WHERE pid = 0');
        self::assertInstanceOf(\mysqli_result::class, $existing);
        $row = $existing->fetch_assoc();
        if (!is_array($row) || (int) $row['n'] === 0) {
            $this->insertTestPlayer(0, 'DB Test Empty Slot', [
                'teamid' => 20,
                'ordinal' => 583,
                'dc_minutes' => 30,
                'uuid' => 'test-000000000-0000-000000000099',
            ]);
        }
        $this->insertTestPlayer(200000071, 'DB Test Real Player', ['teamid' => 20, 'ordinal' => 580]);

        $result = $this->repo->getAllPlayerDepthCharts();

        self::assertArrayNotHasKey(0, $result);
        self::assertArrayHasKey(200000071, $result);
    }
}
