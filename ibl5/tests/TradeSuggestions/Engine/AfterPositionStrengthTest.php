<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions\Engine;

use PHPUnit\Framework\TestCase;
use Tests\TradeSuggestions\Fixtures\ProjectionFactory;
use TradeSuggestions\Engine\AfterPositionStrength;
use TradeSuggestions\Engine\Contracts\SlotRankReaderInterface;
use TradeSuggestions\Profile\SeasonLineup;
use TradeSuggestions\SuggestionPlayer;
use TradeSuggestions\TeamDepth;

/**
 * `AfterPositionStrength::build` on hand-built lineups and a stub rank reader,
 * so each expected value comes from the inputs alone.
 */
final class AfterPositionStrengthTest extends TestCase
{
    public function testMinutesWeightOfExactlyFifteenIsPlayableAndJustBelowIsNot(): void
    {
        $lineup = self::lineup(
            [],
            [],
            [1 => 'PG', 2 => 'PG', 3 => 'PG', 4 => 'SG'],
            [1 => 15.0, 2 => 14.99, 4 => 99.0],
        );

        $out = AfterPositionStrength::build(1, $lineup, self::reader([], []));

        // PG: pid 1 (15.0) counts; pid 2 (14.99) and pid 3 (no weight) do not; pid 4 is an SG.
        self::assertSame(1, $out['PG']->playableDepth);
        self::assertSame(1, $out['SG']->playableDepth);
        self::assertSame(0, $out['SF']->playableDepth);
        self::assertSame(0, $out['PF']->playableDepth);
        self::assertSame(0, $out['C']->playableDepth);
    }

    public function testRotationMembershipMakesALowMinutesPlayerPlayable(): void
    {
        $lineup = self::lineup([], ['PG' => [1]], [1 => 'PG', 2 => 'PG'], []);

        $out = AfterPositionStrength::build(1, $lineup, self::reader([], []));

        self::assertSame(1, $out['PG']->playableDepth);
        self::assertSame(0, $out['SG']->playableDepth);
    }

    public function testMultiPositionNeedsARotationPlayerWithTwoSlots(): void
    {
        // Pid 99 is in the PG rotation but not on the roster; it comes first.
        $lineup = self::lineup(
            [],
            ['PG' => [99, 2], 'C' => [7]],
            [1 => 'PG', 2 => 'G', 7 => 'C'],
            [],
        );

        $out = AfterPositionStrength::build(1, $lineup, self::reader([], []));

        self::assertTrue($out['PG']->multiPosition);
        self::assertFalse($out['C']->multiPosition);
        self::assertFalse($out['SG']->multiPosition);
        self::assertFalse($out['SF']->multiPosition);
    }

    public function testBuriedPidsAreBenchPlayersRankedBetweenTheStarterAndTheWorstStarter(): void
    {
        $lineup = self::lineup(['PG' => 1], ['PG' => [1, 11, 12, 13, 14, 15]], [], []);
        $ranks = self::reader(['PG' => [1 => 2, 11 => 2, 12 => 3, 13 => 5, 14 => 6]], ['PG' => 5]);

        $out = AfterPositionStrength::build(1, $lineup, $ranks);

        // 11 ties the starter's rank (not buried), 12 is buried, 13 sits on the worst starter's rank (buried),
        // 14 ranks below it, 15 has no pool rank.
        self::assertSame([12, 13], $out['PG']->buriedPids);
        self::assertSame([], $out['SG']->buriedPids);
    }

    public function testNoStarterMeansNothingIsBuried(): void
    {
        $lineup = self::lineup([], ['PG' => [11]], [], []);
        $ranks = self::reader(['PG' => [11 => 3]], ['PG' => 5]);

        $out = AfterPositionStrength::build(1, $lineup, $ranks);

        self::assertSame([], $out['PG']->buriedPids);
    }

    public function testStarterWithoutAPoolRankMeansNothingIsBuried(): void
    {
        $lineup = self::lineup(['PG' => 1], ['PG' => [1, 11]], [], []);
        $ranks = self::reader(['PG' => [11 => 3]], ['PG' => 5]);

        $out = AfterPositionStrength::build(1, $lineup, $ranks);

        self::assertSame([], $out['PG']->buriedPids);
    }

    /**
     * @param array<string, int> $starters slot => starter pid
     * @param array<string, list<int>> $rotation
     * @param array<int, string> $positions pid => pos of every rostered player
     * @param array<int, float> $minutes pid => minutes weight
     */
    private static function lineup(array $starters, array $rotation, array $positions, array $minutes): SeasonLineup
    {
        $depth = [];
        foreach (['PG', 'SG', 'SF', 'PF', 'C'] as $slot) {
            $pid = $starters[$slot] ?? null;
            $depth[$slot] = $pid === null ? null : new SuggestionPlayer($pid, 'P' . $pid, 'PG', 25, false, 0, 1, 0);
        }
        $players = [];
        foreach ($positions as $pid => $pos) {
            $players[$pid] = ProjectionFactory::player(pid: $pid, pos: $pos);
        }

        return new SeasonLineup(1, 0, new TeamDepth($depth, true), $rotation, $players, true, $minutes);
    }

    /**
     * @param array<string, array<int, int>> $pool slot => pid => pool rank
     * @param array<string, int> $worst slot => worst starter pool rank
     */
    private static function reader(array $pool, array $worst): SlotRankReaderInterface
    {
        return new class ($pool, $worst) implements SlotRankReaderInterface {
            /**
             * @param array<string, array<int, int>> $pool
             * @param array<string, int> $worst
             */
            public function __construct(private readonly array $pool, private readonly array $worst)
            {
            }

            public function poolRank(string $slot, int $pid): ?int
            {
                return $this->pool[$slot][$pid] ?? null;
            }

            public function starterRank(string $slot, int $tid): ?int
            {
                return null;
            }

            public function bestBenchRank(string $slot, int $tid): ?int
            {
                return null;
            }

            public function worstStarterPoolRank(string $slot): ?int
            {
                return $this->worst[$slot] ?? null;
            }
        };
    }
}
