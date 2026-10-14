<?php

declare(strict_types=1);

namespace Tests\BasketballStats\Tables;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use BasketballStats\Tables\SeasonAverages;
use Tests\WideUnit\Mocks\MockDatabase;

/**
 * Goldens under fixtures/ were captured once from the unmodified class. Never
 * regenerate them to make a failing test pass. The one-off capture method
 * (testCaptureGoldensOnce) was deleted before the pin commit.
 *
 * @covers \BasketballStats\Tables\SeasonAverages
 */
#[AllowMockObjectsWithoutExpectations]
class SeasonAveragesTest extends TestCase
{
    private function createMockTeam(): \Team\Team
    {
        $team = $this->createMock(\Team\Team::class);
        $team->color1 = 'FF0000';
        $team->color2 = '0000FF';
        $team->teamid = 1;
        $team->name = 'Test Team';
        return $team;
    }

    private function createMockDb(): \mysqli
    {
        $mockResult = self::createStub(\mysqli_result::class);
        $mockResult->method('fetch_assoc')->willReturn(null);

        $mockStmt = self::createStub(\mysqli_stmt::class);
        $mockStmt->method('execute')->willReturn(true);
        $mockStmt->method('bind_param')->willReturn(true);
        $mockStmt->method('get_result')->willReturn($mockResult);

        $mockDb = self::createStub(\mysqli::class);
        $mockDb->method('prepare')->willReturn($mockStmt);

        return $mockDb;
    }

    /**
     * Characterization pin: render() with no $ariaLabel arg emits table open tag with NO aria-label
     */
    public function testTableOpenTagHasNoAriaLabel(): void
    {
        $html = SeasonAverages::render($this->createMockDb(), [], $this->createMockTeam(), '', [], '');

        $this->assertStringContainsString('<table class="ibl-data-table team-table responsive-table sortable"', $html);
        $this->assertStringNotContainsString('aria-label', $html);
    }

    private function makeTeam(string $name = 'Test Team', string $color1 = 'FF0000'): \Team\Team
    {
        $team = self::createStub(\Team\Team::class);
        $team->color1 = $color1;
        $team->color2 = '0000FF';
        $team->teamid = 7;
        $team->name = $name;

        return $team;
    }

    /**
     * @return array<string, string>
     */
    private static function bothStatsRow(int $games): array
    {
        $offense = [
            'teamID' => '7', 'name' => 'Test Team', 'games' => (string) $games,
            'fgm' => '400', 'fga' => '850', 'ftm' => '200', 'fta' => '260',
            'tgm' => '120', 'tga' => '330', 'orb' => '95', 'reb' => '420',
            'ast' => '240', 'stl' => '70', 'tvr' => '130', 'blk' => '45', 'pf' => '190',
        ];
        $defense = [
            'teamID' => '7', 'name' => 'Test Team', 'games' => (string) $games,
            'fgm' => '380', 'fga' => '900', 'ftm' => '170', 'fta' => '230',
            'tgm' => '105', 'tga' => '310', 'orb' => '88', 'reb' => '400',
            'ast' => '215', 'stl' => '62', 'tvr' => '142', 'blk' => '39', 'pf' => '205',
        ];

        $row = [];
        foreach ($offense as $key => $value) {
            $row['tos_' . $key] = $value;
        }
        foreach ($defense as $key => $value) {
            $row['tds_' . $key] = $value;
        }

        return $row;
    }

    /**
     * A null row set registers no route, so that repository call returns no row.
     *
     * @param list<array<string, string>>|null $regularRows
     * @param list<array<string, string>>|null $fallbackRows
     */
    private function makeDb(?array $regularRows, ?array $fallbackRows, int $year): MockDatabase
    {
        $db = new MockDatabase();
        if ($regularRows !== null) {
            // regular-season call only (contains the game_type clause)
            $db->onQuery('bst\.game_type = 1 AND bst\.season_year = ' . $year, $regularRows);
        }
        if ($fallbackRows !== null) {
            // fallback call only (negative lookahead rejects the regular-season SQL; [\s\S] spans newlines)
            $db->onQuery('^(?![\s\S]*game_type = 1)[\s\S]*bst\.season_year = ' . $year, $fallbackRows);
        }

        return $db;
    }

