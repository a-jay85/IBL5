<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use SeasonArchive\SeasonArchiveRepository;

class SeasonArchiveRepositoryTest extends DatabaseTestCase
{
    private SeasonArchiveRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new SeasonArchiveRepository($this->db);
    }

    public function testGetAllSeasonYearsReturnsDistinctYears(): void
    {
        $result = $this->repo->getAllSeasonYears();

        self::assertNotEmpty($result);
        // Should be sorted ascending
        $sorted = $result;
        sort($sorted);
        self::assertSame($sorted, $result);
    }

    public function testGetAwardsByYearReturnsRowsForKnownYear(): void
    {
        // Insert a test award to ensure we have data for a specific year
        $this->insertRow('ibl_awards', [
            'year' => 2098,
            'Award' => 'Test Integration Award',
            'name' => 'Test Winner',
        ]);

        $result = $this->repo->getAwardsByYear(2098);

        self::assertNotEmpty($result);
        $first = $result[0];
        self::assertArrayHasKey('Award', $first);
        self::assertArrayHasKey('name', $first);
        self::assertSame('Test Integration Award', $first['Award']);
    }

    public function testGetAwardsByYearReturnsEmptyForUnknownYear(): void
    {
        $result = $this->repo->getAwardsByYear(9999);

        self::assertSame([], $result);
    }

    public function testGetPlayoffResultsByYearReturnsRows(): void
    {
        // Insert playoff games (month=6 → game_type=2) to populate vw_playoff_series_results
        for ($day = 1; $day <= 4; $day++) {
            $date = sprintf('2098-06-%02d', $day);
            $this->insertRow('ibl_box_scores_teams', [
                'Date' => $date,
                'name' => 'Metros',
                'gameOfThatDay' => 1,
                'visitorTeamID' => 2,
                'homeTeamID' => 1,
                'attendance' => 15000,
                'capacity' => 15000,
                'visitorWins' => 0,
                'visitorLosses' => $day - 1,
                'homeWins' => $day - 1,
                'homeLosses' => 0,
                'visitorQ1points' => 20,
                'visitorQ2points' => 18,
                'visitorQ3points' => 15,
                'visitorQ4points' => 20,
                'visitorOTpoints' => 0,
                'homeQ1points' => 30,
                'homeQ2points' => 28,
                'homeQ3points' => 25,
                'homeQ4points' => 30,
                'homeOTpoints' => 0,
                'game2GM' => 30,
                'game2GA' => 60,
                'gameFTM' => 15,
                'gameFTA' => 20,
                'game3GM' => 8,
                'game3GA' => 22,
                'gameORB' => 10,
                'gameDRB' => 30,
                'gameAST' => 20,
                'gameSTL' => 8,
                'gameTOV' => 12,
                'gameBLK' => 5,
                'gamePF' => 18,
            ]);
            $this->insertRow('ibl_box_scores_teams', [
                'Date' => $date,
                'name' => 'Sharks',
                'gameOfThatDay' => 1,
                'visitorTeamID' => 2,
                'homeTeamID' => 1,
                'attendance' => 15000,
                'capacity' => 15000,
                'visitorWins' => 0,
                'visitorLosses' => $day - 1,
                'homeWins' => $day - 1,
                'homeLosses' => 0,
                'visitorQ1points' => 20,
                'visitorQ2points' => 18,
                'visitorQ3points' => 15,
                'visitorQ4points' => 20,
                'visitorOTpoints' => 0,
                'homeQ1points' => 30,
                'homeQ2points' => 28,
                'homeQ3points' => 25,
                'homeQ4points' => 30,
                'homeOTpoints' => 0,
                'game2GM' => 30,
                'game2GA' => 60,
                'gameFTM' => 15,
                'gameFTA' => 20,
                'game3GM' => 8,
                'game3GA' => 22,
                'gameORB' => 10,
                'gameDRB' => 30,
                'gameAST' => 20,
                'gameSTL' => 8,
                'gameTOV' => 12,
                'gameBLK' => 5,
                'gamePF' => 18,
            ]);
        }

        $result = $this->repo->getPlayoffResultsByYear(2098);

        self::assertNotEmpty($result);
        $first = $result[0];
        self::assertArrayHasKey('winner', $first);
        self::assertArrayHasKey('loser', $first);
        self::assertArrayHasKey('winner_games', $first);
    }

    public function testGetTeamAwardsByYearReturnsRows(): void
    {
        // Insert a team award for a unique year
        $this->insertRow('ibl_team_awards', [
            'year' => 2098,
            'name' => 'Test Team',
            'Award' => 'Test Award 2098',
        ]);

        $result = $this->repo->getTeamAwardsByYear(2098);

        self::assertNotEmpty($result);
        $first = $result[0];
        self::assertArrayHasKey('name', $first);
        self::assertArrayHasKey('Award', $first);
    }

    public function testGetAllGmAwardsWithTeamsReturnsJoinedRows(): void
    {
        $result = $this->repo->getAllGmAwardsWithTeams();

        // Production DB has GM awards data
        self::assertNotEmpty($result);
        $first = $result[0];
        self::assertArrayHasKey('gm_username', $first);
        self::assertArrayHasKey('team_name', $first);
        self::assertArrayHasKey('year', $first);
        self::assertArrayHasKey('Award', $first);
    }

    public function testGetAllGmTenuresWithTeamsReturnsRows(): void
    {
        $result = $this->repo->getAllGmTenuresWithTeams();

        self::assertNotEmpty($result);
        $first = $result[0];
        self::assertArrayHasKey('gm_username', $first);
        self::assertArrayHasKey('team_name', $first);
        self::assertArrayHasKey('start_season_year', $first);
    }

    public function testGetHeatWinLossByYearReturnsRows(): void
    {
        // Insert HEAT games (month=10 → game_type=3) for ibl_heat_win_loss view
        // Year=2098 to avoid conflicts with production data
        for ($day = 1; $day <= 3; $day++) {
            $date = sprintf('2098-10-%02d', $day);
            $this->insertRow('ibl_box_scores_teams', [
                'Date' => $date,
                'name' => 'Metros',
                'gameOfThatDay' => 1,
                'visitorTeamID' => 2,
                'homeTeamID' => 1,
                'attendance' => 10000,
                'capacity' => 15000,
                'visitorWins' => 0,
                'visitorLosses' => 0,
                'homeWins' => 0,
                'homeLosses' => 0,
                'visitorQ1points' => 20,
                'visitorQ2points' => 18,
                'visitorQ3points' => 15,
                'visitorQ4points' => 20,
                'visitorOTpoints' => 0,
                'homeQ1points' => 25,
                'homeQ2points' => 22,
                'homeQ3points' => 20,
                'homeQ4points' => 28,
                'homeOTpoints' => 0,
                'game2GM' => 30,
                'game2GA' => 60,
                'gameFTM' => 15,
                'gameFTA' => 20,
                'game3GM' => 8,
                'game3GA' => 22,
                'gameORB' => 10,
                'gameDRB' => 30,
                'gameAST' => 20,
                'gameSTL' => 8,
                'gameTOV' => 12,
                'gameBLK' => 5,
                'gamePF' => 18,
            ]);
            $this->insertRow('ibl_box_scores_teams', [
                'Date' => $date,
                'name' => 'Sharks',
                'gameOfThatDay' => 1,
                'visitorTeamID' => 2,
                'homeTeamID' => 1,
                'attendance' => 10000,
                'capacity' => 15000,
                'visitorWins' => 0,
                'visitorLosses' => 0,
                'homeWins' => 0,
                'homeLosses' => 0,
                'visitorQ1points' => 20,
                'visitorQ2points' => 18,
                'visitorQ3points' => 15,
                'visitorQ4points' => 20,
                'visitorOTpoints' => 0,
                'homeQ1points' => 25,
                'homeQ2points' => 22,
                'homeQ3points' => 20,
                'homeQ4points' => 28,
                'homeOTpoints' => 0,
                'game2GM' => 30,
                'game2GA' => 60,
                'gameFTM' => 15,
                'gameFTA' => 20,
                'game3GM' => 8,
                'game3GA' => 22,
                'gameORB' => 10,
                'gameDRB' => 30,
                'gameAST' => 20,
                'gameSTL' => 8,
                'gameTOV' => 12,
                'gameBLK' => 5,
                'gamePF' => 18,
            ]);
        }

        $result = $this->repo->getHeatWinLossByYear(2098);

        self::assertNotEmpty($result);
        $first = $result[0];
        self::assertArrayHasKey('currentname', $first);
        self::assertArrayHasKey('wins', $first);
        self::assertArrayHasKey('losses', $first);
    }

    public function testGetTeamColorsReturnsMapForRealTeams(): void
    {
        $result = $this->repo->getTeamColors();

        // Should contain all real teams (1-28)
        self::assertNotEmpty($result);
        // Verify structure of first entry
        $firstTeam = array_values($result)[0];
        self::assertArrayHasKey('color1', $firstTeam);
        self::assertArrayHasKey('color2', $firstTeam);
        self::assertArrayHasKey('teamid', $firstTeam);
    }

    public function testGetPlayerIdsByNamesReturnsMappedPids(): void
    {
        $this->insertTestPlayer(200090001, 'SA Test Player', ['tid' => 0, 'ordinal' => 1000]);

        $result = $this->repo->getPlayerIdsByNames(['SA Test Player']);

        self::assertArrayHasKey('SA Test Player', $result);
        self::assertSame(200090001, $result['SA Test Player']);
    }

    public function testGetPlayerIdsByNamesReturnsEmptyForEmptyInput(): void
    {
        $result = $this->repo->getPlayerIdsByNames([]);

        self::assertSame([], $result);
    }

    public function testGetTeamConferencesReturnsMap(): void
    {
        $result = $this->repo->getTeamConferences();

        // Should have entries from standings table
        self::assertNotEmpty($result);
        // All values should be conference names
        foreach ($result as $conference) {
            self::assertContains($conference, ['Eastern', 'Western']);
        }
    }
}
