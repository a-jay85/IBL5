<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions\Engine;

use PHPUnit\Framework\TestCase;
use TradeSuggestions\Engine\PairRecordMemo;
use TradeSuggestions\Engine\SlotNetTallies;
use TradeSuggestions\Engine\SlotRankState;
use TradeSuggestions\Profile\SeasonLineup;
use TradeSuggestions\Projection\RatingSet;
use TradeSuggestions\SuggestionPlayer;
use TradeSuggestions\TeamDepth;

/**
 * `SlotRankState` on hand-built tallies and lineups.
 */
final class SlotRankStateBoundaryTest extends TestCase
{
    public function testStarterMissingFromThePoolGivesAWorstRankOfZero(): void
    {
        $tallies = SlotNetTallies::fromArray(['offset' => 0, 'pool' => [], 'starters' => ['PG' => [1 => 0]], 'bench' => []]);

        $state = SlotRankState::fromTallies($tallies, [1 => self::lineup(1, 1, [1])]);

        self::assertSame(1, $state->starterRank('PG', 1));
        self::assertSame(0, $state->worstStarterPoolRank('PG'));
        self::assertNull($state->worstStarterPoolRank('SG'));
    }

    public function testBestBenchRankIsTheFirstRotationBenchPidInTheBenchGroup(): void
    {
        // Bench ranks: pid 12 is rank 1 (net 10), pid 11 is rank 2 (net 0). Pid 11 comes first in the rotation.
        $tallies = SlotNetTallies::fromArray(['offset' => 0, 'pool' => [], 'starters' => [], 'bench' => ['PG' => [11 => 0, 12 => 10]]]);

        $state = SlotRankState::fromTallies($tallies, [1 => self::lineup(1, null, [11, 12])]);

        self::assertSame(2, $state->bestBenchRank('PG', 1));
    }

    public function testStoredPoolNetsAreKeptWhenMembershipIsUnchanged(): void
    {
        // Stored pool nets (7, 3) differ from what a recount would give (2, -2).
        $tallies = SlotNetTallies::fromArray([
            'offset' => 0,
            'pool' => ['PG' => [1 => 7, 2 => 3]],
            'starters' => ['PG' => [1 => 0, 2 => 0]],
            'bench' => [],
        ]);
        $lineups = [1 => self::lineup(1, 1, [1]), 2 => self::lineup(2, 2, [2])];
        $ratings = [1 => RatingSet::fromRow(['td' => 90, 'dd' => 90]), 2 => RatingSet::fromRow([])];

        $after = SlotRankState::fromTallies($tallies, $lineups)->withLineups([], $ratings, new PairRecordMemo());

        $empty = ['PG' => [], 'SG' => [], 'SF' => [], 'PF' => [], 'C' => []];
        self::assertSame(
            [
                'offset' => 0,
                'pool' => ['PG' => [1 => 7, 2 => 3], 'SG' => [], 'SF' => [], 'PF' => [], 'C' => []],
                'starters' => ['PG' => [1 => 0, 2 => 0], 'SG' => [], 'SF' => [], 'PF' => [], 'C' => []],
                'bench' => $empty,
            ],
            $after->tallies()->toArray(),
        );
    }

    /**
     * @param list<int> $pgRotation
     */
    private static function lineup(int $tid, ?int $pgStarter, array $pgRotation): SeasonLineup
    {
        $starter = $pgStarter === null ? null : new SuggestionPlayer($pgStarter, 'P' . $pgStarter, 'PG', 25, false, 0, 1, 0);
        $depth = new TeamDepth(['PG' => $starter, 'SG' => null, 'SF' => null, 'PF' => null, 'C' => null], true);

        return new SeasonLineup($tid, 0, $depth, ['PG' => $pgRotation], [], true, []);
    }
}
