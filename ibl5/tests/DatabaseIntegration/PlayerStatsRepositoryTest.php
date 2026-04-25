<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use Player\PlayerStatsRepository;

/**
 * Database integration tests for PlayerStatsRepository.
 *
 * Tests player stats retrieval, box score JOINs, and VIEW-backed playoff/HEAT/season
 * career stats. VIEWs derive from ibl_box_scores with game_type auto-classification:
 * - January dates → game_type=1 (regular season)
 * - June dates → game_type=2 (playoffs)
 * - October dates → game_type=3 (HEAT)
 */
class PlayerStatsRepositoryTest extends DatabaseTestCase
{
    private PlayerStatsRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new PlayerStatsRepository($this->db);
    }

    public function testGetPlayerStatsReturnsRow(): void
    {
        // Seed data has pid=1 'Test Player One' on Metros
        $result = $this->repo->getPlayerStats(1);

        self::assertNotNull($result);
        self::assertSame(1, $result['pid']);
        self::assertSame('Test Player One', $result['name']);
    }

    public function testGetPlayerStatsReturnsNullForUnknown(): void
    {
        $result = $this->repo->getPlayerStats(999999999);

        self::assertNull($result);
    }

    public function testGetHistoricalStatsOrderedByYear(): void
    {
        $pid = 200000070;
        $this->insertTestPlayer($pid, 'DB Hist Ord');
        $this->insertHistRow($pid, 'DB Hist Ord', 2098);
        $this->insertHistRow($pid, 'DB Hist Ord', 2096);
        $this->insertHistRow($pid, 'DB Hist Ord', 2097);

        $result = $this->repo->getHistoricalStats($pid);

        self::assertCount(3, $result);
        self::assertSame(2096, $result[0]['year']);
        self::assertSame(2097, $result[1]['year']);
        self::assertSame(2098, $result[2]['year']);
    }

    public function testGetBoxScoresBetweenDatesReturnsMatchingRows(): void
    {
        $pid = 200000071;
        $this->insertTestPlayer($pid, 'DB Box Dates');

        // Insert player boxscore
        $this->insertPlayerBoxscoreRow('2098-01-15', $pid, 'DB Box Dates', 'PG', 2, 1, 1);

        // Insert matching team boxscore row (needed for the subquery JOIN)
        $this->insertTeamBoxscoreRow('2098-01-15', 'Metros', 1, 2, 1);
        $this->insertTeamBoxscoreRow('2098-01-15', 'Sharks', 1, 2, 1);

        // Insert matching schedule row (needed for BoxID JOIN)
        $this->insertRow('ibl_schedule', [
            'Year' => 2098,
            'BoxID' => 500,
            'Date' => '2098-01-15',
            'Visitor' => 2,
            'VScore' => 85,
            'Home' => 1,
            'HScore' => 100,
            'uuid' => 'sched-boxdt-0000-000000000001',
        ]);

        $result = $this->repo->getBoxScoresBetweenDates($pid, '2098-01-01', '2098-01-31');

        self::assertNotEmpty($result);
        $first = $result[0];
        self::assertSame($pid, $first['pid']);
        self::assertArrayHasKey('gameOfThatDay', $first);
        self::assertArrayHasKey('BoxID', $first);
    }

    public function testGetBoxScoresBetweenDatesExcludesOutOfRange(): void
    {
        $pid = 200000072;
        $this->insertTestPlayer($pid, 'DB Box OutRng');
        $this->insertPlayerBoxscoreRow('2098-03-15', $pid, 'DB Box OutRng', 'PG', 2, 1, 1);

        $result = $this->repo->getBoxScoresBetweenDates($pid, '2098-01-01', '2098-01-31');

        // March date is outside Jan range
        $found = false;
        foreach ($result as $row) {
            if ($row['pid'] === $pid) {
                $found = true;
                break;
            }
        }
        self::assertFalse($found, 'Out-of-range boxscore should not be returned');
    }

    public function testGetPlayoffStatsReturnsRowsForJuneGames(): void
    {
        $pid = 200000073;
        $this->insertTestPlayer($pid, 'DB Playoff');

        // June date = game_type=2 (playoff), season_year = 2098
        $this->insertFranchiseSeasonRow(1, 2098, 'Metros');

        $this->insertPlayerBoxscoreRow(
            '2098-06-15', $pid, 'DB Playoff', 'PG', 2, 1, 1,
            points2m: 8, points2a: 15, ftm: 5, fta: 6, points3m: 3, points3a: 7
        );

        $result = $this->repo->getPlayoffStats('DB Playoff');

        self::assertNotEmpty($result);
        self::assertSame(2098, $result[0]['year']);
        self::assertSame('Metros', $result[0]['team']);
    }

    public function testGetPlayoffCareerTotalsAggregates(): void
    {
        $pid = 200000074;
        $this->insertTestPlayer($pid, 'DB PO Career');

        // Insert playoff boxscores across 2 seasons
        $this->insertFranchiseSeasonRow(1, 2097, 'Metros');
        $this->insertFranchiseSeasonRow(1, 2098, 'Metros');

        $this->insertPlayerBoxscoreRow(
            '2097-06-10', $pid, 'DB PO Career', 'PG', 2, 1, 1,
            points2m: 6, ftm: 4, points3m: 2
        );
        $this->insertPlayerBoxscoreRow(
            '2098-06-10', $pid, 'DB PO Career', 'PG', 2, 1, 1,
            points2m: 8, ftm: 5, points3m: 3
        );

        $result = $this->repo->getPlayoffCareerTotals('DB PO Career');

        self::assertNotNull($result);
        self::assertSame(2, $result['games']);
        // FGM = calc_fg_made = game2GM + game3GM
        self::assertSame(6 + 2 + 8 + 3, $result['fgm']); // 19
    }

    public function testGetSeasonCareerAveragesReturnsRow(): void
    {
        $pid = 200000075;
        $this->insertTestPlayer($pid, 'DB Szn Avg');

        // Regular season boxscore (Jan = game_type 1)
        $this->insertPlayerBoxscoreRow(
            '2098-01-20', $pid, 'DB Szn Avg', 'PG', 2, 1, 1,
            minutes: 35, points2m: 7, ftm: 5, points3m: 3
        );

        $result = $this->repo->getSeasonCareerAverages('DB Szn Avg');

        self::assertNotNull($result);
        self::assertSame($pid, $result['pid']);
        self::assertSame(1, $result['games']);
    }

    public function testGetSeasonCareerAveragesByIdMatchesByName(): void
    {
        $pid = 200000076;
        $this->insertTestPlayer($pid, 'DB Match');

        $this->insertPlayerBoxscoreRow(
            '2098-02-10', $pid, 'DB Match', 'SG', 2, 1, 1
        );

        $byName = $this->repo->getSeasonCareerAverages('DB Match');
        $byId = $this->repo->getSeasonCareerAveragesById($pid);

        self::assertNotNull($byName);
        self::assertNotNull($byId);
        self::assertSame($byName['pid'], $byId['pid']);
        self::assertSame($byName['games'], $byId['games']);
    }

    // ── getSimDates ─────────────────────────────────────────────

    public function testGetSimDatesReturnsArray(): void
    {
        $dates = $this->repo->getSimDates();

        self::assertIsArray($dates);
        if ($dates !== []) {
            self::assertArrayHasKey('sim', $dates[0]);
        }
    }

    public function testGetSimDatesRespectsLimit(): void
    {
        $dates = $this->repo->getSimDates(5);

        self::assertLessThanOrEqual(5, count($dates));
    }

    // ── getPlayoffCareerAverages ────────────────────────────────

    public function testGetPlayoffCareerAveragesReturnsNullForNoData(): void
    {
        self::assertNull($this->repo->getPlayoffCareerAverages('DB No PO Avg'));
    }

    public function testGetPlayoffCareerAveragesReturnsRow(): void
    {
        $pid = 200000077;
        $this->insertTestPlayer($pid, 'DB PO Avgs');
        $this->insertFranchiseSeasonRow(1, 2098, 'Metros');

        $this->insertPlayerBoxscoreRow(
            '2098-06-12', $pid, 'DB PO Avgs', 'PG', 2, 1, 1,
            minutes: 30, points2m: 6, ftm: 3, points3m: 2
        );

        $result = $this->repo->getPlayoffCareerAverages('DB PO Avgs');

        self::assertNotNull($result);
        self::assertSame($pid, $result['pid']);
        self::assertSame(1, $result['games']);
    }

    // ── HEAT stats ──────────────────────────────────────────────

    public function testGetHeatStatsReturnsEmptyForNoData(): void
    {
        self::assertSame([], $this->repo->getHeatStats('DB No Heat'));
    }

    public function testGetHeatStatsReturnsRowForOctoberGames(): void
    {
        $pid = 200000078;
        $this->insertTestPlayer($pid, 'DB Heat Plr');
        $this->insertFranchiseSeasonRow(1, 2098, 'Metros');

        // October date = game_type=3 (HEAT)
        $this->insertPlayerBoxscoreRow(
            '2097-10-15', $pid, 'DB Heat Plr', 'SF', 2, 1, 1,
            minutes: 28, points2m: 5, ftm: 2, points3m: 1
        );

        $result = $this->repo->getHeatStats('DB Heat Plr');

        self::assertNotEmpty($result);
        self::assertSame(2098, $result[0]['year']);
    }

    public function testGetHeatCareerTotalsReturnsNullForNoData(): void
    {
        self::assertNull($this->repo->getHeatCareerTotals('DB No Heat Tot'));
    }

    public function testGetHeatCareerAveragesReturnsNullForNoData(): void
    {
        self::assertNull($this->repo->getHeatCareerAverages('DB No Heat Avg'));
    }

    // ── Olympics stats ──────────────────────────────────────────

    public function testGetOlympicsStatsReturnsEmptyForNoData(): void
    {
        self::assertSame([], $this->repo->getOlympicsStats(999999999));
    }

    public function testGetOlympicsCareerTotalsReturnsNullForNoData(): void
    {
        self::assertNull($this->repo->getOlympicsCareerTotals(999999999));
    }

    public function testGetOlympicsCareerAveragesReturnsNullForNoData(): void
    {
        self::assertNull($this->repo->getOlympicsCareerAverages(999999999));
    }
}
