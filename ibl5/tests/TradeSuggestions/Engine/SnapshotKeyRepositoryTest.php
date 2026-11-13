<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions\Engine;

use PHPUnit\Framework\TestCase;
use Tests\WideUnit\Mocks\MockDatabase;
use TradeSuggestions\Engine\SnapshotKeyRepository;

/**
 * The repository is final, so rows are fed through `MockDatabase` routing.
 * The real column names are pinned by `SnapshotKeyRepositoryIntegrationTest`.
 */
final class SnapshotKeyRepositoryTest extends TestCase
{
    public function testRosterDigestNullIsEmptyMarker(): void
    {
        $mockDb = new MockDatabase();
        $mockDb->onQuery('ibl_plr', [['d' => null]]);

        self::assertSame('empty', (new SnapshotKeyRepository($mockDb))->rosterDigest());
    }

    public function testTradeMarkerJoinsMaxUpdatedAtAndPickOwnershipDigest(): void
    {
        $mockDb = new MockDatabase();
        $mockDb->onQuery('ibl_trade_info', [['m' => '2026-11-01 10:00:00']]);
        $mockDb->onQuery('ibl_draft_picks', [['p' => 'pickdigest']]);
        $mockDb->onQuery('ibl_plr', [['d' => str_repeat('a', 40)]]);
        $keys = new SnapshotKeyRepository($mockDb);

        self::assertSame('2026-11-01 10:00:00|pickdigest', $keys->tradeMarker());
        self::assertSame(str_repeat('a', 40), $keys->rosterDigest());
    }

    public function testTradeMarkerIsStringFromMaxUpdatedAt(): void
    {
        $mockDb = new MockDatabase();
        $mockDb->onQuery('ibl_trade_info', [['m' => '2026-11-05 08:30:00']]);
        $mockDb->onQuery('ibl_draft_picks', [['p' => 'abc']]);

        $marker = (new SnapshotKeyRepository($mockDb))->tradeMarker();

        self::assertStringStartsWith('2026-11-05 08:30:00|', $marker);

        $emptyDb = new MockDatabase();
        $emptyDb->onQuery('ibl_trade_info', [['m' => null]]);
        $emptyDb->onQuery('ibl_draft_picks', [['p' => null]]);

        self::assertSame('|', (new SnapshotKeyRepository($emptyDb))->tradeMarker());
    }
}
