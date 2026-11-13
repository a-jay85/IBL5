<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions\Engine;

use PHPUnit\Framework\TestCase;
use TradeSuggestions\Engine\SnapshotCacheKey;
use TradeSuggestions\TeamMode;

final class SnapshotCacheKeyTest extends TestCase
{
    private const array MODES = [1 => TeamMode::WinNow, 5 => TeamMode::WinNow, 9 => TeamMode::Rebuild];

    /**
     * @param array<int, TeamMode> $modes
     */
    private static function key(
        string $sim = 'Regular Season:4',
        string $phase = 'Regular Season',
        string $digest = 'abc',
        string $trade = '2026-11-01 10:00:00',
        array $modes = self::MODES,
    ): string {
        return SnapshotCacheKey::build($sim, $phase, $digest, $trade, $modes);
    }

    public function testKeyChangesWhenRosterDigestChanges(): void
    {
        self::assertNotSame(self::key(), self::key(digest: 'abd'));
    }

    public function testKeyChangesWhenTradeMarkerChanges(): void
    {
        self::assertNotSame(self::key(), self::key(trade: '2026-11-01 10:00:01'));
    }

    public function testKeyChangesWhenOneTeamModeChanges(): void
    {
        $modes = self::MODES;
        $modes[5] = TeamMode::Middle;

        self::assertNotSame(self::key(), self::key(modes: $modes));
    }

    public function testKeyChangesWhenSimMarkerChanges(): void
    {
        self::assertNotSame(self::key(), self::key(sim: 'Regular Season:5'));
    }

    public function testKeyChangesWhenPhaseChanges(): void
    {
        self::assertNotSame(self::key(), self::key(phase: 'Playoffs'));
    }

    public function testKeyIsOrderIndependentOverModes(): void
    {
        $reordered = [9 => TeamMode::Rebuild, 1 => TeamMode::WinNow, 5 => TeamMode::WinNow];

        self::assertSame(self::key(), self::key(modes: $reordered));
    }

    public function testKeyStartsWithPrefix(): void
    {
        self::assertStringStartsWith(SnapshotCacheKey::PREFIX, self::key());
        self::assertSame(strlen(SnapshotCacheKey::PREFIX) + 40, strlen(self::key()));
    }

    public function testKeyIsShaOfPipeJoinedComponentsWithTidEqualsModePairs(): void
    {
        $expected = SnapshotCacheKey::PREFIX
            . sha1('Regular Season:4|Regular Season|abc|2026-11-01 10:00:00|1=WinNow,5=WinNow,9=Rebuild');

        self::assertSame($expected, self::key());
    }
}
