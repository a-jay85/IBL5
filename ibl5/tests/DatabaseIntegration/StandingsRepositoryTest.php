<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use PHPUnit\Framework\Attributes\Group;

use Standings\StandingsRepository;

/**
 * Database integration tests for StandingsRepository.
 *
 * Tests standings queries, streak data, Pythagorean stats (via VIEWs
 * ibl_team_offense_stats / ibl_team_defense_stats), and series records.
 */
#[Group('database')]
class StandingsRepositoryTest extends DatabaseTestCase
{
    private StandingsRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new StandingsRepository($this->db);
    }

    public function testGetStandingsByRegionConference(): void
    {
        $result = $this->repo->getStandingsByRegion('Eastern');

        self::assertNotEmpty($result);
        $first = $result[0];
        self::assertArrayHasKey('teamid', $first);
        self::assertArrayHasKey('team_name', $first);
        self::assertArrayHasKey('gamesBack', $first);
        self::assertArrayHasKey('color1', $first);
    }

    public function testGetStandingsByRegionDivision(): void
    {
        $result = $this->repo->getStandingsByRegion('Atlantic');

        self::assertNotEmpty($result);
        $first = $result[0];
        // For division queries, gamesBack comes from div_gb
        self::assertArrayHasKey('gamesBack', $first);
    }

    public function testGetStandingsByRegionThrowsForInvalidRegion(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('Invalid region: Nonexistent');
        $this->repo->getStandingsByRegion('Nonexistent');
    }

    public function testGetAllStandingsReturnsRows(): void
    {
        $result = $this->repo->getAllStandings();

        self::assertNotEmpty($result);

        $first = $result[0];
        self::assertArrayHasKey('teamid', $first);
        self::assertArrayHasKey('team_name', $first);
        self::assertArrayHasKey('conference', $first);
        self::assertArrayHasKey('division', $first);
        self::assertArrayHasKey('color1', $first);
        self::assertArrayHasKey('wins', $first);
        self::assertArrayHasKey('pct', $first);
    }

    public function testGetTeamStreakDataReturnsKnownTeam(): void
    {
        // teamid=1 exists in the real DB (seed or production)
        $result = $this->repo->getTeamStreakData(1);

        self::assertNotNull($result);
        self::assertArrayHasKey('streak_type', $result);
        self::assertArrayHasKey('streak', $result);
        self::assertArrayHasKey('ranking', $result);
        self::assertContains($result['streak_type'], ['W', 'L']);
        self::assertIsInt($result['streak']);
    }

    public function testGetTeamStreakDataReturnsNullForUnknown(): void
    {
        $result = $this->repo->getTeamStreakData(9999);

        self::assertNull($result);
    }

    public function testGetAllStreakDataIsKeyedByTeamId(): void
    {
        $result = $this->repo->getAllStreakData();

        self::assertNotEmpty($result);
        // Should be keyed by teamid (int)
        self::assertArrayHasKey(1, $result);
        self::assertArrayHasKey(2, $result);
        self::assertArrayHasKey('streak_type', $result[1]);
    }

    public function testGetTeamPythagoreanStatsReturnsNullWhenNoData(): void
    {
        $result = $this->repo->getTeamPythagoreanStats(1, 9999);

        self::assertNull($result);
    }

    public function testGetTeamPythagoreanStatsComputesFromBoxscores(): void
    {
        // Insert team boxscores for a regular-season game (Jan = game_type 1)
        // season_year = 2098 for date 2098-01-20
        $this->insertFranchiseSeasonRow(1, 2098, 'Metros');
        $this->insertFranchiseSeasonRow(2, 2098, 'Sharks');

        // Need BOTH team rows for the same game
        $this->insertTeamBoxscoreRow('2098-01-20', 'Metros', 1, 2, 1);
        $this->insertTeamBoxscoreRow('2098-01-20', 'Sharks', 1, 2, 1);

        $result = $this->repo->getTeamPythagoreanStats(1, 2098);

        self::assertNotNull($result);
        self::assertArrayHasKey('pointsScored', $result);
        self::assertArrayHasKey('pointsAllowed', $result);
        // Offense: from Metros row — game_2gm=30, game_ftm=15, game_3gm=8
        // Points = fgm*2 + ftm + tgm*3 = (30+8)*2 + 15 + 8*3 ... wait
        // Actually: offense VIEW sums game_2gm+game_3gm as fgm, game_ftm as ftm, game_3gm as tgm
        // calculatePoints = fgm*2 + ftm + tgm = (30+8)*2 + 15 + 8*3 is wrong
        // StatsFormatter::calculatePoints(fgm, ftm, tgm) = fgm*2 + ftm + tgm*3
        // From VIEW: fgm = SUM(game_2gm + game_3gm) = 30+8 = 38, ftm = 15, tgm = 8
        // Points = 38*2 + 15 + 8*3 = 76 + 15 + 24 = 115
        self::assertGreaterThan(0, $result['pointsScored']);
        self::assertGreaterThan(0, $result['pointsAllowed']);
    }

    // ── getAllPythagoreanStats ─────────────────────────────────

    public function testGetAllPythagoreanStatsReturnsKeyedArray(): void
    {
        $this->insertFranchiseSeasonRow(1, 2098, 'Metros');
        $this->insertFranchiseSeasonRow(2, 2098, 'Sharks');
        $this->insertTeamBoxscoreRow('2098-01-20', 'Metros', 1, 2, 1);
        $this->insertTeamBoxscoreRow('2098-01-20', 'Sharks', 1, 2, 1);

        $result = $this->repo->getAllPythagoreanStats(2098);

        self::assertNotEmpty($result);
        $firstKey = array_key_first($result);
        self::assertIsInt($firstKey);
        $firstRow = $result[$firstKey];
        self::assertArrayHasKey('pointsScored', $firstRow);
        self::assertArrayHasKey('pointsAllowed', $firstRow);
    }

    public function testGetAllPythagoreanStatsReturnsEmptyForNoBoxscores(): void
    {
        $result = $this->repo->getAllPythagoreanStats(8888);

        self::assertSame([], $result);
    }

    public function testGetSeriesRecordsReflectsScheduleData(): void
    {
        // Seed data has schedule row: season_year=2025, visitor_teamid=2, visitor_score=85, home_teamid=1, home_score=104
        // vw_series_records derives from ibl_schedule
        $result = $this->repo->getSeriesRecords();

        self::assertNotEmpty($result);
        $first = $result[0];
        self::assertArrayHasKey('self', $first);
        self::assertArrayHasKey('opponent', $first);
        self::assertArrayHasKey('wins', $first);
        self::assertArrayHasKey('losses', $first);
    }

    // --- Identifier allowlist negative paths (SQL-injection guard) -----------
    // Column identifiers cannot be bound; they are validated against a closed
    // allowlist and rejected with InvalidArgumentException. These assert an
    // out-of-allowlist / injection-style column is refused before any SQL runs.

    public function testUpdateMagicNumberRejectsUnknownColumn(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('Invalid magic number column');
        $this->repo->updateMagicNumber(1, 5, 'conf_magic_number = 0; DROP TABLE ibl_standings');
    }

    public function testUpdateClinchedFlagRejectsUnknownColumn(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('Invalid clinched column');
        $this->repo->updateClinchedFlag('Some Team', 'clinched_league = 1 WHERE 1=1; --');
    }

    public function testFetchTeamsByRegionRejectsUnknownGrouping(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('Invalid grouping column');
        $this->repo->fetchTeamsByRegion('1=1', 'Eastern');
    }

    public function testFetchTopTeamsByWinsRejectsUnknownGrouping(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('Invalid grouping column');
        $this->repo->fetchTopTeamsByWins('teamid); DROP TABLE ibl_standings; --', 'Eastern');
    }

    public function testUpdateMagicNumberAcceptsAllowlistedColumn(): void
    {
        // A valid column passes validation and the magic number is persisted to
        // that column (proves the allowlisted identifier reached the UPDATE).
        $this->repo->updateMagicNumber(1, 3, 'conf_magic_number');

        $result = $this->db->query('SELECT `conf_magic_number` FROM `ibl_standings` WHERE `teamid` = 1');
        self::assertNotFalse($result);
        $row = $result->fetch_assoc();
        self::assertIsArray($row);
        self::assertEquals(3, $row['conf_magic_number']);
    }

    public function testFetchTeamsByRegionReturnsRowsForValidGrouping(): void
    {
        $result = $this->repo->fetchTeamsByRegion('conference', 'Eastern');
        self::assertNotEmpty($result);
        self::assertArrayHasKey('teamid', $result[0]);
    }

    // ── Group C: computation queries ──────────────────────────────────────────

    public function testFetchTopTeamsByWinsReturnsTwoOrderedByWins(): void
    {
        $updateA = $this->db->query('UPDATE `ibl_standings` SET home_wins = 20, away_wins = 10 WHERE teamid = 1');
        self::assertNotFalse($updateA);
        $updateB = $this->db->query('UPDATE `ibl_standings` SET home_wins = 15, away_wins = 5 WHERE teamid = 3');
        self::assertNotFalse($updateB);

        $result = $this->repo->fetchTopTeamsByWins('conference', 'Eastern');

        self::assertCount(2, $result);
        self::assertEquals(30, $result[0]['wins']);
        self::assertEquals(20, $result[1]['wins']);
    }

    public function testFetchTopTeamsByWinsWithNullGroupingScansWholeLeague(): void
    {
        // Only set two teams; remaining 26 have NULL home_wins → NULL wins → sort last under DESC
        $updateA = $this->db->query('UPDATE `ibl_standings` SET home_wins = 30, away_wins = 10 WHERE teamid = 1');
        self::assertNotFalse($updateA);
        $updateB = $this->db->query('UPDATE `ibl_standings` SET home_wins = 20, away_wins = 15 WHERE teamid = 2');
        self::assertNotFalse($updateB);

        $result = $this->repo->fetchTopTeamsByWins(null, null);

        self::assertCount(2, $result);
        self::assertEquals(40, $result[0]['wins']);
        self::assertEquals(35, $result[1]['wins']);
        self::assertSame(1, $result[0]['teamid']);
        self::assertSame(2, $result[1]['teamid']);
    }

    public function testFetchLeastLosingTeamExcludesNamedTeam(): void
    {
        // Private conference avoids seed NULLs sorting first under ASC; only these two teams are in scope
        $updateA = $this->db->query(
            "UPDATE `ibl_standings` SET conference = 'TestConf3', home_losses = 1, away_losses = 1 WHERE teamid = 10"
        );
        self::assertNotFalse($updateA);
        $updateB = $this->db->query(
            "UPDATE `ibl_standings` SET conference = 'TestConf3', home_losses = 3, away_losses = 3 WHERE teamid = 11"
        );
        self::assertNotFalse($updateB);
        // Spurs (tid=10): losses=2 (fewest, excluded); Pioneers (tid=11): losses=6 (expected result)

        $result = $this->repo->fetchLeastLosingTeam('Spurs', 'conference', 'TestConf3');

        self::assertNotNull($result);
        self::assertEquals(6, $result['losses']);
    }

    public function testFetchLeastLosingTeamReturnsNullWhenOnlyCandidateIsExcluded(): void
    {
        $update = $this->db->query(
            "UPDATE `ibl_standings` SET conference = 'TestConf4', home_losses = 5, away_losses = 5 WHERE teamid = 13"
        );
        self::assertNotFalse($update);

        $result = $this->repo->fetchLeastLosingTeam('Apollos', 'conference', 'TestConf4');

        self::assertNull($result);
    }

    public function testIsRegionSeasonOverTrueWhenNoGamesUnplayed(): void
    {
        $update = $this->db->query("UPDATE `ibl_standings` SET games_unplayed = 0 WHERE conference = 'Eastern'");
        self::assertNotFalse($update);

        $result = $this->repo->isRegionSeasonOver('conference', 'Eastern');

        self::assertTrue($result);
    }

    public function testIsRegionSeasonOverFalseWhenAnyGameUnplayed(): void
    {
        $updateAll = $this->db->query("UPDATE `ibl_standings` SET games_unplayed = 0 WHERE conference = 'Eastern'");
        self::assertNotFalse($updateAll);
        $updateOne = $this->db->query('UPDATE `ibl_standings` SET games_unplayed = 1 WHERE teamid = 1');
        self::assertNotFalse($updateOne);

        $result = $this->repo->isRegionSeasonOver('conference', 'Eastern');

        self::assertFalse($result);
    }

    public function testGetHeadToHeadWinnerReturnsActualWinner(): void
    {
        // Isolated 2099 window: visitor=1 (Metros, 100) beats home=2 (Stars, 90)
        $this->insertScheduleRow(2099, '2099-06-15', 1, 100, 2, 90);

        $winner12 = $this->repo->getHeadToHeadWinner(1, 2, '2099-01-01', '2099-12-31');
        $winner21 = $this->repo->getHeadToHeadWinner(2, 1, '2099-01-01', '2099-12-31');

        self::assertSame(1, $winner12);
        self::assertSame(1, $winner21);
    }

    public function testGetHeadToHeadWinnerFallsBackToFirstTeamWithNoGames(): void
    {
        $tid1 = 1;
        $tid2 = 2;

        $result = $this->repo->getHeadToHeadWinner($tid1, $tid2, '2090-01-01', '2090-12-31');

        self::assertSame($tid1, $result);
    }

    public function testFetchTeamMapForSeasonKeysBySlot(): void
    {
        $this->insertRow('ibl_league_config', [
            'season_ending_year' => 2099,
            'team_slot' => 1,
            'team_name' => 'Metros',
            'conference' => 'Eastern',
            'division' => 'Atlantic',
            'playoff_qualifiers_per_conf' => 4,
            'playoff_round1_format' => 'bo7',
            'playoff_round2_format' => 'bo7',
            'playoff_round3_format' => 'bo7',
            'playoff_round4_format' => 'bo7',
            'team_count' => 28,
        ]);
        $this->insertRow('ibl_league_config', [
            'season_ending_year' => 2099,
            'team_slot' => 2,
            'team_name' => 'Stars',
            'conference' => 'Western',
            'division' => 'Pacific',
            'playoff_qualifiers_per_conf' => 4,
            'playoff_round1_format' => 'bo7',
            'playoff_round2_format' => 'bo7',
            'playoff_round3_format' => 'bo7',
            'playoff_round4_format' => 'bo7',
            'team_count' => 28,
        ]);

        $result = $this->repo->fetchTeamMapForSeason(2099);

        self::assertCount(2, $result);
        self::assertArrayHasKey(1, $result);
        self::assertArrayHasKey(2, $result);
        self::assertSame('Eastern', $result[1]['conference']);
        self::assertSame('Atlantic', $result[1]['division']);
        self::assertSame('Metros', $result[1]['teamName']);
        self::assertSame('Western', $result[2]['conference']);
    }

    public function testFetchTeamMapForSeasonReturnsEmptyForUnseededYear(): void
    {
        $result = $this->repo->fetchTeamMapForSeason(9999);

        self::assertSame([], $result);
    }

    public function testFetchPlayedGamesForSeasonReturnsScoredGamesOnly(): void
    {
        $this->insertScheduleRow(2099, '2099-06-01', 1, 0, 2, 0);
        $this->insertScheduleRow(2099, '2099-06-02', 1, 100, 2, 90);

        $scored = $this->repo->fetchPlayedGamesForSeason('2099-01-01', '2099-12-31');

        self::assertCount(1, $scored);
        self::assertEquals(100, $scored[0]['visitor_score']);

        $empty = $this->repo->fetchPlayedGamesForSeason('2090-01-01', '2090-12-31');
        self::assertSame([], $empty);
    }

    public function testFetchWinningestTeamsCapsAtEightPerConference(): void
    {
        // Eastern has 15 teams; setting home_wins = teamid gives unique values ≤ 41
        $update = $this->db->query(
            "UPDATE `ibl_standings` SET home_wins = teamid, away_wins = 0 WHERE conference = 'Eastern'"
        );
        self::assertNotFalse($update);

        $result = $this->repo->fetchWinningestTeams('Eastern');

        self::assertCount(8, $result);
        $prevWins = PHP_INT_MAX;
        foreach ($result as $row) {
            self::assertLessThanOrEqual($prevWins, $row['wins']);
            $prevWins = $row['wins'];
        }
    }

    public function testFetchMostLosingTeamsCapsAtSixPerConference(): void
    {
        // Eastern has 15 teams; setting home_losses = teamid gives unique values ≤ 41
        $update = $this->db->query(
            "UPDATE `ibl_standings` SET home_losses = teamid, away_losses = 0 WHERE conference = 'Eastern'"
        );
        self::assertNotFalse($update);

        $result = $this->repo->fetchMostLosingTeams('Eastern');

        self::assertCount(6, $result);
        $prevLosses = PHP_INT_MAX;
        foreach ($result as $row) {
            self::assertLessThanOrEqual($prevLosses, $row['losses']);
            $prevLosses = $row['losses'];
        }
    }

    public function testFetchScheduledGameCountsPerTeamCountsBothSides(): void
    {
        // Seeded game: 2025-01-15, visitor=2, home=1; UNION ALL counts each team once per role
        $result = $this->repo->fetchScheduledGameCountsPerTeam('2025-01-01', '2025-12-31');

        self::assertArrayHasKey(1, $result);
        self::assertArrayHasKey(2, $result);
        self::assertGreaterThanOrEqual(1, $result[1]);
        self::assertGreaterThanOrEqual(1, $result[2]);
    }

    // ── Group B: write operations ─────────────────────────────────────────────

    public function testUpsertStandingsInsertsThenUpdatesSameTeam(): void
    {
        // Remove seeded row so the first upsert is a real INSERT, not ON DUPLICATE KEY UPDATE
        $del = $this->db->query('DELETE FROM `ibl_standings` WHERE teamid = 1');
        self::assertNotFalse($del);

        $baseParams = [
            'teamid' => 1, 'teamName' => 'Metros', 'leagueRecord' => '40-20',
            'wins' => 40, 'losses' => 20, 'pct' => 0.667, 'gamesUnplayed' => 0,
            'conference' => 'Eastern', 'confGb' => 0.0, 'confRecord' => '20-10',
            'division' => 'Atlantic', 'divGb' => 0.0, 'divRecord' => '10-5',
            'homeRecord' => '22-8', 'awayRecord' => '18-12',
            'confWins' => 20, 'confLosses' => 10, 'divWins' => 10, 'divLosses' => 5,
            'homeWins' => 22, 'homeLosses' => 8, 'awayWins' => 18, 'awayLosses' => 12,
        ];

        // First call: true INSERT
        $this->repo->upsertStandings($baseParams);

        // Arrange values that ON DUPLICATE KEY UPDATE should reset to NULL
        $this->repo->updateMagicNumber(1, 5, 'conf_magic_number');
        $this->repo->updateClinchedFlag('Metros', 'clinched_playoffs');

        // Second call: ON DUPLICATE KEY UPDATE — resets all 6 magic/clinched columns to NULL
        $this->repo->upsertStandings(array_merge($baseParams, [
            'wins' => 60, 'losses' => 10, 'leagueRecord' => '60-10', 'pct' => 0.857,
        ]));

        $row = $this->db->query(
            'SELECT wins, conf_magic_number, div_magic_number, clinched_conference,
                    clinched_division, clinched_playoffs, clinched_league
             FROM `ibl_standings` WHERE teamid = 1'
        );
        self::assertNotFalse($row);
        $data = $row->fetch_assoc();
        self::assertIsArray($data);
        self::assertEquals(60, $data['wins']);
        self::assertNull($data['conf_magic_number']);
        self::assertNull($data['div_magic_number']);
        self::assertNull($data['clinched_conference']);
        self::assertNull($data['clinched_division']);
        self::assertNull($data['clinched_playoffs']);
        self::assertNull($data['clinched_league']);
    }

    public function testUpdateClinchedFlagSetsAllowlistedColumn(): void
    {
        $this->repo->updateClinchedFlag('Metros', 'clinched_playoffs');

        $result = $this->db->query(
            "SELECT `clinched_playoffs` FROM `ibl_standings` WHERE team_name = 'Metros'"
        );
        self::assertNotFalse($result);
        $row = $result->fetch_assoc();
        self::assertIsArray($row);
        self::assertEquals(1, $row['clinched_playoffs']);
    }

    public function testUpsertTeamAwardIsIdempotent(): void
    {
        $this->repo->upsertTeamAward(2099, 'Metros', 'TestDivisionAward');
        $this->repo->upsertTeamAward(2099, 'Metros', 'TestDivisionAward');

        $result = $this->db->query(
            "SELECT COUNT(*) AS cnt FROM `ibl_team_awards` WHERE year = 2099 AND award = 'TestDivisionAward'"
        );
        self::assertNotFalse($result);
        $row = $result->fetch_assoc();
        self::assertIsArray($row);
        self::assertEquals(1, $row['cnt']);
    }
}
