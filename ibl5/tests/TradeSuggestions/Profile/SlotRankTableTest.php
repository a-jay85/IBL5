<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions\Profile;

use PHPUnit\Framework\TestCase;
use Tests\TradeSuggestions\Fixtures\ProjectionFactory;
use TradeSuggestions\Profile\SlotRankTable;

final class SlotRankTableTest extends TestCase
{
    public function testStarterRankIsOrdinalOneToTwentyEight(): void
    {
        $lineups = self::orderedLeague();
        $table = SlotRankTable::build($lineups, ProjectionFactory::ratingsOf($lineups));

        $ranks = [];
        for ($tid = 1; $tid <= 28; $tid++) {
            $ranks[$tid] = $table->starterRank('PG', $tid);
        }
        $sorted = $ranks;
        sort($sorted);

        self::assertSame(range(1, 28), $sorted);
        self::assertSame(28, $ranks[1]);
        self::assertSame(1, $ranks[28]);
    }

    public function testDominantPlayerRanksFirst(): void
    {
        $lineups = ProjectionFactory::leagueOf(4, static fn (int $tid): array => [
            ProjectionFactory::player(
                pid: $tid,
                tid: $tid,
                dcMinutes: 30,
                ratings: ProjectionFactory::ratingSet([], $tid === 3 ? 95.0 : 50.0)
            ),
        ]);
        $table = SlotRankTable::build($lineups, ProjectionFactory::ratingsOf($lineups));

        self::assertSame(1, $table->starterRank('PG', 3));
        self::assertSame(1, $table->poolRank('PG', 3));
    }

    public function testTieInDominanceBreaksByPidAscending(): void
    {
        $lineups = [
            1 => ProjectionFactory::lineupFor(1, 0, [ProjectionFactory::player(pid: 50, dcMinutes: 30)]),
            2 => ProjectionFactory::lineupFor(2, 0, [ProjectionFactory::player(pid: 40, tid: 2, dcMinutes: 30)]),
        ];
        $table = SlotRankTable::build($lineups, ProjectionFactory::ratingsOf($lineups));

        self::assertSame(1, $table->poolRank('PG', 40));
        self::assertSame(2, $table->poolRank('PG', 50));
        self::assertSame(1, $table->starterRank('PG', 2));
        self::assertSame(2, $table->starterRank('PG', 1));
    }

    public function testBenchPlayerNotCountedInStarterRank(): void
    {
        $lineups = ProjectionFactory::leagueOf(3, static function (int $tid): array {
            $players = [ProjectionFactory::player(pid: $tid, tid: $tid, dcMinutes: 30, ratings: ProjectionFactory::ratingSet([], 40.0 + 10 * $tid))];
            if ($tid === 1) {
                $players[] = ProjectionFactory::player(pid: 100, tid: 1, dcMinutes: 20, ratings: ProjectionFactory::ratingSet([], 95.0));
            }

            return $players;
        });
        $table = SlotRankTable::build($lineups, ProjectionFactory::ratingsOf($lineups));

        self::assertSame(1, $table->poolRank('PG', 100));
        self::assertSame(3, $table->starterRank('PG', 1));
        self::assertSame(2, $table->starterRank('PG', 2));
        self::assertSame(1, $table->starterRank('PG', 3));
        self::assertSame(1, $table->bestBenchRank('PG', 1));
        self::assertNull($table->bestBenchRank('PG', 2));
    }

    public function testTeamWithoutStarterAtSlotHasNullRank(): void
    {
        $lineups = [1 => ProjectionFactory::lineupFor(1, 0, [ProjectionFactory::player(pid: 1, dcMinutes: 30)])];
        $table = SlotRankTable::build($lineups, ProjectionFactory::ratingsOf($lineups));

        self::assertNull($table->starterRank('C', 1));
        self::assertNull($table->worstStarterPoolRank('C'));
        self::assertSame(1, $table->starterRank('PG', 1));
    }

    public function testPoolRankNullForPlayerOutsidePool(): void
    {
        $lineups = [1 => ProjectionFactory::lineupFor(1, 0, [
            ProjectionFactory::player(pid: 1, dcMinutes: 30),
            ProjectionFactory::player(pid: 2, dcMinutes: 5),
        ])];
        $table = SlotRankTable::build($lineups, ProjectionFactory::ratingsOf($lineups));

        self::assertSame(1, $table->poolRank('PG', 1));
        self::assertNull($table->poolRank('PG', 2));
        self::assertNull($table->poolRank('PG', 999));
    }

    public function testLowerIsBetterRatingRanksCorrectlyAtCenter(): void
    {
        $lineups = [
            1 => ProjectionFactory::lineupFor(1, 0, [ProjectionFactory::player(pid: 1, pos: 'C', dcMinutes: 30, ratings: ProjectionFactory::ratingSet(['r_foul' => 80]))]),
            2 => ProjectionFactory::lineupFor(2, 0, [ProjectionFactory::player(pid: 2, tid: 2, pos: 'C', dcMinutes: 30, ratings: ProjectionFactory::ratingSet(['r_foul' => 20]))]),
        ];
        $table = SlotRankTable::build($lineups, ProjectionFactory::ratingsOf($lineups));

        self::assertSame(1, $table->starterRank('C', 2));
        self::assertSame(2, $table->starterRank('C', 1));
    }

    /**
     * 28 teams, one PG each, every rating 2.5 points above the team before.
     *
     * @return array<int, \TradeSuggestions\Profile\SeasonLineup>
     */
    private static function orderedLeague(): array
    {
        return ProjectionFactory::leagueOf(28, static fn (int $tid): array => [
            ProjectionFactory::player(
                pid: $tid * 10,
                tid: $tid,
                dcMinutes: 30,
                ratings: ProjectionFactory::ratingSet([], 20.0 + 2.5 * $tid)
            ),
        ]);
    }
}