    /**
     * Every key PlayerStats::fill() reads from an ibl_plr row. Stat values are strings, as mysqli returns them; pid and retired are ints, as the repository row carries them.
     *
     * @param array<string, string|int> $overrides
     * @return array<string, string|int>
     */
    private static function plrStatsRow(int $pid, array $overrides = []): array
    {
        $zeroKeys = [
            'c_dd', 'c_td', 'car_3ga', 'car_3gm', 'car_ast', 'car_blk', 'car_drb', 'car_fga', 'car_fgm',
            'car_fta', 'car_ftm', 'car_gm', 'car_min', 'car_orb', 'car_pf', 'car_reb', 'car_stl', 'car_tvr',
            'ch_ast', 'ch_blk', 'ch_pts', 'ch_reb', 'ch_stl', 'cp_ast', 'cp_blk', 'cp_pts', 'cp_reb', 'cp_stl',
            's_dd', 's_td', 'sh_ast', 'sh_blk', 'sh_pts', 'sh_reb', 'sh_stl', 'sp_ast', 'sp_blk', 'sp_pts',
            'sp_reb', 'sp_stl',
        ];
        $row = array_fill_keys($zeroKeys, '0');
        $row += [
            'pid' => $pid, 'name' => 'Row ' . $pid, 'pos' => 'G', 'retired' => 0,
            'stats_gm' => '20', 'stats_gs' => '15', 'stats_min' => '640',
            'stats_fgm' => '140', 'stats_fga' => '300', 'stats_ftm' => '60', 'stats_fta' => '75',
            'stats_3gm' => '30', 'stats_3ga' => '80', 'stats_orb' => '25', 'stats_drb' => '95',
            'stats_ast' => '90', 'stats_stl' => '22', 'stats_tvr' => '40', 'stats_blk' => '12', 'stats_pf' => '48',
        ];

        return array_merge($row, $overrides);
    }

    private function makePlayer(
        int $pid,
        string $name,
        string $pos,
        int $teamid,
        string $teamName,
        ?string $color1,
        ?string $color2,
        string $statusClass = ''
    ): \Player\Player {
        $player = self::createStub(\Player\Player::class);
        $player->method('getPlayerID')->willReturn($pid);
        $player->method('getName')->willReturn($name);
        $player->method('getDecoratedName')->willReturn($name);
        $player->method('getNameStatusClass')->willReturn($statusClass);
        $player->method('getPosition')->willReturn($pos);
        $player->method('getTeamid')->willReturn($teamid);
        $player->method('getTeamName')->willReturn($teamName);
        $player->method('getTeamColor1')->willReturn($color1);
        $player->method('getTeamColor2')->willReturn($color2);

        return $player;
    }

    /** @return list<\Player\Player> */
    private function playerPair(): array
    {
        return [
            $this->makePlayer(11, 'Alpha Starter', 'PG', 7, 'Test Team', 'FF0000', '0000FF', 'is-injured'),
            $this->makePlayer(12, 'Bravo Bench', 'C', 0, '', null, null),
        ];
    }

    /** @return list<\Player\Player> */
    private function hostilePlayers(): array
    {
        return [
            $this->makePlayer(13, "O'Brien <script>alert(1)</script>", '<b>PG</b>', 7, '"><img src=x>', '"><img src=x>', '0000FF'),
        ];
    }

