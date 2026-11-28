<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions\Profile;

use PHPUnit\Framework\TestCase;
use Tests\TradeSuggestions\Fixtures\ProjectionFactory;
use TradeSuggestions\Profile\PositionStrength;
use TradeSuggestions\Profile\SlotRankTable;
use TradeSuggestions\Profile\SlotStrength;

final class PositionStrengthTest extends TestCase
{
    public function testStrongAtRankEightAndNotAtNine(): void
    {
        self::assertTrue(self::strength(8)->isStrong());
        self::assertFalse(self::strength(9)->isStrong());
    }

    public function testWeakAtRankTwentyOneAndNotAtTwenty(): void
    {
        self::assertTrue(self::strength(21)->isWeak());
        self::assertFalse(self::strength(20)->isWeak());
    }

    public function testEmptySlotIsWeakWithZeroDepth(): void
    {
        $lineups = [1 => ProjectionFactory::lineupFor(1, 0, [ProjectionFactory::player(dcMinutes: 30)])];
        $table = SlotRankTable::build($lineups, ProjectionFactory::ratingsOf($lineups));

        $center = PositionStrength::build(1, $lineups[1], $table)['C'];

        self::assertNull($center->starterRank);
        self::assertTrue($center->isWeak());
        self::assertFalse($center->isStrong());
        self::assertSame(0, $center->playableDepth);
    }

    public function testPlayableDepthCountsFifteenMinutesOrRotationRole(): void
    {
        // No reliable chart, so the weight is minutes per game from the production line.
        $lineups = [1 => ProjectionFactory::lineupFor(1, 0, [
            ProjectionFactory::player(pid: 1, dcMinutes: 30),
            ProjectionFactory::player(pid: 2, dcMinutes: 20, production: ProjectionFactory::productionLine(['stats_min' => 560])),
            ProjectionFactory::player(pid: 3, dcMinutes: 5, production: ProjectionFactory::productionLine(['stats_min' => 560])),
            ProjectionFactory::player(pid: 4, dcMinutes: 5, production: ProjectionFactory::productionLine(['stats_min' => 640])),
        ])];
        $table = SlotRankTable::build($lineups, ProjectionFactory::ratingsOf($lineups));

        self::assertSame(14.0, $lineups[1]->minutesWeight[3]);
        self::assertSame(3, PositionStrength::build(1, $lineups[1], $table)['PG']->playableDepth);
    }

    public function testMultiPositionFlagRequiresTwoLegalSlots(): void
    {
        $lineups = [
            1 => ProjectionFactory::lineupFor(1, 0, [ProjectionFactory::player(pid: 1, pos: 'G', dcMinutes: 30)]),
            2 => ProjectionFactory::lineupFor(2, 0, [ProjectionFactory::player(pid: 2, tid: 2, pos: 'C', dcMinutes: 30)]),
        ];
        $table = SlotRankTable::build($lineups, ProjectionFactory::ratingsOf($lineups));
        $league = PositionStrength::buildLeague($lineups, $table);

        self::assertTrue($league[1]['PG']->multiPosition);
        self::assertFalse($league[2]['C']->multiPosition);
    }

    public function testBuriedTalentDetectedBehindBetterStarter(): void
    {
        $league = self::buriedLeague(55.0);

        self::assertSame([100], $league[3]['PG']->buriedPids);
        self::assertSame([], $league[1]['PG']->buriedPids);
    }

    public function testBuriedTalentEmptyWhenBenchWouldNotStartAnywhere(): void
    {
        $league = self::buriedLeague(30.0);

        self::assertSame([], $league[3]['PG']->buriedPids);
    }

