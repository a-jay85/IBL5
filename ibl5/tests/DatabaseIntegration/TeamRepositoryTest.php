<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use Team\TeamRepository;

class TeamRepositoryTest extends DatabaseTestCase
{
    private TeamRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new TeamRepository($this->db);
    }

    public function testGetTeamReturnsRowForKnownTeam(): void
    {
        // Team 1 exists in production data — just verify structure
        $team = $this->repo->getTeam(1);

        self::assertNotNull($team);
        self::assertSame(1, $team['teamid']);
        self::assertArrayHasKey('team_name', $team);
        self::assertArrayHasKey('team_city', $team);
        self::assertArrayHasKey('color1', $team);
    }

    public function testGetTeamReturnsNullForUnknown(): void
    {
        $team = $this->repo->getTeam(99999);

        self::assertNull($team);
    }

    public function testGetTeamPowerDataReturnsJoinedRow(): void
    {
        $this->ensureStandingsAndPowerExist(1, 'Atlantic', 'Eastern');

        // Get team name for teamid=1
        $stmt = $this->db->prepare("SELECT team_name FROM ibl_team_info WHERE teamid = 1");
        self::assertNotFalse($stmt);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        self::assertNotNull($row);

        /** @var string $teamName */
        $teamName = $row['team_name'];

        $power = $this->repo->getTeamPowerData($teamName);

        self::assertNotNull($power);
        self::assertArrayHasKey('tid', $power);
        self::assertArrayHasKey('team_name', $power);
        self::assertArrayHasKey('wins', $power);
        self::assertArrayHasKey('losses', $power);
        self::assertArrayHasKey('conference', $power);
        self::assertArrayHasKey('division', $power);
        self::assertArrayHasKey('ranking', $power);
        self::assertArrayHasKey('streak_type', $power);
        self::assertArrayHasKey('sos', $power);
    }

    public function testGetTeamPowerDataReturnsNullForUnknown(): void
    {
        $power = $this->repo->getTeamPowerData('Nonexistent Team');

        self::assertNull($power);
    }

    public function testGetDivisionStandingsReturnsTeamsInDivision(): void
    {
        // Ensure standings + power data exists for a test division
        $this->ensureStandingsAndPowerExist(1, 'Atlantic', 'Eastern');

        $standings = $this->repo->getDivisionStandings('Atlantic');

        self::assertNotEmpty($standings);
        foreach ($standings as $r) {
            self::assertSame('Atlantic', $r['division']);
            self::assertArrayHasKey('ranking', $r);
        }
    }

    public function testGetConferenceStandingsReturnsTeamsInConference(): void
    {
        $this->ensureStandingsAndPowerExist(1, 'Atlantic', 'Eastern');

        $standings = $this->repo->getConferenceStandings('Eastern');

        self::assertNotEmpty($standings);
        foreach ($standings as $r) {
            self::assertSame('Eastern', $r['conference']);
        }
    }

    public function testGetChampionshipBannersReturnsRows(): void
    {
        // Insert a test banner within the transaction
        $this->insertRow('ibl_banners', [
            'year' => 2099,
            'currentname' => 'TestBannerTeam',
            'bannername' => 'TestBannerTeam',
            'bannertype' => 1,
        ]);

        $banners = $this->repo->getChampionshipBanners('TestBannerTeam');

        self::assertNotEmpty($banners);
        self::assertSame(2099, $banners[0]['year']);
        self::assertSame('TestBannerTeam', $banners[0]['currentname']);
    }

    public function testGetChampionshipBannersReturnsEmptyForUnknown(): void
    {
        $banners = $this->repo->getChampionshipBanners('ZZZ_Nonexistent_Team');

        self::assertSame([], $banners);
    }

    public function testGetGMTenuresReturnsRows(): void
    {
        // Insert a test tenure within the transaction
        $this->insertRow('ibl_gm_tenures', [
            'franchise_id' => 1,
            'gm_username' => 'test_tenure_gm',
            'start_season_year' => 2098,
            'end_season_year' => 2099,
            'is_mid_season_start' => 0,
            'is_mid_season_end' => 0,
        ]);

        $tenures = $this->repo->getGMTenures(1);

        self::assertNotEmpty($tenures);
        // Find our inserted tenure
        $found = false;
        foreach ($tenures as $tenure) {
            if ($tenure['gm_username'] === 'test_tenure_gm') {
                self::assertSame(2098, $tenure['start_season_year']);
                $found = true;
                break;
            }
        }
        self::assertTrue($found, 'Inserted GM tenure not found');
    }

    public function testGetRegularSeasonHistoryDependsOnBoxscoreView(): void
    {
        // ibl_team_win_loss is a VIEW derived from ibl_box_scores_teams.
        // Insert a regular season boxscore (month 01 = game_type 1)
        $this->insertTeamBoxscoreRow('2098-01-15', 'TestHistTeam', 1, 2, 1);

        // The view joins with ibl_team_info, so get team_name for teamid=1
        $stmt = $this->db->prepare("SELECT team_name FROM ibl_team_info WHERE teamid = 1");
        self::assertNotFalse($stmt);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        self::assertNotNull($row);

        /** @var string $teamName */
        $teamName = $row['team_name'];
        $history = $this->repo->getRegularSeasonHistory($teamName);

        self::assertNotEmpty($history);
        self::assertArrayHasKey('year', $history[0]);
        self::assertArrayHasKey('wins', $history[0]);
        self::assertArrayHasKey('losses', $history[0]);
    }

    public function testGetRosterUnderContractReturnsContractedPlayers(): void
    {
        // Team 1 should have players in production data
        $roster = $this->repo->getRosterUnderContract(1);

        self::assertNotEmpty($roster);
        foreach ($roster as $player) {
            self::assertSame(1, $player['tid']);
            self::assertSame(0, $player['retired']);
        }
    }

    public function testGetFreeAgentsReturnsPlayersWithHighOrdinal(): void
    {
        // Insert a free agent player with high ordinal in the transaction
        $this->insertRow('ibl_plr', [
            'pid' => 99990,
            'name' => 'Test Free Agent',
            'age' => 25,
            'tid' => 0,
            'pos' => 'SG',
            'sta' => 70,
            'exp' => 2,
            'bird' => 0,
            'cy' => 0,
            'cyt' => 0,
            'cy1' => 0,
            'cy2' => 0,
            'retired' => 0,
            'ordinal' => 1000,
            'droptime' => 0,
            'uuid' => '99990000-0000-0000-0000-000000000000',
        ]);

        $freeAgents = $this->repo->getFreeAgents();

        self::assertNotEmpty($freeAgents);
        foreach ($freeAgents as $player) {
            self::assertGreaterThan(959, $player['ordinal']);
            self::assertSame(0, $player['retired']);
        }
    }

    public function testGetAllTeamsReturnsOnlyRealTeams(): void
    {
        $teams = $this->repo->getAllTeams();

        self::assertCount(28, $teams);
        foreach ($teams as $team) {
            self::assertGreaterThanOrEqual(1, $team['teamid']);
            self::assertLessThanOrEqual(\League::MAX_REAL_TEAMID, $team['teamid']);
        }
    }

    public function testGetFranchiseSeasonsReturnsRows(): void
    {
        $seasons = $this->repo->getFranchiseSeasons(1);

        // Production DB should have franchise season data
        self::assertNotEmpty($seasons);
        self::assertSame(1, $seasons[0]['franchise_id']);
        self::assertArrayHasKey('season_year', $seasons[0]);
        self::assertArrayHasKey('team_city', $seasons[0]);
        self::assertArrayHasKey('team_name', $seasons[0]);
    }

    public function testGetHistoricalRosterReturnsRows(): void
    {
        // Insert a test hist row in the transaction
        $this->insertRow('ibl_hist', [
            'pid' => 1,
            'name' => 'Test Hist Player',
            'year' => 2098,
            'team' => 'TestTeam',
            'teamid' => 1,
            'games' => 50,
            'minutes' => 1600,
            'fgm' => 300,
            'fga' => 600,
            'ftm' => 100,
            'fta' => 120,
            'tgm' => 50,
            'tga' => 130,
            'orb' => 40,
            'reb' => 200,
            'ast' => 150,
            'stl' => 50,
            'blk' => 20,
            'tvr' => 80,
            'pf' => 100,
            'pts' => 750,
            'salary' => 1500,
        ]);

        $roster = $this->repo->getHistoricalRoster(1, '2098');

        self::assertNotEmpty($roster);
        self::assertSame(1, $roster[0]['teamid']);
    }

    // ── getGMAwards ───────────────────────────────────────────

    public function testGetGMAwardsReturnsRowsForKnownGM(): void
    {
        $this->insertRow('ibl_gm_awards', [
            'name' => 'b9_test_gm',
            'Award' => 'GM of the Year',
            'year' => 2099,
        ]);

        $result = $this->repo->getGMAwards('b9_test_gm');

        self::assertCount(1, $result);
        self::assertSame('b9_test_gm', $result[0]['name']);
        self::assertSame('GM of the Year', $result[0]['Award']);
        self::assertSame(2099, $result[0]['year']);
    }

    public function testGetGMAwardsReturnsEmptyForUnknownGM(): void
    {
        self::assertSame([], $this->repo->getGMAwards('zz_no_such_gm_xyz'));
    }

    // ── getTeamAccomplishments ──────────────────────────────────

    public function testGetTeamAccomplishmentsReturnsTeamAwardRows(): void
    {
        $this->insertTeamAwardRow('B9TestTeam', 'Atlantic Division Title', 2099);

        $result = $this->repo->getTeamAccomplishments('B9TestTeam');

        self::assertNotEmpty($result);
        self::assertSame('Atlantic Division Title', $result[0]['Award']);
    }

    public function testGetTeamAccomplishmentsReturnsEmptyForUnknownTeam(): void
    {
        self::assertSame([], $this->repo->getTeamAccomplishments('ZZ_Nonexistent_Batch9'));
    }

    // ── getHEATHistory ──────────────────────────────────────────

    public function testGetHEATHistoryReturnsArrayWithExpectedShape(): void
    {
        // ibl_heat_win_loss is a VIEW derived from game_type=3 boxscores.
        // CI seed may have no HEAT game data — test shape contract only.
        $result = $this->repo->getHEATHistory('Metros');

        self::assertIsArray($result);
        if ($result !== []) {
            self::assertArrayHasKey('year', $result[0]);
            self::assertArrayHasKey('currentname', $result[0]);
            self::assertArrayHasKey('namethatyear', $result[0]);
            self::assertArrayHasKey('wins', $result[0]);
            self::assertArrayHasKey('losses', $result[0]);
        }
    }

    // ── getPlayoffResults ───────────────────────────────────────

    public function testGetPlayoffResultsReturnsPlayoffSeriesData(): void
    {
        // Insert 4 playoff boxscores (June = game_type=2 auto-generated).
        // Team 1 (visitor) wins 3 games, Team 2 (home) wins 1 game.
        // Total score = sum of all 4 quarters. Visitor wins when v_total > h_total.
        $games = [
            ['vTotal' => 100, 'hTotal' => 90],  // visitor (tid=1) wins
            ['vTotal' => 85,  'hTotal' => 95],   // home (tid=2) wins
            ['vTotal' => 98,  'hTotal' => 88],   // visitor wins
            ['vTotal' => 97,  'hTotal' => 92],   // visitor wins
        ];

        foreach ($games as $i => $g) {
            $date = sprintf('2099-06-%02d', $i + 1);
            $this->insertRow('ibl_box_scores_teams', [
                'Date' => $date,
                'name' => $i % 2 === 0 ? 'Metros' : 'Sharks',
                'gameOfThatDay' => 1,
                'visitorTeamID' => 1,
                'homeTeamID' => 2,
                'attendance' => 15000, 'capacity' => 18000,
                'visitorWins' => 0, 'visitorLosses' => 0,
                'homeWins' => 0, 'homeLosses' => 0,
                'visitorQ1points' => (int) ($g['vTotal'] / 4),
                'visitorQ2points' => (int) ($g['vTotal'] / 4),
                'visitorQ3points' => (int) ($g['vTotal'] / 4),
                'visitorQ4points' => $g['vTotal'] - 3 * (int) ($g['vTotal'] / 4),
                'visitorOTpoints' => 0,
                'homeQ1points' => (int) ($g['hTotal'] / 4),
                'homeQ2points' => (int) ($g['hTotal'] / 4),
                'homeQ3points' => (int) ($g['hTotal'] / 4),
                'homeQ4points' => $g['hTotal'] - 3 * (int) ($g['hTotal'] / 4),
                'homeOTpoints' => 0,
                'game2GM' => 30, 'game2GA' => 60, 'gameFTM' => 15, 'gameFTA' => 20,
                'game3GM' => 8, 'game3GA' => 22, 'gameORB' => 10, 'gameDRB' => 30,
                'gameAST' => 20, 'gameSTL' => 8, 'gameTOV' => 12, 'gameBLK' => 5, 'gamePF' => 18,
            ]);
        }

        $this->insertFranchiseSeasonRow(1, 2099, 'Metros');
        $this->insertFranchiseSeasonRow(2, 2099, 'Sharks');

        $result = $this->repo->getPlayoffResults();

        // Find our 2099 series
        $series2099 = array_filter(
            $result,
            static fn (array $r): bool => $r['year'] === 2099,
        );

        self::assertNotEmpty($series2099);
        $series = array_values($series2099)[0];
        self::assertSame(3, $series['winner_games']);
        self::assertSame(1, $series['loser_games']);
    }

    private function ensureStandingsAndPowerExist(int $tid, string $division, string $conference): void
    {
        // Use REPLACE to ensure data exists within transaction regardless of DB state
        $this->db->query("DELETE FROM ibl_power WHERE TeamID = $tid");
        $this->db->query("DELETE FROM ibl_standings WHERE tid = $tid");
        $this->insertRow('ibl_standings', [
            'tid' => $tid,
            'team_name' => 'TestTeam',
            'wins' => 30,
            'losses' => 20,
            'pct' => 0.600,
            'leagueRecord' => '30-20',
            'conference' => $conference,
            'division' => $division,
            'confRecord' => '18-12',
            'confGB' => 0.0,
            'divRecord' => '8-4',
            'divGB' => 0.0,
            'homeRecord' => '18-7',
            'awayRecord' => '12-13',
            'gamesUnplayed' => 32,
        ]);
        $this->insertRow('ibl_power', [
            'TeamID' => $tid,
            'ranking' => 75.5,
            'last_win' => 7,
            'last_loss' => 3,
            'streak_type' => 'W',
            'streak' => 3,
            'sos' => 0.510,
            'remaining_sos' => 0.490,
        ]);
    }
}
