<?php

declare(strict_types=1);

namespace Tests\Player;

use Http\HttpRequest;
use Player\Contracts\PlayerRepositoryInterface;
use Player\PlayerPageController;
use Player\PlayerPageService;
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

        $this->stubRepo = self::createStub(TeamIdentityRepositoryInterface::class);
        $this->stubRepo->method('getTeamnameFromUsername')->willReturn('Heat');
        $this->stubRepo->method('getTeamnameFromTeamID')->willReturn('Heat');
        $this->stubRepo->method('getTeamColorRow')->willReturn(['color1' => 'CE1141', 'color2' => '000000']);

        $this->seedInvariantQueries();
        $this->controller = $this->buildController();
    }

    /**
     * Build the controller under test with an explicit request snapshot, so each
     * test supplies its own input instead of mutating global state.
     */
    private function buildController(?HttpRequest $request = null, ?PlayerRepositoryInterface $playerRepository = null, ?\Closure $playerLoader = null): PlayerPageController
    {
        return new PlayerPageController(
            $this->mockDb,
            $this->stubRepo,
            new PlayerPageService($this->mockDb, $this->stubRepo),
            $request ?? new HttpRequest(),
            null,
            $playerRepository,
            $playerLoader,
        );
    }

    private function seedInvariantQueries(int $playerTeamid = 5): void
    {
        $playerRow = [
            'pid' => 1, 'ordinal' => 1, 'name' => 'Test Player', 'nickname' => null,
            'age' => 25, 'teamid' => $playerTeamid, 'pos' => 'PG',
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

    public function testRenderPageFreeAgentKeepsGoldTradingCardAndTeamZeroPageColors(): void
    {
        $this->seedInvariantQueries(0);
        $this->stubRepo = self::createStub(TeamIdentityRepositoryInterface::class);
        $this->stubRepo->method('getTeamnameFromUsername')->willReturn('Free Agents');
        $this->stubRepo->method('getTeamColorRow')->willReturn(['color1' => '888888', 'color2' => 'cccccc']);

        $html = $this->buildController()->renderPage(1, null, 'nobody');

        // Menu and stats cards use the unguarded team-0 row (gray); the trading card stays gold.
        $this->assertStringContainsStringIgnoringCase('888888', $html);
        $card = substr($html, (int) strpos($html, 'card-flip-container'), 600);
        $this->assertStringContainsStringIgnoringCase('--card-grad-start:#1e3a5f', $card);
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

        $this->stubRepo = self::createStub(TeamIdentityRepositoryInterface::class);
        $this->stubRepo->method('getTeamnameFromUsername')->willReturn('Free Agents');
        $this->stubRepo->method('getTeamColorRow')->willReturn(['color1' => 'D4AF37', 'color2' => '1e3a5f']);

        $controller = $this->buildController();
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
        $controller = $this->buildController(new HttpRequest(get: ['result' => 'rookie_option_success']));
        $html = $controller->renderPage(1, null, 'testuser');

        $this->assertStringContainsString('Rookie option has been exercised successfully', $html);
    }

    // ── Result-banner branches ──────────────────────────────────────

    public function testRenderPageShowsEmailFailedBanner(): void
    {
        $controller = $this->buildController(new HttpRequest(get: ['result' => 'email_failed']));
        $html = $controller->renderPage(1, null, 'testuser');

        $this->assertStringContainsString('notification email failed to send', $html);
        $this->assertStringContainsString('ibl-alert--warning', $html);
    }

    public function testRenderPageShowsNoBannerForUnknownResultCode(): void
    {
        $controller = $this->buildController(new HttpRequest(get: ['result' => 'unknown_code_xyz']));
        $html = $controller->renderPage(1, null, 'testuser');

        $this->assertStringNotContainsString('ibl-alert', $html);
    }

    // ── Result-parameter characterization (14.8 equivalence harness) ─
    //
    // These three pin the accepted-value set of the `result` parameter. They were
    // written against the raw superglobal read and assert identically now that the
    // read moves behind Http\HttpRequest — only how the input is supplied changed.

    public function testRenderPageShowsRookieOptionBannerWhenResultParameterPresent(): void
    {
        $controller = $this->buildController(new HttpRequest(get: ['result' => 'rookie_option_success']));
        $html = $controller->renderPage(1, null, 'testuser');

        $this->assertStringContainsString('Rookie option has been exercised successfully', $html);
    }

    public function testRenderPageShowsNoBannerWhenResultParameterIsArray(): void
    {
        // `?result[]=rookie_option_success` yields an array — the is_string() guard
        // at the call site must reject it.
        $controller = $this->buildController(new HttpRequest(get: ['result' => ['rookie_option_success']]));
        $html = $controller->renderPage(1, null, 'testuser');

        $this->assertStringNotContainsString('Rookie option has been exercised successfully', $html);
    }

    public function testRenderPageShowsNoBannerWhenResultParameterAbsent(): void
    {
        $controller = $this->buildController(new HttpRequest());
        $html = $controller->renderPage(1, null, 'testuser');

        $this->assertStringNotContainsString('Rookie option has been exercised successfully', $html);
        $this->assertStringNotContainsString('notification email failed to send', $html);
    }

    // ── Default-fallback branch (invalid pageView integer) ──────────

    public function testRenderPageDefaultFallbackForInvalidPageViewActivePlayer(): void
    {
        // pageView=999 matches no PlayerPageType constant → falls through to
        // the default-fallback block at PlayerPageController.php line 264.
        // Active player (retired=0) → renderOverview path.
        $html = $this->controller->renderPage(1, 999, 'testuser');

        $this->assertStringContainsString('Test Player', $html);
        $this->assertStringContainsString('Game Log', $html);
    }

    public function testRenderPageDefaultFallbackForInvalidPageViewRetiredPlayer(): void
    {
        // Mirror the retired-player mock setup from testRenderPageOverviewForRetiredPlayer
        // (line 137), then pass pageView=999 to reach the default-fallback block
        // at PlayerPageController.php line 265–273 (retired branch → Regular Season view).
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

        $this->mockDb = new \Tests\WideUnit\Mocks\MockDatabase();
        $this->injectGlobalMockDb();
        $this->mockDb->onQuery('team_name AS teamname', [$playerWithRetired]);
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
        $this->mockDb->onQuery('ibl_hist', [[
            'pid' => 1, 'year' => 2020, 'teamid' => 5, 'team' => 'Heat',
            'games' => 55, 'minutes' => 1500,
            'fgm' => 400, 'fga' => 800, 'ftm' => 200, 'fta' => 250,
            'tgm' => 100, 'tga' => 280,
            'orb' => 50, 'reb' => 200, 'ast' => 300, 'stl' => 80,
            'tvr' => 120, 'blk' => 40, 'pf' => 130, 'pts' => 1100,
            'salary' => 1000,
        ]]);
        $this->mockDb->setMockData([$retiredRow]);

        $stubRepo = self::createStub(TeamIdentityRepositoryInterface::class);
        $stubRepo->method('getTeamnameFromUsername')->willReturn('Free Agents');
        $stubRepo->method('getTeamColorRow')->willReturn(['color1' => 'D4AF37', 'color2' => '1e3a5f']);
        $controller = new PlayerPageController($this->mockDb, $stubRepo, new PlayerPageService($this->mockDb, $stubRepo), new HttpRequest());

        $html = $controller->renderPage(1, 999, 'nobody');

        $this->assertStringContainsString('Retired Player', $html);
        $this->assertStringContainsString('Regular Season', $html);
        // Confirm NOT the active-player overview path (no Game Log for retired fallback)
        $this->assertStringNotContainsString('Game Log', $html);
    }

    private const SAMPLE_UUID = '123e4567-e89b-42d3-a456-426614174000';

    public function testShowPageResolvesUuidViaRepository(): void
    {
        $repository = $this->createMock(PlayerRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('getPlayerIdByUuid')
            ->with(self::SAMPLE_UUID)
            ->willReturn(1);
        $controller = $this->buildController(null, $repository);

        $html = $controller->showPage(self::SAMPLE_UUID, null, '');

        $this->assertSame($this->controller->renderPage(1, null, ''), $html);
    }

    public function testShowPageNumericIdSkipsRepository(): void
    {
        $repository = $this->createMock(PlayerRepositoryInterface::class);
        $repository->expects($this->never())->method('getPlayerIdByUuid');
        $controller = $this->buildController(null, $repository);

        $html = $controller->showPage('1', null, '');

        $this->assertSame($this->controller->renderPage(1, null, ''), $html);
    }

    public function testShowPageUnknownUuidFallsBackToIntCast(): void
    {
        $repository = $this->createMock(PlayerRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('getPlayerIdByUuid')
            ->with(self::SAMPLE_UUID)
            ->willReturn(null);
        $controller = $this->buildController(null, $repository);

        $html = $controller->showPage(self::SAMPLE_UUID, null, '');

        $this->assertSame($this->controller->renderPage(0, null, ''), $html);
    }

    public function testShowPageNullPageViewPassesNull(): void
    {
        $html = $this->controller->showPage('1', null, 'testuser');

        $this->assertSame($this->controller->renderPage(1, null, 'testuser'), $html);
        $this->assertStringContainsString('Game Log', $html);
    }

    public function testShowPageCastsPageViewToInt(): void
    {
        $html = $this->controller->showPage('1', '3', 'testuser');

        $this->assertSame($this->controller->renderPage(1, 3, 'testuser'), $html);
        $this->assertNotSame($this->controller->renderPage(1, null, 'testuser'), $html);
    }

    public function testShowPageUnknownPidReturnsNotFoundPanelWith404(): void
    {
        $this->mockDb->onQuery('team_name AS teamname', []);

        $html = $this->controller->showPage('99999999|0|4040454', null, '');

        $this->assertSame(404, $this->controller->responseStatus());
        $this->assertStringContainsString('Player Not Found', $html);
    }

    public function testShowPageZeroPidReturnsNotFound(): void
    {
        $this->mockDb->onQuery('team_name AS teamname', []);

        $html = $this->controller->showPage('0', null, '');

        $this->assertSame(404, $this->controller->responseStatus());
        $this->assertStringContainsString('Player Not Found', $html);
    }

    public function testShowPageNegativePidReturnsNotFound(): void
    {
        $this->mockDb->onQuery('team_name AS teamname', []);

        $html = $this->controller->showPage('-5', null, '');

        $this->assertSame(404, $this->controller->responseStatus());
        $this->assertStringContainsString('Player Not Found', $html);
    }

    public function testShowPageValidPidKeepsStatus200(): void
    {
        $html = $this->controller->showPage('1', null, '');

        $this->assertSame(200, $this->controller->responseStatus());
        $this->assertStringNotContainsString('Player Not Found', $html);
    }

    public function testNotFoundBodyIsIdenticalForDifferentHostilePids(): void
    {
        $this->mockDb->onQuery('team_name AS teamname', []);

        $first = $this->buildController()->showPage('99999999|0|4040454', null, '');
        $second = $this->buildController()->showPage('<script>alert(1)</script>', null, '');

        $this->assertSame($first, $second);
        foreach ([$first, $second] as $body) {
            $this->assertStringNotContainsString('99999999', $body);
            $this->assertStringNotContainsString('4040454', $body);
            $this->assertStringNotContainsString('<script>', $body);
        }
    }

    public function testRenderPagePropagatesNonNotFoundRuntimeException(): void
    {
        $controller = $this->buildController(
            null,
            null,
            static function (int $pid): never {
                throw new \RuntimeException('connection lost');
            },
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('connection lost');

        $controller->renderPage(1, null, '');
    }
}
