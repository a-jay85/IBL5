<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use Standings\StandingsRepository;

/**
 * Database integration tests for StandingsRepository.
 *
 * Tests standings queries, streak data, Pythagorean stats (via VIEWs
 * ibl_team_offense_stats / ibl_team_defense_stats), and series records.
 */
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
        self::assertArrayHasKey('tid', $first);
        self::assertArrayHasKey('team_name', $first);
        self::assertArrayHasKey('gamesBack', $first);
        self::assertArrayHasKey('color1', $first);
    }

    public function testGetStandingsByRegionDivision(): void
    {
        $result = $this->repo->getStandingsByRegion('Atlantic');

        self::assertNotEmpty($result);
        $first = $result[0];
        // For division queries, gamesBack comes from divGB
        self::assertArrayHasKey('gamesBack', $first);
    }

    public function testGetStandingsByRegionThrowsForInvalidRegion(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid region: Nonexistent');
        $this->repo->getStandingsByRegion('Nonexistent');
    }

    public function testGetAllStandingsReturnsRows(): void
    {
        $result = $this->repo->getAllStandings();

        self::assertNotEmpty($result);

        $first = $result[0];
        self::assertArrayHasKey('tid', $first);
        self::assertArrayHasKey('team_name', $first);
        self::assertArrayHasKey('conference', $first);
        self::assertArrayHasKey('division', $first);
        self::assertArrayHasKey('color1', $first);
        self::assertArrayHasKey('wins', $first);
        self::assertArrayHasKey('pct', $first);
    }

    public function testGetTeamStreakDataReturnsKnownTeam(): void
    {
        // tid=1 exists in the real DB (seed or production)
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
        // Should be keyed by TeamID (int)
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
        // Offense: from Metros row — game2GM=30, gameFTM=15, game3GM=8
        // Points = fgm*2 + ftm + tgm*3 = (30+8)*2 + 15 + 8*3 ... wait
        // Actually: offense VIEW sums game2GM+game3GM as fgm, gameFTM as ftm, game3GM as tgm
        // calculatePoints = fgm*2 + ftm + tgm = (30+8)*2 + 15 + 8*3 is wrong
        // StatsFormatter::calculatePoints(fgm, ftm, tgm) = fgm*2 + ftm + tgm*3
        // From VIEW: fgm = SUM(game2GM + game3GM) = 30+8 = 38, ftm = 15, tgm = 8
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
        // Seed data has schedule row: Year=2025, Visitor=2, VScore=85, Home=1, HScore=104
        // vw_series_records derives from ibl_schedule
        $result = $this->repo->getSeriesRecords();

        self::assertNotEmpty($result);
        $first = $result[0];
        self::assertArrayHasKey('self', $first);
        self::assertArrayHasKey('opponent', $first);
        self::assertArrayHasKey('wins', $first);
        self::assertArrayHasKey('losses', $first);
    }
}