    private function makePlayerDb(bool $withFooterStats = true): MockDatabase
    {
        $db = $this->makeDb($withFooterStats ? [self::bothStatsRow(10)] : null, $withFooterStats ? [self::bothStatsRow(11)] : null, 2024);
        $db->onQuery('FROM ibl_plr WHERE pid = 11 LIMIT 1', [self::plrStatsRow(11)]);
        $db->onQuery('FROM ibl_plr WHERE pid = 12 LIMIT 1', [self::plrStatsRow(12, ['stats_gm' => '3', 'stats_gs' => '0', 'stats_min' => '41'])]);
        $db->onQuery('FROM ibl_plr WHERE pid = 13 LIMIT 1', [self::plrStatsRow(13)]);

        return $db;
    }

    /**
     * @return array<string, array{
     *     0: string,
     *     1: string,
     *     2: string,
     *     3: string,
     *     4: string,
     *     5: list<array<string, string>>|null,
     *     6: list<array<string, string>>|null,
     *     7: int
     * }>
     */
    public static function goldenCases(): array
    {
        return [
            'explicit-year' => [
                'explicit-year', '2023', 'Season totals', 'Test Team', 'FF0000',
                [self::bothStatsRow(10)], [self::bothStatsRow(11)], 2023,
            ],
            'mock-year' => [
                'mock-year', '', '', 'Test Team', 'FF0000',
                [self::bothStatsRow(10)], [self::bothStatsRow(11)], 2024,
            ],
            'fallback' => [
                'fallback', '2023', '', 'Test Team', 'FF0000',
                null, [self::bothStatsRow(11)], 2023,
            ],
            'no-stats' => [
                'no-stats', '2023', '', 'Test Team', 'FF0000',
                null, null, 2023,
            ],
            'hostile' => [
                'hostile', '2023', '"><script>alert(1)</script>', "O'Brien <b>Hack</b>", '"><img src=x>',
                [self::bothStatsRow(10)], null, 2023,
            ],
        ];
    }

    /**
     * @param list<array<string, string>>|null $regularRows
     * @param list<array<string, string>>|null $fallbackRows
     */
    private function renderGoldenCase(
        string $yr,
        string $ariaLabel,
        string $teamName,
        string $color1,
        ?array $regularRows,
        ?array $fallbackRows,
        int $year
    ): string {
        $db = $this->makeDb($regularRows, $fallbackRows, $year);

        return SeasonAverages::render($db, [], $this->makeTeam($teamName, $color1), $yr, [], '', $ariaLabel);
    }

    /**
     * @param list<array<string, string>>|null $regularRows
     * @param list<array<string, string>>|null $fallbackRows
     */
    #[DataProvider('goldenCases')]
    public function testRenderMatchesGolden(
        string $case,
        string $yr,
        string $ariaLabel,
        string $teamName,
        string $color1,
        ?array $regularRows,
        ?array $fallbackRows,
        int $year
    ): void {
        $html = $this->renderGoldenCase($yr, $ariaLabel, $teamName, $color1, $regularRows, $fallbackRows, $year);

        $goldenPath = __DIR__ . '/fixtures/season-averages-' . $case . '.golden.html';

        $this->assertStringEqualsFile($goldenPath, $html);
    }

    public function testHostileInputGoldenHasNoUnescapedPayload(): void
    {
        $case = self::goldenCases()['hostile'];
        $html = $this->renderGoldenCase($case[1], $case[2], $case[3], $case[4], $case[5], $case[6], $case[7]);

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('aria-label="&quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt;"', $html);
        $this->assertStringNotContainsString('<b>Hack</b>', $html);
    }

    public function testFallbackAndRegularRowsRenderDifferently(): void
    {
        $regular = self::goldenCases()['explicit-year'];
        $fallback = self::goldenCases()['fallback'];

        $regularHtml = $this->renderGoldenCase($regular[1], $regular[2], $regular[3], $regular[4], $regular[5], $regular[6], $regular[7]);
        $fallbackHtml = $this->renderGoldenCase($fallback[1], $fallback[2], $fallback[3], $fallback[4], $fallback[5], $fallback[6], $fallback[7]);

        $this->assertNotSame($regularHtml, $fallbackHtml);
    }

