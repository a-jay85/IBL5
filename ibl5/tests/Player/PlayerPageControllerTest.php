<?php

declare(strict_types=1);

namespace Tests\Player;

use Player\PlayerPageController;
use Player\PlayerPageType;
use Repositories\Contracts\TeamIdentityRepositoryInterface;
use Tests\WideUnit\WideUnitTestCase;

class PlayerPageControllerTest extends WideUnitTestCase
{
    private TeamIdentityRepositoryInterface $stubRepo;
    private PlayerPageController $controller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stubRepo = $this->createStub(TeamIdentityRepositoryInterface::class);
        $this->stubRepo->method('getTeamnameFromUsername')->willReturn('Heat');
        $this->stubRepo->method('getTeamnameFromTeamID')->willReturn('Heat');

        $this->seedInvariantQueries();
        $this->controller = new PlayerPageController($this->mockDb, $this->stubRepo);
    }

    protected function tearDown(): void
    {
        unset($_GET['result']);
        parent::tearDown();
    }

    private function seedInvariantQueries(): void
    {
        $playerRow = [
            'pid' => 1, 'ordinal' => 1, 'name' => 'Test Player', 'nickname' => null,
            'age' => 25, 'teamid' => 5, 'pos' => 'PG',
            'r_fga' => 70, 'r_fgp' => 50, 'r_fta' => 60, 'r_ftp' => 80,
            'r_3ga' => 40, 'r_3gp' => 35, 'r_orb' => 30, 'r_drb' => 50,
            'r_ast' => 60, 'r_stl' => 50, 'r_tvr' => 40, 'r_blk' => 30,
            'r_foul' => 40, 'oo' => 70, 'od' => 60, 'r_drive_off' => 65,
            'dd' => 55, 'po' => 50, 'pd' => 45, 'r_trans_off' => 70,
            'td' => 60, 'clutch' => 75, 'consistency' => 80,
            'talent' => 85, 'skill' => 80, 'intangibles' => 70,
            'loyalty' => 50, 'playing_time' => 60, 'winner' => 40,
            'tradition' => 30, 'security' => 55,
            'exp' => 3, 'bird' => 3, 'cy' => 2, 'cyt' => 4,
            'salary_yr1' => 1000, 'salary_yr2' => 1100, 'salary_yr3' => 1200,
            'salary_yr4' => 0, 'salary_yr5' => 0, 'salary_yr6' => 0,
            'draftyear' => 2021, 'draftround' => 1, 'draftpickno' => 5,
            'draftedby' => 'Heat', 'draftedbycurrentname' => 'Heat',
            'college' => 'State U',
            'htft' => 6, 'htin' => 3, 'wt' => 195,
            'injured' => 0, 'retired' => 0, 'droptime' => 0,
            'teamname' => 'Heat', 'color1' => 'CE1141', 'color2' => '000000',
            'rookie_option_used' => 0, 'uuid' => 'test-uuid-123',
            'gm_username' => 'testgm',
        ];

        $teamRow = [
            'teamid' => 5, 'team_city' => 'Miami', 'team_name' => 'Heat',
            'color1' => 'CE1141', 'color2' => '000000',
            'arena' => 'Test Arena', 'capacity' => 19600,
            'owner_name' => 'Owner', 'owner_email' => 'owner@test.com',
            'discord_id' => null, 'used_extension_this_chunk' => 0,
            'used_extension_this_season' => 0, 'has_mle' => 0, 'has_lle' => 0,
            'league_record' => '30-20', 'uuid' => 'team-uuid-5',
            'gm_username' => 'testgm',
        ];

        // Player::withPlayerID — matches the LEFT JOIN pattern
        $this->mockDb->onQuery('team_name AS teamname', [$playerRow]);

        // TeamColorHelper::getTeamColors — matches the color SELECT pattern
        $this->mockDb->onQuery('SELECT color1, color2', [['color1' => 'CE1141', 'color2' => '000000']]);

        // PlayerRepository::getAllStarWeekendCounts (SUM(CASE) query)
        $this->mockDb->onQuery('SUM.*CASE.*ibl_awards', [['allStar' => 2, 'threePoint' => 1, 'dunkContest' => 0, 'rookieSoph' => 1]]);

        // PlayerRepository::getAwards (individual award rows) — return empty for most tests
        $this->mockDb->onQuery('ibl_awards.*WHERE name', []);

        // Team::initialize — matches standings join pattern
        $this->mockDb->onQuery('ibl_standings', [$teamRow]);

        // PlayerStats::withPlayerID — matches the plain ibl_plr SELECT without JOIN
        $this->mockDb->onQuery('SELECT \* FROM.*ibl_plr.*WHERE pid', [$this->playerStatsRow()]);

        // Fallback: empty results for all other queries (box scores, hist, etc.)
        $this->mockDb->setMockData([]);
    }

    /**
     * @return array{pid: int, name: string, pos: string, retired: int, stats_gs: int, stats_gm: int, stats_min: int, stats_fgm: int, stats_fga: int, stats_ftm: int, stats_fta: int, stats_3gm: int, stats_3ga: int, stats_orb: int, stats_drb: int, stats_ast: int, stats_stl: int, stats_tvr: int, stats_blk: int, stats_pf: int, sh_pts: int, sh_reb: int, sh_ast: int, sh_stl: int, sh_blk: int, s_dd: int, s_td: int, sp_pts: int, sp_reb: int, sp_ast: int, sp_stl: int, sp_blk: int, ch_pts: int, ch_reb: int, ch_ast: int, ch_stl: int, ch_blk: int, c_dd: int, c_td: int, cp_pts: int, cp_reb: int, cp_ast: int, cp_stl: int, cp_blk: int, car_gm: int, car_min: int, car_fgm: int, car_fga: int, car_ftm: int, car_fta: int, car_3gm: int, car_3ga: int, car_orb: int, car_drb: int, car_reb: int, car_ast: int, car_stl: int, car_tvr: int, car_blk: int, car_pf: int}
     */
    private function playerStatsRow(): array
    {
        return [
            'pid' => 1, 'name' => 'Test Player', 'pos' => 'PG', 'retired' => 0,
            'stats_gs' => 50, 'stats_gm' => 55, 'stats_min' => 1500,
            'stats_fgm' => 400, 'stats_fga' => 800,
            'stats_ftm' => 200, 'stats_fta' => 250,
            'stats_3gm' => 100, 'stats_3ga' => 280,
            'stats_orb' => 50, 'stats_drb' => 150,
            'stats_ast' => 300, 'stats_stl' => 80,
            'stats_tvr' => 120, 'stats_blk' => 40, 'stats_pf' => 130,
            'sh_pts' => 42, 'sh_reb' => 15, 'sh_ast' => 18,
            'sh_stl' => 6, 'sh_blk' => 5,
            's_dd' => 20, 's_td' => 5,
            'sp_pts' => 38, 'sp_reb' => 12, 'sp_ast' => 14,
            'sp_stl' => 4, 'sp_blk' => 3,
            'ch_pts' => 50, 'ch_reb' => 18, 'ch_ast' => 20,
            'ch_stl' => 8, 'ch_blk' => 7,
            'c_dd' => 80, 'c_td' => 18,
            'cp_pts' => 45, 'cp_reb' => 16, 'cp_ast' => 18,
            'cp_stl' => 6, 'cp_blk' => 5,
            'car_gm' => 300, 'car_min' => 9000,
            'car_fgm' => 2000, 'car_fga' => 4200,
            'car_ftm' => 1000, 'car_fta' => 1250,
            'car_3gm' => 500, 'car_3ga' => 1400,
            'car_orb' => 300, 'car_drb' => 700, 'car_reb' => 1000,
            'car_ast' => 1800, 'car_stl' => 400,
            'car_tvr' => 600, 'car_blk' => 200, 'car_pf' => 600,
        ];
    }

    public function testRenderPageReturnsHtmlForActivePlayer(): void
    {
        $html = $this->controller->renderPage(1, null, 'testuser');

        $this->assertStringContainsString('Test Player', $html);
        $this->assertStringContainsString('card-flip-container', $html);
    }

    public function testRenderPageOverviewForRetiredPlayer(): void
    {
        $retiredRow = array_merge($this->playerStatsRow(), ['retired' => 1]);
        $playerWithRetired = [
            'pid' => 1, 'ordinal' => 1, 'name' => 'Retired Player', 'nickname' => null,
            'age' => 40, 'teamid' => 0, 'pos' => 'SG',
            'r_fga' => 70, 'r_fgp' => 50, 'r_fta' => 60, 'r_ftp' => 80,
            'r_3ga' => 40, 'r_3gp' => 35, 'r_orb' => 30, 'r_drb' => 50,
            'r_ast' => 60, 'r_stl' => 50, 'r_tvr' => 40, 'r_blk' => 30,
            'r_foul' => 40, 'oo' => 70, 'od' => 60, 'r_drive_off' => 65,
            'dd' => 55, 'po' => 50, 'pd' => 45, 'r_trans_off' => 70,
            'td' => 60, 'clutch' => 75, 'consistency' => 80,
            'talent' => 85, 'skill' => 80, 'intangibles' => 70,
            'loyalty' => 50, 'playing_time' => 60, 'winner' => 40,
            'tradition' => 30, 'security' => 55,
            'exp' => 15, 'bird' => 0, 'cy' => 0, 'cyt' => 0,
            'salary_yr1' => 0, 'salary_yr2' => 0, 'salary_yr3' => 0,
            'salary_yr4' => 0, 'salary_yr5' => 0, 'salary_yr6' => 0,
            'draftyear' => 2010, 'draftround' => 1, 'draftpickno' => 3,
            'draftedby' => 'Heat', 'draftedbycurrentname' => 'Heat',
            'college' => 'U of Test',
            'htft' => 6, 'htin' => 5, 'wt' => 210,
            'injured' => 0, 'retired' => 1, 'droptime' => 0,
            'teamname' => 'Free Agents', 'color1' => 'D4AF37', 'color2' => '1e3a5f',
            'rookie_option_used' => 0, 'uuid' => 'retired-uuid',
            'gm_username' => '',
        ];

        $histRow = [
            'pid' => 1, 'year' => 2020, 'teamid' => 5, 'team' => 'Heat',
            'games' => 55, 'minutes' => 1500,
            'fgm' => 400, 'fga' => 800, 'ftm' => 200, 'fta' => 250,
            'tgm' => 100, 'tga' => 280,
            'orb' => 50, 'reb' => 200, 'ast' => 300, 'stl' => 80,
            'tvr' => 120, 'blk' => 40, 'pf' => 130, 'pts' => 1100,
            'salary' => 1000,
        ];

        // Reset mock and re-seed with retired player
        $this->mockDb = new \Tests\WideUnit\Mocks\MockDatabase();
        $this->injectGlobalMockDb();
        $this->mockDb->onQuery('team_name AS teamname', [$playerWithRetired]);
        $this->mockDb->onQuery('SELECT color1, color2', [['color1' => 'D4AF37', 'color2' => '1e3a5f']]);
        $this->mockDb->onQuery('SUM.*CASE.*ibl_awards', [['allStar' => 0, 'threePoint' => 0, 'dunkContest' => 0, 'rookieSoph' => 0]]);
        $this->mockDb->onQuery('ibl_awards.*WHERE name', []);
        $this->mockDb->onQuery('ibl_standings', [[
            'teamid' => 0, 'team_city' => '', 'team_name' => 'Free Agents',
            'color1' => 'D4AF37', 'color2' => '1e3a5f',
            'arena' => '', 'capacity' => 0,
            'owner_name' => '', 'owner_email' => '',
            'discord_id' => null, 'used_extension_this_chunk' => 0,
            'used_extension_this_season' => 0, 'has_mle' => 0, 'has_lle' => 0,
            'league_record' => '0-0', 'uuid' => 'fa-uuid',
            'gm_username' => '',
        ]]);
        $this->mockDb->onQuery('ibl_hist', [$histRow]);
        $this->mockDb->setMockData([$retiredRow]);

        $this->stubRepo = $this->createStub(TeamIdentityRepositoryInterface::class);
        $this->stubRepo->method('getTeamnameFromUsername')->willReturn('Free Agents');

        $controller = new PlayerPageController($this->mockDb, $this->stubRepo);
        $html = $controller->renderPage(1, null, 'nobody');

        $this->assertStringContainsString('Retired Player', $html);
        $this->assertStringContainsString('card-flip-container', $html);
    }

    public function testRenderPageSimStats(): void
    {
        $html = $this->controller->renderPage(1, PlayerPageType::SIM_STATS, 'testuser');

        $this->assertStringContainsString('Test Player', $html);
    }

    public function testRenderPageRegularSeasonAverages(): void
    {
        $html = $this->controller->renderPage(1, PlayerPageType::REGULAR_SEASON_AVERAGES, 'testuser');

        $this->assertStringContainsString('card-flip-container', $html);
        $this->assertStringContainsString('Regular Season', $html);
    }

    public function testRenderPageRegularSeasonTotals(): void
    {
        $html = $this->controller->renderPage(1, PlayerPageType::REGULAR_SEASON_TOTALS, 'testuser');

        $this->assertStringContainsString('Regular Season', $html);
    }

    public function testRenderPagePlayoffAverages(): void
    {
        $html = $this->controller->renderPage(1, PlayerPageType::PLAYOFF_AVERAGES, 'testuser');

        $this->assertStringContainsString('Playoffs', $html);
    }

    public function testRenderPagePlayoffTotals(): void
    {
        $html = $this->controller->renderPage(1, PlayerPageType::PLAYOFF_TOTALS, 'testuser');

        $this->assertStringContainsString('Playoffs', $html);
    }

    public function testRenderPageHeatAverages(): void
    {
        $html = $this->controller->renderPage(1, PlayerPageType::HEAT_AVERAGES, 'testuser');

        $this->assertStringContainsString('H.E.A.T.', $html);
    }

    public function testRenderPageHeatTotals(): void
    {
        $html = $this->controller->renderPage(1, PlayerPageType::HEAT_TOTALS, 'testuser');

        $this->assertStringContainsString('H.E.A.T.', $html);
    }

    public function testRenderPageOlympicAverages(): void
    {
        $html = $this->controller->renderPage(1, PlayerPageType::OLYMPIC_AVERAGES, 'testuser');

        $this->assertStringContainsString('Olympics', $html);
    }

    public function testRenderPageOlympicTotals(): void
    {
        $html = $this->controller->renderPage(1, PlayerPageType::OLYMPIC_TOTALS, 'testuser');

        $this->assertStringContainsString('Olympics', $html);
    }

    public function testRenderPageRatingsAndSalary(): void
    {
        $html = $this->controller->renderPage(1, PlayerPageType::RATINGS_AND_SALARY, 'testuser');

        $this->assertStringContainsString('Test Player', $html);
    }

    public function testRenderPageAwardsAndNews(): void
    {
        $html = $this->controller->renderPage(1, PlayerPageType::AWARDS_AND_NEWS, 'testuser');

        $this->assertStringContainsString('Test Player', $html);
    }

    public function testRenderPageOneOnOne(): void
    {
        $html = $this->controller->renderPage(1, PlayerPageType::ONE_ON_ONE, 'testuser');

        $this->assertStringContainsString('Test Player', $html);
    }

    public function testRenderPageDefaultFallback(): void
    {
        // Using PlayerPageType::OVERVIEW (null) explicitly
        $html = $this->controller->renderPage(1, PlayerPageType::OVERVIEW, 'testuser');

        $this->assertStringContainsString('Test Player', $html);
        $this->assertStringContainsString('Game Log', $html);
    }

    public function testRenderPageShowsResultBanner(): void
    {
        $_GET['result'] = 'rookie_option_success';
        $html = $this->controller->renderPage(1, null, 'testuser');

        $this->assertStringContainsString('Rookie option has been exercised successfully', $html);
    }
}