    public function testPlayableDepthCountsExactlyFifteenMinutesPerGame(): void
    {
        // stats_min 600 over 40 games is exactly 15.0; dcMinutes 5 keeps him out of the rotation.
        $lineups = [1 => ProjectionFactory::lineupFor(1, 0, [
            ProjectionFactory::player(pid: 1, dcMinutes: 30),
            ProjectionFactory::player(pid: 2, dcMinutes: 5, production: ProjectionFactory::productionLine(['stats_min' => 600])),
        ])];
        $table = SlotRankTable::build($lineups, ProjectionFactory::ratingsOf($lineups));

        self::assertSame(15.0, $lineups[1]->minutesWeight[2]);
        self::assertSame(2, PositionStrength::build(1, $lineups[1], $table)['PG']->playableDepth);
    }

    public function testNoBuriedTalentWhenStarterHasNoPoolRank(): void
    {
        $table = self::buriedTable();
        // Starter 500 is unknown to the table; bench 100 is in the pool and would start somewhere.
        $lineup = ProjectionFactory::lineupFor(9, 0, [
            ProjectionFactory::player(pid: 500, tid: 9, dcMinutes: 30),
            ProjectionFactory::player(pid: 100, tid: 9, dcMinutes: 20),
        ]);

        self::assertSame(500, $lineup->starterPid('PG'));
        self::assertSame([100], $lineup->benchPids('PG'));
        self::assertSame(2, $table->poolRank('PG', 100));
        self::assertSame([], PositionStrength::build(9, $lineup, $table)['PG']->buriedPids);
    }

    public function testBuriedTalentIncludesBenchPlayerRankedExactlyAtWorstStarter(): void
    {
        $table = self::buriedTable();
        // Pid 1 is the league's worst starter (pool rank 4); pid 3 is its best (rank 1).
        $lineup = ProjectionFactory::lineupFor(9, 0, [
            ProjectionFactory::player(pid: 3, tid: 9, dcMinutes: 30),
            ProjectionFactory::player(pid: 1, tid: 9, dcMinutes: 20),
        ]);

        self::assertSame(4, $table->worstStarterPoolRank('PG'));
        self::assertSame(4, $table->poolRank('PG', 1));
        self::assertSame([1], PositionStrength::build(9, $lineup, $table)['PG']->buriedPids);
    }

    /**
     * Pool ranks at PG: pid 3 = 1, pid 100 = 2, pid 2 = 3, pid 1 = 4.
     */
    private static function buriedTable(): SlotRankTable
    {
        $lineups = ProjectionFactory::leagueOf(3, static function (int $tid): array {
            $players = [ProjectionFactory::player(pid: $tid, tid: $tid, dcMinutes: 30, ratings: ProjectionFactory::ratingSet([], 30.0 + 10 * $tid))];
            if ($tid === 3) {
                $players[] = ProjectionFactory::player(pid: 100, tid: 3, dcMinutes: 20, ratings: ProjectionFactory::ratingSet([], 55.0));
            }

            return $players;
        });

        return SlotRankTable::build($lineups, ProjectionFactory::ratingsOf($lineups));
    }

    private static function strength(?int $starterRank): SlotStrength
    {
        return new SlotStrength('PG', $starterRank, null, 1, false, []);
    }

    /**
     * Starters rated 40, 50, 60 on tids 1-3; tid 3 also has a bench PG rated `$benchRating`.
     *
     * @return array<int, array<string, SlotStrength>>
     */
    private static function buriedLeague(float $benchRating): array
    {
        $lineups = ProjectionFactory::leagueOf(3, static function (int $tid) use ($benchRating): array {
            $players = [ProjectionFactory::player(pid: $tid, tid: $tid, dcMinutes: 30, ratings: ProjectionFactory::ratingSet([], 30.0 + 10 * $tid))];
            if ($tid === 3) {
                $players[] = ProjectionFactory::player(pid: 100, tid: 3, dcMinutes: 20, ratings: ProjectionFactory::ratingSet([], $benchRating));
            }

            return $players;
        });
        $table = SlotRankTable::build($lineups, ProjectionFactory::ratingsOf($lineups));

        return PositionStrength::buildLeague($lineups, $table);
    }
}
