<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use PHPUnit\Framework\Attributes\Group;
use PlrParser\PlrBoxScoreRepository;

#[Group('database')]
class PlrBoxScoreRepositoryTest extends DatabaseTestCase
{
    private PlrBoxScoreRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new PlrBoxScoreRepository($this->db);
    }

    public function testSumStatsByGameTypeThroughDateReturnsAggregatesPerPid(): void
    {
        $this->insertPlayerBoxscoreRow('2025-01-15', 1, 'P1', 'PG', 2, 1, 1, minutes: 30, points2m: 5, points2a: 10, ftm: 3, fta: 4, points3m: 2, points3a: 5, orb: 2, drb: 4, ast: 6, stl: 1, tov: 2, blk: 1, pf: 2);
        $this->insertPlayerBoxscoreRow('2025-01-20', 1, 'P1', 'PG', 1, 3, 1, minutes: 28, points2m: 4, points2a: 8, ftm: 2, fta: 3, points3m: 1, points3a: 4, orb: 1, drb: 3, ast: 4, stl: 2, tov: 1, blk: 0, pf: 3);

        $result = $this->repo->sumStatsByGameTypeThroughDate(2025, 1, '2025-01-31');

        self::assertArrayHasKey(1, $result);
        self::assertSame(2, $result[1]['gp']);
        self::assertSame(58, $result[1]['min']);
        self::assertSame(9, $result[1]['two_gm']);
        self::assertSame(18, $result[1]['two_ga']);
        self::assertSame(5, $result[1]['ftm']);
        self::assertSame(7, $result[1]['fta']);
        self::assertSame(3, $result[1]['three_gm']);
        self::assertSame(9, $result[1]['three_ga']);
        self::assertSame(3, $result[1]['orb']);
        self::assertSame(7, $result[1]['drb']);
        self::assertSame(10, $result[1]['ast']);
        self::assertSame(3, $result[1]['stl']);
        self::assertSame(3, $result[1]['tov']);
        self::assertSame(1, $result[1]['blk']);
        self::assertSame(5, $result[1]['pf']);
    }

    public function testSumStatsByGameTypeThroughDateExcludesRowsAfterEndDate(): void
    {
        $this->insertPlayerBoxscoreRow('2025-01-15', 1, 'P1', 'PG', 2, 1, 1);
        $this->insertPlayerBoxscoreRow('2025-02-15', 1, 'P1', 'PG', 2, 1, 1);

        $result = $this->repo->sumStatsByGameTypeThroughDate(2025, 1, '2025-01-31');

        self::assertArrayHasKey(1, $result);
        self::assertSame(1, $result[1]['gp']);
    }

    public function testSumStatsByGameTypeThroughDateCountsGpOnlyForNonZeroMinutes(): void
    {
        $this->insertPlayerBoxscoreRow('2025-01-15', 1, 'P1', 'PG', 2, 1, 1, minutes: 30);
        $this->insertPlayerBoxscoreRow('2025-01-20', 1, 'P1', 'PG', 1, 3, 1, minutes: 0, points2m: 0, points2a: 0, ftm: 0, fta: 0, points3m: 0, points3a: 0, orb: 0, drb: 0, ast: 0, stl: 0, tov: 0, blk: 0, pf: 0);

        $result = $this->repo->sumStatsByGameTypeThroughDate(2025, 1, '2025-01-31');

        self::assertArrayHasKey(1, $result);
        self::assertSame(1, $result[1]['gp']);
    }

    public function testSumStatsByGameTypeThroughDateReturnsEmptyForNoData(): void
    {
        $result = $this->repo->sumStatsByGameTypeThroughDate(2025, 1, '2025-01-31');
        self::assertSame([], $result);
    }

    public function testGetSingleGameMaximumsThroughDateReturnsMaxima(): void
    {
        $this->insertPlayerBoxscoreRow('2025-01-15', 1, 'P1', 'PG', 2, 1, 1, minutes: 30, points2m: 8, points2a: 12, ftm: 5, fta: 6, points3m: 3, points3a: 7, orb: 3, drb: 7, ast: 8, stl: 3, tov: 2, blk: 2, pf: 2);
        $this->insertPlayerBoxscoreRow('2025-01-20', 1, 'P1', 'PG', 1, 3, 1, minutes: 35, points2m: 10, points2a: 15, ftm: 8, fta: 10, points3m: 5, points3a: 10, orb: 5, drb: 9, ast: 12, stl: 5, tov: 3, blk: 4, pf: 4);

        $result = $this->repo->getSingleGameMaximumsThroughDate(2025, 1, '2025-01-31');

        self::assertArrayHasKey(1, $result);
        // Game 2: 10*2 + 8 + 5*3 = 43 points
        self::assertSame(43, $result[1]['high_pts']);
        // Game 2: orb 5 + drb 9 = 14 rebounds
        self::assertSame(14, $result[1]['high_reb']);
        self::assertSame(12, $result[1]['high_ast']);
        self::assertSame(5, $result[1]['high_stl']);
        self::assertSame(4, $result[1]['high_blk']);
    }

    public function testGetSingleGameMaximumsThroughDateCountsDoubleDoubles(): void
    {
        // 10pts (5*2=10), 10reb (3orb+7drb), 5ast — that's a double-double
        $this->insertPlayerBoxscoreRow('2025-01-15', 1, 'P1', 'PG', 2, 1, 1, minutes: 30, points2m: 5, points2a: 10, ftm: 0, fta: 0, points3m: 0, points3a: 0, orb: 3, drb: 7, ast: 5, stl: 1, tov: 2, blk: 0, pf: 2);

        $result = $this->repo->getSingleGameMaximumsThroughDate(2025, 1, '2025-01-31');

        self::assertSame(1, $result[1]['doubles']);
        self::assertSame(0, $result[1]['triples']);
    }

    public function testGetSingleGameMaximumsThroughDateCountsTripleDoubles(): void
    {
        // 10pts (5*2=10), 10reb (3orb+7drb), 10ast — triple-double
        $this->insertPlayerBoxscoreRow('2025-01-15', 1, 'P1', 'PG', 2, 1, 1, minutes: 35, points2m: 5, points2a: 12, ftm: 0, fta: 0, points3m: 0, points3a: 0, orb: 3, drb: 7, ast: 10, stl: 1, tov: 2, blk: 0, pf: 2);

        $result = $this->repo->getSingleGameMaximumsThroughDate(2025, 1, '2025-01-31');

        self::assertSame(0, $result[1]['doubles']);
        self::assertSame(1, $result[1]['triples']);
    }

    public function testLatestGameDateReturnsMaxDate(): void
    {
        $this->insertPlayerBoxscoreRow('2025-01-15', 1, 'P1', 'PG', 2, 1, 1);
        $this->insertPlayerBoxscoreRow('2025-01-20', 1, 'P1', 'PG', 1, 3, 1);

        $result = $this->repo->latestGameDate(2025, 1);
        self::assertSame('2025-01-20', $result);
    }

    public function testLatestGameDateReturnsNullWhenEmpty(): void
    {
        $result = $this->repo->latestGameDate(2025, 1);
        self::assertNull($result);
    }

    public function testCumulativeRegularSeasonStatsByDateReturnsCumulativeRunningTotals(): void
    {
        $this->insertPlayerBoxscoreRow('2025-01-15', 1, 'P1', 'PG', 2, 1, 1, minutes: 30, points2m: 5, points2a: 10, ftm: 3, fta: 4, points3m: 2, points3a: 5, orb: 2, drb: 4, ast: 6, stl: 1, tov: 2, blk: 1, pf: 2);
        $this->insertPlayerBoxscoreRow('2025-01-20', 1, 'P1', 'PG', 1, 3, 1, minutes: 28, points2m: 4, points2a: 8, ftm: 2, fta: 3, points3m: 1, points3a: 4, orb: 1, drb: 3, ast: 4, stl: 2, tov: 1, blk: 0, pf: 3);

        $result = $this->repo->cumulativeRegularSeasonStatsByDate(1, 2025);

        self::assertCount(2, $result);

        self::assertSame('2025-01-15', $result[0]['date']);
        self::assertSame(1, $result[0]['gp']);
        self::assertSame(30, $result[0]['min']);
        self::assertSame(5, $result[0]['two_gm']);

        self::assertSame('2025-01-20', $result[1]['date']);
        self::assertSame(2, $result[1]['gp']);
        self::assertSame(58, $result[1]['min']);
        self::assertSame(9, $result[1]['two_gm']);
    }

    public function testSumTeamRegularSeasonStatsThroughDateAggregatesTeamStats(): void
    {
        // Insert two team boxscore rows per game (visitor row 1, home row 2)
        $this->insertTeamBoxscoreRow('2025-01-15', 'Stars', 1, 2, 1);
        $this->insertTeamBoxscoreRow('2025-01-15', 'Metros', 1, 2, 1);

        $result = $this->repo->sumTeamRegularSeasonStatsThroughDate(2025, '2025-01-31');

        // visitor_teamid=2 (Stars) gets rn=1, home_teamid=1 (Metros) gets rn=2
        self::assertArrayHasKey(2, $result);
        self::assertArrayHasKey(1, $result);
        self::assertSame(1, $result[2]['gp']);
        self::assertSame(1, $result[1]['gp']);
    }

    public function testSumTeamPlayoffStatsThroughDateUsesGameType2(): void
    {
        // June dates = game_type 2 (playoffs)
        $this->insertTeamBoxscoreRow('2025-06-10', 'Stars', 1, 2, 1);
        $this->insertTeamBoxscoreRow('2025-06-10', 'Metros', 1, 2, 1);

        $result = $this->repo->sumTeamPlayoffStatsThroughDate(2025, '2025-06-30');

        self::assertArrayHasKey(2, $result);
        self::assertArrayHasKey(1, $result);
        self::assertSame(1, $result[2]['gp']);

        $regularSeason = $this->repo->sumTeamRegularSeasonStatsThroughDate(2025, '2025-06-30');
        self::assertSame([], $regularSeason);
    }

    public function testSimEndDatesForSeasonReturnsOrderedDates(): void
    {
        // Seed has sim 1: start=2025-01-10, end=2025-01-20
        // Add another sim within the 2025 season window
        $this->insertRow('ibl_sim_dates', [
            'start_date' => '2025-02-01',
            'end_date' => '2025-02-10',
        ]);

        $result = $this->repo->simEndDatesForSeason(2025);

        self::assertCount(2, $result);
        self::assertSame('2025-01-20', $result[0]);
        self::assertSame('2025-02-10', $result[1]);
    }

    public function testSimEndDatesForSeasonExcludesOutOfWindowDates(): void
    {
        // Insert a sim outside the 2025 season window (before Oct 2024)
        $this->insertRow('ibl_sim_dates', [
            'start_date' => '2024-08-01',
            'end_date' => '2024-08-10',
        ]);

        $result = $this->repo->simEndDatesForSeason(2025);

        // Only the seed sim (2025-01-20) should be returned
        self::assertCount(1, $result);
        self::assertSame('2025-01-20', $result[0]);
    }
}
