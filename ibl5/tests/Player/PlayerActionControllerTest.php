<?php

declare(strict_types=1);

namespace Tests\Player;

use Player\PlayerActionController;
use Player\Views\PlayerTradingCardFlipView;
use Repositories\Contracts\SalaryCapRepositoryInterface;
use Repositories\Contracts\TeamIdentityRepositoryInterface;
use Tests\WideUnit\Mocks\Season;
use Tests\WideUnit\WideUnitTestCase;

/**
 * Tests PlayerActionController: the negotiate and rookie-option page bodies
 * extracted from modules/Player/index.php.
 */
class PlayerActionControllerTest extends WideUnitTestCase
{
    private const NO_TEAM_ERROR_HTML = '<div class="ibl-alert ibl-alert--error">You do not have a team assigned.</div>'
        . '<a href="javascript:history.back()" class="ibl-btn ibl-btn--primary ibl-btn--spaced-inline">Go Back</a>';

    /**
     * Repository stub for a logged-in user with no team row.
     */
    private function teamlessRepo(): TeamIdentityRepositoryInterface
    {
        $repo = self::createStub(TeamIdentityRepositoryInterface::class);
        $repo->method('getTeamnameFromUsername')->willReturn(null);

        return $repo;
    }

    /**
     * Repository stub for a user who owns the given team.
     */
    private function ownerRepo(string $teamName): TeamIdentityRepositoryInterface
    {
        $repo = self::createStub(TeamIdentityRepositoryInterface::class);
        $repo->method('getTeamnameFromUsername')->willReturn($teamName);
        $repo->method('getTeamColorRow')->willReturn(['color1' => 'CE1141', 'color2' => '000000']);

        return $repo;
    }

    private function buildController(TeamIdentityRepositoryInterface $commonRepo, string $phase = 'Regular Season'): PlayerActionController
    {
        $season = new Season($this->mockDb);
        $season->phase = $phase;

        // Season\Season is class-aliased to the WideUnit mock under PHPUnit.
        return new PlayerActionController(
            $this->mockDb,
            $commonRepo,
            self::createStub(SalaryCapRepositoryInterface::class),
            $season,
        );
    }

    /**
     * Seed the queries Player::withPlayerID() and the trading card assembly run.
     *
     * @param array<string, mixed> $overrides Player row overrides
     */
    private function seedPlayer(array $overrides = []): void
    {
        $playerRow = array_merge([
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
            'exp' => 2, 'bird' => 3, 'cy' => 2, 'cyt' => 4,
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
        ], $overrides);

        // Player::withPlayerID
        $this->mockDb->onQuery('team_name AS teamname', [$playerRow]);
        // PlayerRepository all-star weekend counts
        $this->mockDb->onQuery('SUM.*CASE.*ibl_awards', [['allStar' => 2, 'threePoint' => 1, 'dunkContest' => 0, 'rookieSoph' => 1]]);
        // PlayerStats::withPlayerID
        $this->mockDb->onQuery('SELECT \* FROM.*ibl_plr.*WHERE pid', [$this->playerStatsRow()]);
        // Fallback: empty results for every other query
        $this->mockDb->setMockData([]);
    }

    /**
     * @return array<string, int|string>
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

    public function testRenderNegotiationTeamlessReturnsNoTeamErrorAndRunsNoQuery(): void
    {
        $controller = $this->buildController($this->teamlessRepo());

        $output = $controller->renderNegotiation(1, 'nobody', 'nuke_', false);

        $this->assertSame(self::NO_TEAM_ERROR_HTML, $output);
        $this->assertSame([], $this->getExecutedQueries());
    }

    public function testRenderNegotiationOwnedTeamDelegatesToService(): void
    {
        // The player is on another team, so NegotiationService stops at the ownership check
        // after rendering its own header: proof the call reached the service.
        $this->seedPlayer(['teamname' => 'Pistons']);
        $controller = $this->buildController($this->ownerRepo('Heat'));

        $output = $controller->renderNegotiation(1, 'testgm', 'nuke_', false);

        $this->assertStringNotContainsString('You do not have a team assigned.', $output);
        $this->assertStringContainsString('<h1 class="ibl-title">Contract Extension</h1>', $output);
        $this->assertStringContainsString('is not on your team.', $output);
    }

    public function testRenderRookieOptionTeamlessReturnsNoTeamError(): void
    {
        $this->seedPlayer();
        $controller = $this->buildController($this->teamlessRepo(), 'Free Agency');

        $output = $controller->renderRookieOption(1, 'nobody', null, null, null);

        $this->assertSame(self::NO_TEAM_ERROR_HTML, $output);
    }

    public function testRenderRookieOptionOwnershipFailureReturnsValidatorError(): void
    {
        $this->seedPlayer(['teamname' => 'Pistons']);
        $controller = $this->buildController($this->ownerRepo('Heat'), 'Free Agency');

        $output = $controller->renderRookieOption(1, 'testgm', null, null, null);

        $this->assertStringContainsString('ibl-alert--error', $output);
        $this->assertStringContainsString('Sorry, PG Test Player is not on your team.', $output);
        $this->assertStringContainsString('Go Back', $output);
        $this->assertStringNotContainsString('<form', $output);
    }

    public function testRenderRookieOptionEligibilityFailureReturnsValidatorError(): void
    {
        $this->seedPlayer(['draftround' => 3]);
        $controller = $this->buildController($this->ownerRepo('Heat'), 'Free Agency');

        $output = $controller->renderRookieOption(1, 'testgm', null, null, null);

        $this->assertStringContainsString('ibl-alert--error', $output);
        $this->assertStringContainsString('is not eligible for a rookie option.', $output);
        $this->assertStringContainsString('Go Back', $output);
        $this->assertStringNotContainsString('<form', $output);
    }

    public function testRenderRookieOptionEligibleRendersFormAndFlipStyles(): void
    {
        // Round 1, exp 2, Free Agency, salary_yr4 0: eligible. Final year is salary_yr3 (1200),
        // so the option is 2 x 1200 = 2400. salary_yr2 (1100) differs, which pins the column.
        $this->seedPlayer();
        $controller = $this->buildController($this->ownerRepo('Heat'), 'Free Agency');

        $output = $controller->renderRookieOption(1, 'testgm', null, null, 'team-page');

        $this->assertStringContainsString('Rookie Option Value: 2400', $output);
        $this->assertStringContainsString('<input type="hidden" name="rookieOptionValue" value="2400">', $output);
        $this->assertStringContainsString('<input type="hidden" name="from" value="team-page">', $output);
        $this->assertStringContainsString('card-flip-container', $output);

        $formPos = strpos($output, '<form name="RookieExtend"');
        $stylesPos = strpos($output, PlayerTradingCardFlipView::getFlipStyles());
        $this->assertNotFalse($formPos);
        $this->assertNotFalse($stylesPos);
        $this->assertLessThan($stylesPos, $formPos);
    }

    public function testRenderRookieOptionCardUsesTeamColors(): void
    {
        $this->seedPlayer();
        $controller = $this->buildController($this->ownerRepo('Heat'), 'Free Agency');

        $output = $controller->renderRookieOption(1, 'testgm', null, null, 'team-page');

        // The card's gradient endpoints carry color2 ('000000'); the gold default would carry '1e3a5f'.
        $card = substr($output, (int) strpos($output, 'card-flip-container'));
        $this->assertStringContainsStringIgnoringCase('--card-grad-start:#000000', $card);
        $this->assertStringNotContainsStringIgnoringCase('--card-grad-start:#1e3a5f', $card);
    }
}
