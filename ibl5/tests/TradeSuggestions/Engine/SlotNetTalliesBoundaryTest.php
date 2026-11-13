<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions\Engine;

use PHPUnit\Framework\TestCase;
use TradeSuggestions\Engine\PairRecordMemo;
use TradeSuggestions\Engine\SlotNetTallies;
use TradeSuggestions\Profile\SeasonLineup;
use TradeSuggestions\Projection\RatingSet;
use TradeSuggestions\SuggestionPlayer;
use TradeSuggestions\TeamDepth;

/**
 * `SlotNetTallies::build` and `bestBench` on hand-built lineups. A "strong" set
 * beats the zero set on `td` and `dd`, so strong vs zero is +2 at every slot.
 */
final class SlotNetTalliesBoundaryTest extends TestCase
{
    public function testStarterWithoutRatingsIsLeftOutAndPairsAreTallied(): void
    {
        $lineups = [
            1 => self::lineup(1, 1, [1]),
            2 => self::lineup(2, 2, [2]),
            3 => self::lineup(3, 3, [3]),
        ];
        $ratings = [1 => self::strong(), 2 => self::zero()];

        $tallies = SlotNetTallies::build(0, $lineups, $ratings, new PairRecordMemo());

        $empty = ['PG' => [1 => 2, 2 => -2], 'SG' => [], 'SF' => [], 'PF' => [], 'C' => []];
        self::assertSame(
            [
                'offset' => 0,
                'pool' => $empty,
                'starters' => $empty,
                'bench' => ['PG' => [], 'SG' => [], 'SF' => [], 'PF' => [], 'C' => []],
            ],
            $tallies->toArray(),
        );
    }

    public function testAGroupOfOnePidStartsAtZero(): void
    {
        $lineups = [1 => self::lineup(1, 5, [5, 6])];
        $ratings = [5 => self::strong(), 6 => self::zero()];

        $tallies = SlotNetTallies::build(0, $lineups, $ratings, new PairRecordMemo())->toArray();

        self::assertSame([5 => 2, 6 => -2], $tallies['pool']['PG']);
        self::assertSame([5 => 0], $tallies['starters']['PG']);
        self::assertSame([6 => 0], $tallies['bench']['PG']);
    }

    public function testBestBenchKeepsTheFirstOfTwoEqualRanks(): void
    {
        $lineup = self::lineup(1, null, [7, 8]);

        self::assertSame(7, SlotNetTallies::bestBench($lineup, 'PG', [7 => 3, 8 => 3]));
        self::assertSame(8, SlotNetTallies::bestBench($lineup, 'PG', [7 => 4, 8 => 3]));
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

    private static function zero(): RatingSet
    {
        return RatingSet::fromRow([]);
    }

    private static function strong(): RatingSet
    {
        return RatingSet::fromRow(['td' => 90, 'dd' => 90]);
    }
}
