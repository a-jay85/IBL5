<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration\Team;

use PHPUnit\Framework\Attributes\Group;
use Tests\DatabaseIntegration\DatabaseTestCase;

/**
 * DB characterization pin for Team\TeamApiHandler split/display validation.
 *
 * Runs inside DatabaseTestCase's rolled-back transaction. headers_list() is
 * empty under the CLI SAPI, so the split validation is pinned through rendered
 * output identity rather than the HX-Push-Url header.
 */
#[Group('database')]
final class TeamApiHandlerSplitCharacterizationTest extends DatabaseTestCase
{
    private const TEAM_ID = 99;
    private const PID     = 200140101;
    private const PLAYER  = 'Preview Pinner';

    protected function setUp(): void
    {
        parent::setUp();

        // Three inserts copied verbatim from
        // TeamServicePageDataCharacterizationTest::seedCharTeam()
        $this->insertRow('ibl_team_info', [
            'teamid'      => self::TEAM_ID,
            'team_city'   => 'Testville',
            'team_name'   => 'CharTest',
            'color1'      => '102030',
            'color2'      => 'A0B0C0',
            'arena'       => 'Test Arena',
            'capacity'    => 18000,
            'owner_name'  => 'Test Owner',
            'owner_email' => 'owner@test.local',
            'gm_username' => 'char_gm',
        ]);

        $this->insertRow('ibl_standings', [
            'teamid'         => self::TEAM_ID,
            'team_name'      => 'CharTest',
            'pct'            => 0.600,
            'league_record'  => '12-8',
            'wins'           => 12,
            'losses'         => 8,
            'conference'     => 'CharTestConf',
            'conf_record'    => '7-5',
            'conf_gb'        => 0.0,
            'division'       => 'CharTestDiv',
            'div_record'     => '4-2',
            'div_gb'         => 0.0,
            'home_record'    => '8-2',
            'away_record'    => '4-6',
            'games_unplayed' => 62,
        ]);

        $this->insertRow('ibl_power', [
            'teamid'        => self::TEAM_ID,
            'ranking'       => 5,
            'last_win'      => 3,
            'last_loss'     => 1,
            'streak_type'   => 'W',
            'streak'        => 2,
            'sos'           => 0.500,
            'remaining_sos' => 0.510,
        ]);

        // cy (1) !== cyt (3) deliberately — survives both roster query paths
        $this->insertTestPlayer(self::PID, self::PLAYER, [
            'teamid'     => self::TEAM_ID,
            'cy'         => 1,
            'cyt'        => 3,
            'salary_yr1' => 100,
            'salary_yr2' => 100,
            'ordinal'    => 1,
        ]);

        $_GET = [];
    }

    protected function tearDown(): void
    {
        $_GET = [];
        parent::tearDown();
    }

    /**
     * @param array<string, string|list<string>> $get
     */
    private function render(array $get): string
    {
        $_GET = $get;
        ob_start();
        $handler = new \Team\TeamApiHandler($this->db);
        $handler->handle();
        return (string) ob_get_clean();
    }

    public function testValidSplitKeyRendersSplitTable(): void
    {
        $ratings = $this->render(['teamid' => '99', 'display' => 'ratings']);
        $split   = $this->render(['teamid' => '99', 'display' => 'split', 'split' => 'home']);

        self::assertNotSame($ratings, $split);
        self::assertStringContainsString('split:home', $split);
    }

    public function testInvalidSplitKeyRendersIdenticalHtmlToRatings(): void
    {
        $ratings = $this->render(['teamid' => '99', 'display' => 'ratings']);
        $split   = $this->render(['teamid' => '99', 'display' => 'split', 'split' => 'bogus_key']);

        self::assertSame($ratings, $split);
    }

    public function testMissingSplitKeyRendersIdenticalHtmlToRatings(): void
    {
        $ratings = $this->render(['teamid' => '99', 'display' => 'ratings']);
        $split   = $this->render(['teamid' => '99', 'display' => 'split']);

        self::assertSame($ratings, $split);
    }

    public function testArrayValuedSplitRendersIdenticalHtmlToRatings(): void
    {
        $ratings = $this->render(['teamid' => '99', 'display' => 'ratings']);
        $split   = $this->render(['teamid' => '99', 'display' => 'split', 'split' => ['home']]);

        self::assertSame($ratings, $split);
    }

    public function testSplitIsIgnoredForNonSplitDisplay(): void
    {
        $withSplit    = $this->render(['teamid' => '99', 'display' => 'total_s', 'split' => 'home']);
        $withoutSplit = $this->render(['teamid' => '99', 'display' => 'total_s']);

        self::assertSame($withoutSplit, $withSplit);
    }
}
