<?php

declare(strict_types=1);

namespace Tests\Module\EntryPoints;

use PHPUnit\Framework\Attributes\DataProvider;

class LeaderboardsEntryPointTest extends ModuleEntryPointTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->mockDb->setMockData([]);
        $this->mockDb->onQuery('cache', []);
    }

    public function testDefaultTabRendersSeasonFormOnly(): void
    {
        $output = $this->runModule('Leaderboards', [], [], $this->dbGlobals());

        $this->assertStringContainsString('<div class="ibl-tab-panel" data-tab="season"', $output);
        $this->assertStringContainsString('class="ibl-tabs"', $output);
        $this->assertStringContainsString('<div class="leaderboards-page"><h1 class="ibl-title">Leaderboards</h1><div class="ibl-tabs">', $output);
        $this->assertStringContainsString('<form name="Leaderboards"', $output);
        $this->assertStringNotContainsString('ibl-data-table', $output);
    }

    public function testSeasonTabFirstVisitRendersFilterFormWithoutResults(): void
    {
        $output = $this->runModule('Leaderboards', ['tab' => 'season'], [], $this->dbGlobals());

        $this->assertNotEmpty($output);
        $this->assertStringContainsString('<div class="ibl-tab-panel" data-tab="season"', $output);
        $this->assertStringContainsString('<form name="Leaderboards"', $output);
        $this->assertStringNotContainsString('ibl-data-table', $output);
        $this->assertStringNotContainsString('<td class="rank-cell', $output);
    }

    public function testSeasonTabSubmittedRunsDefaultSearchAndRendersTable(): void
    {
        $output = $this->runModule('Leaderboards', ['tab' => 'season', 'submitted' => '1'], [], $this->dbGlobals());

        $this->assertStringContainsString('ibl-data-table', $output);
        $this->assertQueryExecuted('ibl_hist');
    }

    public function testSeasonTabBlankLimitDefaultsToFifty(): void
    {
        $this->mockDb->onQuery('FROM ibl_hist h', $this->seasonRows(60));

        $output = $this->runModule('Leaderboards', [
            'tab' => 'season',
            'submitted' => '1',
            'limit' => '',
        ], [], $this->dbGlobals());

        $this->assertSame(50, $this->countPlayerRows($output));
    }

    public function testSeasonTabFormSubmitsViaGet(): void
    {
        $output = $this->runModule('Leaderboards', ['tab' => 'season'], [], $this->dbGlobals());

        $this->assertStringContainsString('method="get" action="modules.php"', $output);
        $this->assertStringContainsString('name="submitted" value="1"', $output);
    }

    public function testSeasonTabRendersFilterFormMarkup(): void
    {
        $output = $this->runModule('Leaderboards', ['tab' => 'season'], [], $this->dbGlobals());

        $this->assertStringContainsString('<form name="Leaderboards"', $output);
        $this->assertStringContainsString('class="ibl-filter-form ibl-filter-form--stacked"', $output);
        $this->assertStringContainsString('name="sortby"', $output);
    }

    public function testSeasonTabPostWithFiltersRunsLeaderboardQuery(): void
    {
        $output = $this->runModule('Leaderboards', array_merge(['tab' => 'season', 'submitted' => '1'], [
            'year' => '2024',
            'team' => '1',
            'sortby' => 'PPG',
            'limit' => '50',
        ]), [], $this->dbGlobals());

        $this->assertNotEmpty($output);
        $this->assertQueryExecuted('ibl_hist');
    }

    public function testSeasonTabPostWithStringTeamCastsToInt(): void
    {
        $output = $this->runModule('Leaderboards', array_merge(['tab' => 'season', 'submitted' => '1'], [
            'year' => '2024',
            'team' => 'garbage',
            'sortby' => 'PPG',
            'limit' => '50',
        ]), [], $this->dbGlobals());

        $this->assertNotEmpty($output);
        $this->assertQueryExecuted('ibl_hist');
    }

    public function testSeasonTabPostWithDefaultSortby(): void
    {
        $output = $this->runModule('Leaderboards', array_merge(['tab' => 'season', 'submitted' => '1'], [
            'year' => '2024',
            'team' => '0',
            'limit' => '25',
        ]), [], $this->dbGlobals());

        $this->assertNotEmpty($output);
        $this->assertStringContainsString('<div class="ibl-tab-panel" data-tab="season"', $output);
    }

    public function testCareerTabFirstVisitRendersFormWithoutResults(): void
    {
        $output = $this->runModule('Leaderboards', ['tab' => 'career'], [], $this->dbGlobals());

        $this->assertNotEmpty($output);
        $this->assertStringContainsString('<div class="ibl-tab-panel" data-tab="career"', $output);
        $this->assertStringContainsString('<form name="CareerLeaderboards"', $output);
        $this->assertStringNotContainsString('ibl-data-table', $output);
        $this->assertStringNotContainsString('<td class="rank-cell', $output);
        $this->assertMatchesRegularExpression('/id="cl-retirees"[^>]*checked/', $output);
    }

    public function testCareerTabSubmittedRunsDefaultSearch(): void
    {
        $output = $this->runModule('Leaderboards', ['tab' => 'career', 'submitted' => '1'], [], $this->dbGlobals());

        $this->assertStringContainsString('ibl-data-table', $output);
        $this->assertQueryExecuted('ibl_hist');
    }

    public function testCareerTabFormSubmitsViaGet(): void
    {
        $output = $this->runModule('Leaderboards', ['tab' => 'career'], [], $this->dbGlobals());

        $this->assertStringContainsString('method="get" action="modules.php"', $output);
        $this->assertStringContainsString('name="submitted" value="1"', $output);
    }

    public function testCareerTabSubmittedBlankLimitShowsFiftyRowsIncludingRetirees(): void
    {
        $this->seedCareerRows(60, 'FROM ibl_hist h');

        $output = $this->runModule('Leaderboards', ['tab' => 'career', 'submitted' => '1', 'retirees' => '1'], [], $this->dbGlobals());

        $this->assertSame(50, $this->countPlayerRows($output));
        $this->assertStringContainsString('Retired Player*', $output);
        $this->assertMatchesRegularExpression('/id="cl-retirees"[^>]*checked/', $output);
        $this->assertStringContainsString('<th class="sorted-col">PTS</th>', $output);
    }

    public function testCareerTabRendersCareerFilterFormMarkup(): void
    {
        $output = $this->runModule('Leaderboards', ['tab' => 'career'], [], $this->dbGlobals());

        $this->assertStringContainsString('<form name="CareerLeaderboards"', $output);
        $this->assertStringContainsString('name="phase"', $output);
        $this->assertStringContainsString('name="mode"', $output);
        $this->assertStringContainsString('name="sortby"', $output);
        $this->assertStringContainsString('name="retirees"', $output);
        $this->assertStringContainsString('>Search</button>', $output);
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function phaseModeTableProvider(): array
    {
        return [
            'regular totals' => ['regular', 'totals', 'ibl_hist'],
            'regular averages' => ['regular', 'averages', 'ibl_season_career_avgs'],
            'playoffs totals' => ['playoffs', 'totals', 'ibl_playoff_career_totals'],
            'playoffs averages' => ['playoffs', 'averages', 'ibl_playoff_career_avgs'],
            'heat totals' => ['heat', 'totals', 'ibl_heat_career_totals'],
            'heat averages' => ['heat', 'averages', 'ibl_heat_career_avgs'],
            'olympics totals' => ['olympics', 'totals', 'ibl_olympics_career_totals'],
            'olympics averages' => ['olympics', 'averages', 'ibl_olympics_career_avgs'],
            'rookie totals' => ['rookie', 'totals', 'ibl_rookie_career_totals'],
            'rookie averages falls back to totals' => ['rookie', 'averages', 'ibl_rookie_career_totals'],
            'sophomore totals' => ['sophomore', 'totals', 'ibl_sophomore_career_totals'],
            'sophomore averages falls back to totals' => ['sophomore', 'averages', 'ibl_sophomore_career_totals'],
            'allstar totals' => ['allstar', 'totals', 'ibl_allstar_career_totals'],
            'allstar averages' => ['allstar', 'averages', 'ibl_allstar_career_avgs'],
        ];
    }

    #[DataProvider('phaseModeTableProvider')]
    public function testCareerTabPhaseAndModeResolveToTable(string $phase, string $mode, string $table): void
    {
        $output = $this->runModule('Leaderboards', array_merge(['tab' => 'career'], [
            'submitted' => '1',
            'phase' => $phase,
            'mode' => $mode,
            'sortby' => 'PPG',
            'retirees' => '1',
            'display' => '50',
        ]), [], $this->dbGlobals());

        $this->assertNotEmpty($output);
        $this->assertQueryExecuted($table);
    }

    public function testCareerTabRookieAveragesRendersAveragesRadioDisabled(): void
    {
        $output = $this->runModule('Leaderboards', array_merge(['tab' => 'career'], [
            'submitted' => '1',
            'phase' => 'rookie',
            'mode' => 'averages',
        ]), [], $this->dbGlobals());

        $this->assertMatchesRegularExpression('/value="averages"[^>]*disabled/', $output);
        $this->assertMatchesRegularExpression('/value="totals" checked/', $output);
    }

    public function testCareerTabBogusParamsFallBackToDefaults(): void
    {
        $output = $this->runModule('Leaderboards', array_merge(['tab' => 'career'], [
            'submitted' => '1',
            'phase' => 'garbage',
            'mode' => 'nonsense',
            'sortby' => 'NOPE',
            'retirees' => '1',
            'display' => 'abc',
        ]), [], $this->dbGlobals());

        $this->assertNotEmpty($output);
        $this->assertStringContainsString('<div class="ibl-tab-panel" data-tab="career"', $output);
        $this->assertQueryExecuted('ibl_hist');
        $this->assertStringContainsString('<th class="sorted-col">PTS</th>', $output);
    }

    public function testCareerTabNonNumericLimitFallsBackToFifty(): void
    {
        $this->seedCareerRows(60, 'FROM ibl_hist h');

        $output = $this->runModule('Leaderboards', array_merge(['tab' => 'career'], [
            'submitted' => '1',
            'retirees' => '1',
            'display' => 'abc',
        ]), [], $this->dbGlobals());

        $this->assertSame(50, $this->countPlayerRows($output));
    }

    public function testCareerTabPercentageSortOnTotalsKeepsPercentage(): void
    {
        $output = $this->runModule('Leaderboards', array_merge(['tab' => 'career'], [
            'submitted' => '1',
            'phase' => 'regular',
            'mode' => 'totals',
            'sortby' => 'FGP',
            'retirees' => '1',
        ]), [], $this->dbGlobals());

        $this->assertStringContainsString('<th class="sorted-col">FG%</th>', $output);
        $this->assertStringNotContainsString('<th class="sorted-col">PTS</th>', $output);
    }

    public function testCareerTabPercentageSortOnAveragesUsesPctColumn(): void
    {
        $output = $this->runModule('Leaderboards', array_merge(['tab' => 'career'], [
            'submitted' => '1',
            'phase' => 'regular',
            'mode' => 'averages',
            'sortby' => 'FGP',
            'retirees' => '1',
        ]), [], $this->dbGlobals());

        $this->assertQueryExecuted('ibl_season_career_avgs');
        $this->assertStringContainsString('<th class="sorted-col">FG%</th>', $output);
    }

    public function testCareerTabSubmittedWithoutRetireesExcludesRetiredPlayers(): void
    {
        $this->seedCareerRows(10, 'FROM ibl_hist h');

        $output = $this->runModule('Leaderboards', array_merge(['tab' => 'career'], [
            'submitted' => '1',
            'phase' => 'regular',
            'mode' => 'totals',
            'sortby' => 'PPG',
            'display' => '50',
        ]), [], $this->dbGlobals());

        $this->assertStringNotContainsString('Retired Player*', $output);
        $this->assertSame(9, $this->countPlayerRows($output));
        $this->assertDoesNotMatchRegularExpression('/id="cl-retirees"[^>]*checked/', $output);
    }

    public function testCareerTabSubmittedWithRetireesIncludesEveryone(): void
    {
        $this->seedCareerRows(10, 'FROM ibl_hist h');

        $output = $this->runModule('Leaderboards', array_merge(['tab' => 'career'], [
            'submitted' => '1',
            'retirees' => '1',
        ]), [], $this->dbGlobals());

        $this->assertStringContainsString('Retired Player*', $output);
        $this->assertSame(10, $this->countPlayerRows($output));
    }

    public function testCareerTabSubmittedLimitIsUsed(): void
    {
        $this->seedCareerRows(20, 'FROM ibl_hist h');

        $output = $this->runModule('Leaderboards', array_merge(['tab' => 'career'], [
            'submitted' => '1',
            'retirees' => '1',
            'display' => '7',
        ]), [], $this->dbGlobals());

        $this->assertSame(7, $this->countPlayerRows($output));
    }

    /**
     * Seed the inner career query; the cached repository filters, sorts and limits in PHP.
     * Player 1 is retired.
     */
    private function seedCareerRows(int $count, string $queryPattern): void
    {
        $rows = [];
        for ($i = 1; $i <= $count; $i++) {
            $rows[] = [
                'pid' => $i,
                'name' => $i === 1 ? 'Retired Player' : 'Player ' . $i,
                'retired' => $i === 1 ? 1 : 0,
                'games' => 100, 'minutes' => 3000,
                'fgm' => 500, 'fga' => 1000, 'ftm' => 200, 'fta' => 250,
                'tgm' => 50, 'tga' => 150, 'orb' => 100, 'drb' => 300, 'reb' => 400,
                'ast' => 300, 'stl' => 80, 'tvr' => 100, 'blk' => 40, 'pf' => 150,
                'pts' => 10000 - $i,
            ];
        }
        $this->mockDb->onQuery($queryPattern, $rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function seasonRows(int $count): array
    {
        $rows = [];
        for ($i = 1; $i <= $count; $i++) {
            $rows[] = [
                'pid' => $i, 'name' => 'Player ' . $i, 'year' => 2024, 'teamid' => 1,
                'team' => 'Team', 'color1' => 'FFFFFF', 'color2' => '000000',
                'games' => 80, 'minutes' => 2000,
                'fgm' => 500, 'fga' => 1000, 'ftm' => 200, 'fta' => 250,
                'tgm' => 50, 'tga' => 150, 'orb' => 100, 'drb' => 300, 'reb' => 400,
                'ast' => 300, 'stl' => 80, 'tvr' => 100, 'blk' => 40, 'pf' => 150,
                'pts' => 2000 - $i,
            ];
        }

        return $rows;
    }

    private function countPlayerRows(string $output): int
    {
        return substr_count($output, '<td class="rank-cell');
    }

    public function testCareerTabDoesNotRenderSeasonBoard(): void
    {
        $output = $this->runModule('Leaderboards', ['tab' => 'career'], [], $this->dbGlobals());

        $this->assertStringNotContainsString('<form name="Leaderboards"', $output);
    }

    public function testTabBarRendersOnCareerTab(): void
    {
        $output = $this->runModule('Leaderboards', ['tab' => 'career'], [], $this->dbGlobals());

        $this->assertStringContainsString(
            '<a class="ibl-tab ibl-tab--active"',
            $output
        );
        $this->assertStringContainsString('tab=career', $output);
    }

    public function testTabBodyWrappedInPanelDiv(): void
    {
        $output = $this->runModule('Leaderboards', ['tab' => 'season'], [], $this->dbGlobals());

        $this->assertStringContainsString('ibl-tab-panel', $output);
    }

    public function testUnknownTabFallsBackToSeason(): void
    {
        $output = $this->runModule('Leaderboards', ['tab' => 'bogus'], [], $this->dbGlobals());

        $this->assertStringContainsString('<div class="ibl-tab-panel" data-tab="season"', $output);
        $this->assertStringNotContainsString('<form name="CareerLeaderboards"', $output);
    }

    public function testArrayTabParamFallsBackToSeason(): void
    {
        $output = $this->runModule('Leaderboards', ['tab' => ['career']], [], $this->dbGlobals());

        $this->assertStringContainsString('<div class="ibl-tab-panel" data-tab="season"', $output);
        $this->assertStringNotContainsString('<form name="CareerLeaderboards"', $output);
    }
}
