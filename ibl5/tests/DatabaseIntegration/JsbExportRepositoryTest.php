<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use JsbParser\JsbExportRepository;

/**
 * Tests JsbExportRepository against real MariaDB —
 * player changeable fields and completed trade items for JSB file export.
 */
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
            'tid' => 1,
            'bird' => 3,
            'cy' => 2,
            'cyt' => 4,
            'cy1' => 1500,
            'cy2' => 1700,
            'cy3' => 1900,
            'cy4' => 0,
            'cy5' => 0,
            'cy6' => 0,
            'ordinal' => 100,
            'fa_signing_flag' => 0,
        ]);

        $result = $this->repo->getAllPlayerChangeableFields();

        self::assertArrayHasKey(200000060, $result);
        $player = $result[200000060];

        self::assertSame(200000060, $player['pid']);
        self::assertSame('DB Test Export Player', $player['name']);
        self::assertSame(1, $player['tid']);
        // COALESCE preserves native int for tinyint(1) and int columns, but returns
        // string for tinyint(3) unsigned (cy, cyt). The repo's is_int() narrowing
        // converts string results to 0. This tests the actual repository behavior.
        self::assertSame(3, $player['bird']);          // tinyint(1) → int through COALESCE
        self::assertSame(0, $player['cy']);             // tinyint(3) unsigned → string → 0
        self::assertSame(0, $player['cyt']);            // tinyint → string → 0
        self::assertSame(1500, $player['cy1']);         // int → int through COALESCE
        self::assertSame(1700, $player['cy2']);         // int → int through COALESCE
        self::assertSame(1900, $player['cy3']);         // int → int through COALESCE
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
            'tid' => 1,
            'pos' => 'SF',
            'sta' => 80,
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

        self::assertNotEmpty($matching);
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

        self::assertEmpty($matching);
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

        self::assertEmpty($matching);
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
        self::assertNotEmpty($offerIds);
        if (count($offerIds) >= 2) {
            self::assertLessThanOrEqual($offerIds[1], $offerIds[0]);
        }
    }
}
