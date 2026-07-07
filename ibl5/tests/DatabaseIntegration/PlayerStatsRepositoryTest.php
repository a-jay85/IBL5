<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use PHPUnit\Framework\Attributes\Group;

use Player\Stats\PlayerStatsRepository;

/**
 * Database integration tests for PlayerStatsRepository.
 *
 * Tests player stats retrieval, box score JOINs, and VIEW-backed playoff/HEAT/season
 * career stats. VIEWs derive from ibl_box_scores with game_type auto-classification:
 * - January dates → game_type=1 (regular season)
 * - June dates → game_type=2 (playoffs)
 * - October dates → game_type=3 (HEAT)
 */
#[Group('database')]
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

        // Insert matching schedule row (needed for box_id JOIN)
        $this->insertRow('ibl_schedule', [
            'season_year' => 2098,
            'box_id' => 500,
            'game_date' => '2098-01-15',
            'visitor_teamid' => 2,
            'visitor_score' => 85,
            'home_teamid' => 1,
            'home_score' => 100,
            'uuid' => 'sched-boxdt-0000-000000000001',
        ]);

        $result = $this->repo->getBoxScoresBetweenDates($pid, '2098-01-01', '2098-01-31');

        self::assertNotEmpty($result);
        $first = $result[0];
        self::assertSame($pid, $first['pid']);
        self::assertArrayHasKey('game_of_that_day', $first);
        // COALESCE in MariaDB may return string; consumer casts via (int).
        self::assertEquals(1, $first['game_of_that_day']);
        self::assertArrayHasKey('box_id', $first);
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
        // FGM = calc_fg_made = game_2gm + game_3gm
        self::assertSame(6 + 2 + 8 + 3, $result['fgm']); // 19
    }

    public function testGetSeasonCareerAveragesReturnsRow(): void
    {
        $pid = 200000075;
        $this->insertTestPlayer($pid, 'DB Szn Avg');

        $this->insertHistRow($pid, 'DB Szn Avg', 2098);

        $result = $this->repo->getSeasonCareerAverages('DB Szn Avg');

        self::assertNotNull($result);
        self::assertSame($pid, $result['pid']);
        self::assertSame(50, $result['games']); // insertHistRow default
    }

    public function testGetSeasonCareerAveragesByIdMatchesByName(): void
    {
        $pid = 200000076;
        $this->insertTestPlayer($pid, 'DB Match');

        $this->insertHistRow($pid, 'DB Match', 2098);

        $byName = $this->repo->getSeasonCareerAverages('DB Match');
        $byId = $this->repo->getSeasonCareerAveragesById($pid);

        self::assertNotNull($byName);
        self::assertNotNull($byId);
        self::assertSame($byName['pid'], $byId['pid']);
        self::assertSame($byName['games'], $byId['games']);
    }

    public function testGetSeasonCareerAveragesUsesHistDirectly(): void
    {
        // Player with ONLY ibl_hist rows (no box_scores) — career averages still returned
        $pid = 200000079;
        $this->insertTestPlayer($pid, 'DB Hist Direct');
        $this->insertHistRow($pid, 'DB Hist Direct', 2097, ['games' => 40, 'pts' => 600]);
        $this->insertHistRow($pid, 'DB Hist Direct', 2098, ['games' => 50, 'pts' => 750]);

        $result = $this->repo->getSeasonCareerAverages('DB Hist Direct');

        self::assertNotNull($result);
        self::assertSame($pid, $result['pid']);
        self::assertSame(90, $result['games']); // 40 + 50
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

    public function testGetBoxScoresBetweenDatesIncludesDnpRows(): void
    {
        // getBoxScoresBetweenDates is the documented include-DNP site (full per-game log).
        // A DNP row (game_min=0) must appear in the result so the PlayerOverviewView can render MIN=0.
        $pid = 200000073;
        $this->insertTestPlayer($pid, 'DB Box DNP');

        // DNP boxscore row (game_min = 0)
        $this->insertPlayerBoxscoreRow('2098-01-15', $pid, 'DB Box DNP', 'PG', 2, 1, 1, minutes: 0,
            points2m: 0, points2a: 0, ftm: 0, fta: 0, points3m: 0, points3a: 0,
            orb: 0, drb: 0, ast: 0, stl: 0, tov: 0, blk: 0, pf: 0);
        $this->insertTeamBoxscoreRow('2098-01-15', 'Metros', 1, 2, 1);
        $this->insertTeamBoxscoreRow('2098-01-15', 'Sharks', 1, 2, 1);
        $this->insertRow('ibl_schedule', [
            'season_year' => 2098,
            'box_id' => 501,
            'game_date' => '2098-01-15',
            'visitor_teamid' => 2,
            'visitor_score' => 85,
            'home_teamid' => 1,
            'home_score' => 100,
            'uuid' => 'sched-boxdt-0000-000000000002',
        ]);

        $result = $this->repo->getBoxScoresBetweenDates($pid, '2098-01-01', '2098-01-31');

        $found = false;
        foreach ($result as $row) {
            if ($row['pid'] === $pid) {
                $found = true;
                self::assertSame(0, (int) $row['game_min'], 'DNP row must appear with game_min=0');
                break;
            }
        }
        self::assertTrue($found, 'DNP row must be included in getBoxScoresBetweenDates (include-DNP site)');
    }

    // ── DNP exclusion from career/per-season averages (maintenance-40b) ──
    //
    // Career per-game averages and games counts must exclude DNP rows
    // (game_min = 0). A per-game average = total ÷ games-played, never
    // ÷ total roster rows; DNP rows are bench appearances, not games played.
    // The fix adds BaseMysqliRepository::playedCondition('bs') (game_min > 0)
    // to buildCareerAveragesQuery() and buildPerSeasonStatsQuery().
    //
    // The CI seed has no game_min=0 rows, so these tests self-seed a
    // 1-played + 1-DNP block (mirroring testGetBoxScoresBetweenDatesIncludesDnpRows).

    public function testGetPlayoffCareerAveragesExcludeDnpRows(): void
    {
        $pid = 200000080;
        $name = 'DB DNP Avg';
        $this->insertTestPlayer($pid, $name);
        $this->insertFranchiseSeasonRow(1, 2098, 'Metros');

        // Played playoff game: 30 min, 8 pts (game_2gm 4 → 4·2), reb 6 (orb 2 + drb 4)
        $this->insertPlayerBoxscoreRow(
            '2098-06-10', $pid, $name, 'PG', 2, 1, 1,
            minutes: 30, points2m: 4, points2a: 8,
            ftm: 0, fta: 0, points3m: 0, points3a: 0,
            orb: 2, drb: 4, ast: 0, stl: 0, tov: 0, blk: 0, pf: 0
        );
        // DNP playoff game: every stat 0
        $this->insertPlayerBoxscoreRow(
            '2098-06-12', $pid, $name, 'PG', 2, 1, 1,
            minutes: 0, points2m: 0, points2a: 0,
            ftm: 0, fta: 0, points3m: 0, points3a: 0,
            orb: 0, drb: 0, ast: 0, stl: 0, tov: 0, blk: 0, pf: 0
        );

        $result = $this->repo->getPlayoffCareerAverages($name);

        self::assertNotNull($result);
        // DNP row excluded: games=1 (not 2), averages over the played game only.
        // (Buggy pre-fix values were games=2, MIN=15.0, PTS=4.0, REB=3.0.)
        self::assertSame(1, $result['games']);
        self::assertSame(30.0, (float) $result['minutes']);
        self::assertSame(8.0, (float) $result['pts']);
        self::assertSame(6.0, (float) $result['reb']);
    }

    public function testGetPlayoffStatsExcludesDnpRowsFromGamesCount(): void
    {
        $pid = 200000081;
        $name = 'DB DNP Szn';
        $this->insertTestPlayer($pid, $name);
        $this->insertFranchiseSeasonRow(1, 2098, 'Metros');

        // One played + one DNP playoff game in the same season.
        $this->insertPlayerBoxscoreRow(
            '2098-06-10', $pid, $name, 'PG', 2, 1, 1,
            minutes: 30, points2m: 4, points2a: 8,
            ftm: 0, fta: 0, points3m: 0, points3a: 0,
            orb: 2, drb: 4, ast: 0, stl: 0, tov: 0, blk: 0, pf: 0
        );
        $this->insertPlayerBoxscoreRow(
            '2098-06-12', $pid, $name, 'PG', 2, 1, 1,
            minutes: 0, points2m: 0, points2a: 0,
            ftm: 0, fta: 0, points3m: 0, points3a: 0,
            orb: 0, drb: 0, ast: 0, stl: 0, tov: 0, blk: 0, pf: 0
        );

        $result = $this->repo->getPlayoffStats($name);

        self::assertCount(1, $result);
        // SUM-based per-season row reflects only the played game (games=1, not 2).
        self::assertSame(1, $result[0]['games']);
        self::assertSame(30, $result[0]['minutes']);
        self::assertSame(8, $result[0]['pts']);
    }

    public function testGetPlayoffCareerAveragesReturnsNullForAllDnpPlayer(): void
    {
        $pid = 200000082;
        $name = 'DB All DNP';
        $this->insertTestPlayer($pid, $name);
        $this->insertFranchiseSeasonRow(1, 2098, 'Metros');

        // Only DNP playoff rows — after the filter, no rows remain → no GROUP BY row.
        $this->insertPlayerBoxscoreRow(
            '2098-06-10', $pid, $name, 'PG', 2, 1, 1,
            minutes: 0, points2m: 0, points2a: 0,
            ftm: 0, fta: 0, points3m: 0, points3a: 0,
            orb: 0, drb: 0, ast: 0, stl: 0, tov: 0, blk: 0, pf: 0
        );
        $this->insertPlayerBoxscoreRow(
            '2098-06-12', $pid, $name, 'PG', 2, 1, 1,
            minutes: 0, points2m: 0, points2a: 0,
            ftm: 0, fta: 0, points3m: 0, points3a: 0,
            orb: 0, drb: 0, ast: 0, stl: 0, tov: 0, blk: 0, pf: 0
        );

        // All rows filtered out → null (not a row with games=0 and zeroed averages).
        self::assertNull($this->repo->getPlayoffCareerAverages($name));
    }

    public function testGetPlayoffCareerAveragesUnaffectedForPlayedOnlyPlayer(): void
    {
        $pid = 200000083;
        $name = 'DB Played Only';
        $this->insertTestPlayer($pid, $name);
        $this->insertFranchiseSeasonRow(1, 2098, 'Metros');

        // Two played playoff games, no DNP rows — the filter is a no-op here.
        $this->insertPlayerBoxscoreRow(
            '2098-06-10', $pid, $name, 'PG', 2, 1, 1,
            minutes: 30, points2m: 4, points2a: 8,
            ftm: 0, fta: 0, points3m: 0, points3a: 0,
            orb: 2, drb: 4, ast: 0, stl: 0, tov: 0, blk: 0, pf: 0
        );
        $this->insertPlayerBoxscoreRow(
            '2098-06-12', $pid, $name, 'PG', 2, 1, 1,
            minutes: 20, points2m: 6, points2a: 10,
            ftm: 0, fta: 0, points3m: 0, points3a: 0,
            orb: 0, drb: 6, ast: 0, stl: 0, tov: 0, blk: 0, pf: 0
        );

        $result = $this->repo->getPlayoffCareerAverages($name);

        self::assertNotNull($result);
        // Both games count; averages over both: games=2, MIN=25, PTS=(8+12)/2=10, REB=(6+6)/2=6.
        self::assertSame(2, $result['games']);
        self::assertSame(25.0, (float) $result['minutes']);
        self::assertSame(10.0, (float) $result['pts']);
        self::assertSame(6.0, (float) $result['reb']);
    }
}