    public function testPlayerRowsMatchGolden(): void
    {
        $html = SeasonAverages::render($this->makePlayerDb(), $this->playerPair(), $this->makeTeam(), '', [11], '', 'Season table');
        $this->assertStringEqualsFile(__DIR__ . '/fixtures/season-averages-player-rows.golden.html', $html);
    }

    public function testLeagueStartersMatchesGolden(): void
    {
        $html = SeasonAverages::render($this->makePlayerDb(), $this->playerPair(), $this->makeTeam(), '', [11], 'LeagueStarters', '');
        $this->assertStringEqualsFile(__DIR__ . '/fixtures/season-averages-league-starters.golden.html', $html);
    }

    public function testHostilePlayerRowMatchesGolden(): void
    {
        $html = SeasonAverages::render($this->makePlayerDb(), $this->hostilePlayers(), $this->makeTeam("O'Brien <b>Hack</b>", '"><img src=x>'), '', [], 'LeagueStarters', '');
        $this->assertStringEqualsFile(__DIR__ . '/fixtures/season-averages-hostile-players.golden.html', $html);
    }

    public function testHostilePlayerPositionIsEscaped(): void
    {
        $html = SeasonAverages::render($this->makePlayerDb(), $this->hostilePlayers(), $this->makeTeam(), '', [], 'LeagueStarters', '');
        $this->assertStringContainsString('<td>&lt;b&gt;PG&lt;/b&gt;</td>', $html);
        $this->assertStringNotContainsString('<td><b>PG</b></td>', $html);
        $this->assertStringNotContainsString('<script>', $html);
    }

    public function testStarterPidMarksOnlyTheStarterCell(): void
    {
        $html = SeasonAverages::render($this->makePlayerDb(), $this->playerPair(), $this->makeTeam(), '', [11], '', '');
        $this->assertSame(1, substr_count($html, ' is-starter'));
        $noStarters = SeasonAverages::render($this->makePlayerDb(), $this->playerPair(), $this->makeTeam(), '', [], '', '');
        $this->assertStringNotContainsString(' is-starter', $noStarters);
    }

    public function testLeagueStartersRendersTeamColumn(): void
    {
        $html = SeasonAverages::render($this->makePlayerDb(), $this->playerPair(), $this->makeTeam(), '', [], 'LeagueStarters', '');
        $this->assertStringContainsString('<th>Team</th>', $html);
        $this->assertStringContainsString('Free Agent', $html);
        $teamModule = SeasonAverages::render($this->makePlayerDb(), $this->playerPair(), $this->makeTeam(), '', [], '', '');
        $this->assertStringNotContainsString('<th>Team</th>', $teamModule);
        $this->assertStringNotContainsString('Free Agent', $teamModule);
    }

    public function testLeagueStartersFooterLabelSpansThreeColumns(): void
    {
        $html = SeasonAverages::render($this->makePlayerDb(), $this->playerPair(), $this->makeTeam(), '', [], 'LeagueStarters', '');
        $this->assertSame(2, substr_count($html, '<td colspan="3">'));
        $this->assertStringNotContainsString('colspan="2"', $html);
        $teamModule = SeasonAverages::render($this->makePlayerDb(), $this->playerPair(), $this->makeTeam(), '', [], '', '');
        $this->assertSame(2, substr_count($teamModule, '<td colspan="2">'));
    }

    public function testHostileTeamNameIsEscapedInFooter(): void
    {
        $html = SeasonAverages::render($this->makePlayerDb(), $this->playerPair(), $this->makeTeam("O'Brien <b>Hack</b>"), '', [], '', '');
        $this->assertStringContainsString("O&apos;Brien &lt;b&gt;Hack&lt;/b&gt; Offense", $html);
        $this->assertStringContainsString("O&apos;Brien &lt;b&gt;Hack&lt;/b&gt; Defense", $html);
        $this->assertStringNotContainsString('<b>Hack</b>', $html);
    }
}
