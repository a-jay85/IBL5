<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions\Engine;

use PHPUnit\Framework\TestCase;
use Tests\TradeSuggestions\Engine\Fixtures\SnapshotFactory;
use TradeSuggestions\Engine\LeagueSnapshot;
use TradeSuggestions\Engine\NeedPair;
use TradeSuggestions\Engine\SurplusDeficitMatcher;
use TradeSuggestions\Profile\SeasonLineup;

final class SurplusDeficitMatcherEdgeTest extends TestCase
{
    private static ?LeagueSnapshot $league = null;

    private static function league(): LeagueSnapshot
    {
        return self::$league ??= SnapshotFactory::league(4);
    }

    public function testZeroLimitReturnsBeforeReadingAnyDimension(): void
    {
        $s = self::league();
        $pair = new NeedPair(0, NeedPair::KIND_DIMENSION, 'not_a_dimension', 1, 2, 3, 24, 1.0);

        self::assertSame([], SurplusDeficitMatcher::assetsFor($s, 1, $pair, 0));
        try {
            SurplusDeficitMatcher::assetsFor($s, 1, $pair, 1);
            self::fail('guard: a positive limit reads the dimension and throws');
        } catch (\InvalidArgumentException $e) {
            self::assertStringContainsString('not_a_dimension', $e->getMessage());
        }
    }

    public function testStarterMissingFromTheRosterIsNotListed(): void
    {
        $s = self::league();
        $lineup = $s->lineups[0][1];
        $starter = $lineup->starterPid('PG');
        self::assertNotNull($starter);
        $players = $lineup->players;
        unset($players[$starter]);
        $trimmed = new SeasonLineup($lineup->tid, $lineup->offset, $lineup->depth, $lineup->rotation, $players, $lineup->chartReliable, $lineup->minutesWeight);
        $lineups = $s->lineups;
        $lineups[0][1] = $trimmed;
        $snapshot = new LeagueSnapshot(...[...get_object_vars($s), 'lineups' => $lineups]);

        $rest = array_values(array_filter($lineup->rotation['PG'], static fn (int $pid): bool => $pid !== $starter));
        $ranks = $s->slotRanks[0];
        usort($rest, static fn (int $a, int $b): int => [$ranks->poolRank('PG', $a) ?? PHP_INT_MAX, $a] <=> [$ranks->poolRank('PG', $b) ?? PHP_INT_MAX, $b]);
        self::assertNotSame([], $rest);

        $pair = new NeedPair(0, NeedPair::KIND_SLOT, 'PG', 1, 2, 3, 25, 1.0);

        self::assertSame($rest, SurplusDeficitMatcher::assetsFor($snapshot, 1, $pair, 50));
    }
}
