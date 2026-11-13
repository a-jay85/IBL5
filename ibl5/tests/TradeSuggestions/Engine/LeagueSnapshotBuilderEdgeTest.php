<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions\Engine;

use PHPUnit\Framework\TestCase;
use Tests\TradeSuggestions\Engine\Fixtures\SnapshotFactory;
use Tests\TradeSuggestions\Fixtures\ProjectionFactory;
use TradeSuggestions\Engine\LeagueSnapshotBuilder;
use TradeSuggestions\Engine\LeagueSnapshotInputs;
use TradeSuggestions\Projection\ProjectionPlayer;
use TradeSuggestions\Projection\Rating;
use TradeSuggestions\Valuation\ResignLikelihood;
use TradeSuggestions\Valuation\TeamFactors;

final class LeagueSnapshotBuilderEdgeTest extends TestCase
{
    public function testNonFranchiseTeamFirstDoesNotStopTheScanAndTeamIdsComeOutSorted(): void
    {
        $base = SnapshotFactory::inputs(2, 5);
        $in = self::withRosters($base, [
            SnapshotFactory::NON_FRANCHISE_TID => $base->playersByTid[SnapshotFactory::NON_FRANCHISE_TID],
            2 => $base->playersByTid[2],
            1 => $base->playersByTid[1],
        ]);

        $s = LeagueSnapshotBuilder::build($in);

        self::assertSame([1, 2], $s->teamIds);
        self::assertSame([1, 2], array_keys($s->lineups[0]));
        self::assertSame([201, 202, 203, 204, 205, 101, 102, 103, 104, 105], array_keys($s->players));
    }

    /**
     * Team 1 holds X (PG, one season left) and T (PG, one season left); team 2 holds R (PG, three seasons).
     * At offset 0 X is in the PG pool and T is his teammate at PG. At offset 1 neither is in a lineup or pool.
     */
    private static function resignInputs(): LeagueSnapshotInputs
    {
        $top = [];
        foreach (Rating::ALL as $key) {
            $top[$key] = 99;
        }
        $x = ProjectionFactory::player(
            pid: 11,
            tid: 1,
            pos: 'PG',
            salaryByYear: [1 => 500, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0],
            ratings: ProjectionFactory::ratingSet($top),
            dcMinutes: 32,
        );
        $t = ProjectionFactory::player(
            pid: 12,
            tid: 1,
            pos: 'PG',
            salaryByYear: [1 => 800, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0],
            ratings: ProjectionFactory::ratingSet([], 70.0),
            dcMinutes: 20,
        );
        $r = ProjectionFactory::player(
            pid: 21,
            tid: 2,
            pos: 'PG',
            salaryByYear: [1 => 300, 2 => 300, 3 => 300, 4 => 0, 5 => 0, 6 => 0],
            ratings: ProjectionFactory::ratingSet([], 60.0),
            dcMinutes: 20,
        );
        $base = SnapshotFactory::inputs(2, 5, [1 => [$x, $t], 2 => [$r]]);

        return new LeagueSnapshotInputs(
            seasonYear: $base->seasonYear,
            advancesContractYears: $base->advancesContractYears,
            regularSeason: $base->regularSeason,
            playersByTid: $base->playersByTid,
            scale: $base->scale,
            standings: $base->standings,
            streaks: $base->streaks,
            teamFactors: [1 => new TeamFactors(1, 30, 30, 1000, 1000), 2 => new TeamFactors(2, 30, 30, 1000, 1000)],
            cashByTeamAndOffset: $base->cashByTeamAndOffset,
            pickRows: $base->pickRows,
            draftOrder: $base->draftOrder,
            modes: $base->modes,
            teamNames: $base->teamNames,
        );
    }

    public function testResignOddsUseOffsetZeroPoolRanksAndOffsetZeroLineups(): void
    {
        $s = LeagueSnapshotBuilder::build(self::resignInputs());

        self::assertSame(1, $s->slotRanks[0]->poolRank('PG', 11));
        self::assertNull($s->slotRanks[1]->poolRank('PG', 11));
        // Own PG money is teammate T (800), rival PG money is R (300); all prefs 0, bird 1 (no rights).
        $expected = ResignLikelihood::likelihood(-0.0375);
        self::assertEqualsWithDelta($expected, $s->resignLikelihood[11], 1e-9);
        self::assertTrue($s->likelyToWalk[11]);
    }

    /**
     * @param array<int, list<ProjectionPlayer>> $rosters
     */
    private static function withRosters(LeagueSnapshotInputs $in, array $rosters): LeagueSnapshotInputs
    {
        return new LeagueSnapshotInputs(
            seasonYear: $in->seasonYear,
            advancesContractYears: $in->advancesContractYears,
            regularSeason: $in->regularSeason,
            playersByTid: $rosters,
            scale: $in->scale,
            standings: $in->standings,
            streaks: $in->streaks,
            teamFactors: $in->teamFactors,
            cashByTeamAndOffset: $in->cashByTeamAndOffset,
            pickRows: $in->pickRows,
            draftOrder: $in->draftOrder,
            modes: $in->modes,
            teamNames: $in->teamNames,
        );
    }
}
