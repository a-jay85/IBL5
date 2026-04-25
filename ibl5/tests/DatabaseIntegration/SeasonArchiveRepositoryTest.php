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
            'award' => 'Test Integration Award',
            'name' => 'Test Winner',
        ]);

        $result = $this->repo->getAwardsByYear(2098);

        self::assertNotEmpty($result);
        $first = $result[0];
        self::assertArrayHasKey('award', $first);
        self::assertArrayHasKey('name', $first);
        self::assertSame('Test Integration Award', $first['award']);
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
                'game_date' => $date,
                'name' => 'Metros',
                'game_of_that_day' => 1,
                'visitor_teamid' => 2,
                'home_teamid' => 1,
                'attendance' => 15000,
                'capacity' => 15000,
                'visitor_wins' => 0,
                'visitor_losses' => $day - 1,
                'home_wins' => $day - 1,
                'home_losses' => 0,
                'visitor_q1_points' => 20,
                'visitor_q2_points' => 18,
                'visitor_q3_points' => 15,
                'visitor_q4_points' => 20,
                'visitor_ot_points' => 0,
                'home_q1_points' => 30,
                'home_q2_points' => 28,
                'home_q3_points' => 25,
                'home_q4_points' => 30,
                'home_ot_points' => 0,
                'game_2gm' => 30,
                'game_2ga' => 60,
                'game_ftm' => 15,
                'game_fta' => 20,
                'game_3gm' => 8,
                'game_3ga' => 22,
                'game_orb' => 10,
                'game_drb' => 30,
                'game_ast' => 20,
                'game_stl' => 8,
                'game_tov' => 12,
                'game_blk' => 5,
                'game_pf' => 18,
            ]);
            $this->insertRow('ibl_box_scores_teams', [
                'game_date' => $date,
                'name' => 'Sharks',
                'game_of_that_day' => 1,
                'visitor_teamid' => 2,
                'home_teamid' => 1,
                'attendance' => 15000,
                'capacity' => 15000,
                'visitor_wins' => 0,
                'visitor_losses' => $day - 1,
                'home_wins' => $day - 1,
                'home_losses' => 0,
                'visitor_q1_points' => 20,
                'visitor_q2_points' => 18,
                'visitor_q3_points' => 15,
                'visitor_q4_points' => 20,
                'visitor_ot_points' => 0,
                'home_q1_points' => 30,
                'home_q2_points' => 28,
                'home_q3_points' => 25,
                'home_q4_points' => 30,
                'home_ot_points' => 0,
                'game_2gm' => 30,
                'game_2ga' => 60,
                'game_ftm' => 15,
                'game_fta' => 20,
                'game_3gm' => 8,
                'game_3ga' => 22,
                'game_orb' => 10,
                'game_drb' => 30,
                'game_ast' => 20,
                'game_stl' => 8,
                'game_tov' => 12,
                'game_blk' => 5,
                'game_pf' => 18,
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
            'award' => 'Test Award 2098',
        ]);

        $result = $this->repo->getTeamAwardsByYear(2098);

        self::assertNotEmpty($result);
        $first = $result[0];
        self::assertArrayHasKey('name', $first);
        self::assertArrayHasKey('award', $first);
    }

    public function testGetTeamAwardsByYearSortsHierarchically(): void
    {
        // Insert out of hierarchy order to prove the query sorts, not insertion order.
        $this->insertRow('ibl_team_awards', [
            'year' => 2097,
            'name' => 'Team A',
            'award' => 'IBL Draft Lottery Winners',
        ]);
        $this->insertRow('ibl_team_awards', [
            'year' => 2097,
            'name' => 'Team B',
            'award' => 'Pacific Division Champions',
        ]);
        $this->insertRow('ibl_team_awards', [
            'year' => 2097,
            'name' => 'Team C',
            'award' => 'Atlantic Division Champions',
        ]);
        $this->insertRow('ibl_team_awards', [
            'year' => 2097,
            'name' => 'Team D',
            'award' => 'Western Conference Champions',
        ]);
        $this->insertRow('ibl_team_awards', [
            'year' => 2097,
            'name' => 'Team E',
            'award' => 'Eastern Conference Champions',
        ]);

        $result = $this->repo->getTeamAwardsByYear(2097);

        $awards = [];
        foreach ($result as $row) {
            $awards[] = $row['award'];
        }

        self::assertSame([
            'Eastern Conference Champions',
            'Western Conference Champions',
            'Atlantic Division Champions',
            'Pacific Division Champions',
            'IBL Draft Lottery Winners',
        ], $awards);
    }

    public function testGetAllGmAwardsWithTeamsReturnsJoinedRows(): void
    {
        $result = $this->repo->getAllGmAwardsWithTeams();

        // Production DB has GM awards data
        self::assertNotEmpty($result);
        $first = $result[0];
        self::assertArrayHasKey('gm_display_name', $first);
        self::assertArrayHasKey('team_name', $first);
        self::assertArrayHasKey('year', $first);
        self::assertArrayHasKey('award', $first);
    }

    public function testGetAllGmTenuresWithTeamsReturnsRows(): void
    {
        $result = $this->repo->getAllGmTenuresWithTeams();

        self::assertNotEmpty($result);
        $first = $result[0];
        self::assertArrayHasKey('gm_display_name', $first);
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
                'game_date' => $date,
                'name' => 'Metros',
                'game_of_that_day' => 1,
                'visitor_teamid' => 2,
                'home_teamid' => 1,
                'attendance' => 10000,
                'capacity' => 15000,
                'visitor_wins' => 0,
                'visitor_losses' => 0,
                'home_wins' => 0,
                'home_losses' => 0,
                'visitor_q1_points' => 20,
                'visitor_q2_points' => 18,
                'visitor_q3_points' => 15,
                'visitor_q4_points' => 20,
                'visitor_ot_points' => 0,
                'home_q1_points' => 25,
                'home_q2_points' => 22,
                'home_q3_points' => 20,
                'home_q4_points' => 28,
                'home_ot_points' => 0,
                'game_2gm' => 30,
                'game_2ga' => 60,
                'game_ftm' => 15,
                'game_fta' => 20,
                'game_3gm' => 8,
                'game_3ga' => 22,
                'game_orb' => 10,
                'game_drb' => 30,
                'game_ast' => 20,
                'game_stl' => 8,
                'game_tov' => 12,
                'game_blk' => 5,
                'game_pf' => 18,
            ]);
            $this->insertRow('ibl_box_scores_teams', [
                'game_date' => $date,
                'name' => 'Sharks',
                'game_of_that_day' => 1,
                'visitor_teamid' => 2,
                'home_teamid' => 1,
                'attendance' => 10000,
                'capacity' => 15000,
                'visitor_wins' => 0,
                'visitor_losses' => 0,
                'home_wins' => 0,
                'home_losses' => 0,
                'visitor_q1_points' => 20,
                'visitor_q2_points' => 18,
                'visitor_q3_points' => 15,
                'visitor_q4_points' => 20,
                'visitor_ot_points' => 0,
                'home_q1_points' => 25,
                'home_q2_points' => 22,
                'home_q3_points' => 20,
                'home_q4_points' => 28,
                'home_ot_points' => 0,
                'game_2gm' => 30,
                'game_2ga' => 60,
                'game_ftm' => 15,
                'game_fta' => 20,
                'game_3gm' => 8,
                'game_3ga' => 22,
                'game_orb' => 10,
                'game_drb' => 30,
                'game_ast' => 20,
                'game_stl' => 8,
                'game_tov' => 12,
                'game_blk' => 5,
                'game_pf' => 18,
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
        $this->insertTestPlayer(200090001, 'SA Test Player', ['teamid' => 0, 'ordinal' => 1000]);

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
