<?php

declare(strict_types=1);

namespace Tests\Leaderboards;

use Tests\Module\EntryPoints\ModuleEntryPointTestCase;

class LeaderboardsEntryPointTest extends ModuleEntryPointTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->mockDb->setMockData([]);
        $this->mockDb->onQuery('cache', []);
    }

    public function testDefaultTabRendersSeasonLeaders(): void
    {
        $output = $this->runModule('Leaderboards', [], [], $this->dbGlobals());

        $this->assertStringContainsString('Season Leaders', $output);
        $this->assertStringContainsString('class="ibl-tabs', $output);
        $this->assertQueryExecuted('ibl_hist');
    }

    public function testSeasonTabEmptyPostRendersFilterFormAndDefaultLeaderboard(): void
    {
        $output = $this->runModule('Leaderboards', ['tab' => 'season'], [], $this->dbGlobals());

        $this->assertNotEmpty($output);
        $this->assertStringContainsString('Season Leaders', $output);
        $this->assertQueryExecuted('ibl_hist');
    }

    public function testSeasonTabRendersFilterFormMarkup(): void
    {
        $output = $this->runModule('Leaderboards', ['tab' => 'season'], [], $this->dbGlobals());

        $this->assertStringContainsString('<form name="Leaderboards"', $output);
        $this->assertStringContainsString('class="ibl-filter-form"', $output);
        $this->assertStringContainsString('name="sortby"', $output);
    }

    public function testSeasonTabPostWithFiltersRunsLeaderboardQuery(): void
    {
        $output = $this->runModule('Leaderboards', ['tab' => 'season'], [
            'year' => '2024',
            'team' => '1',
            'sortby' => 'PPG',
            'limit' => '50',
        ], $this->dbGlobals());

        $this->assertNotEmpty($output);
        $this->assertQueryExecuted('ibl_hist');
    }

    public function testSeasonTabPostWithStringTeamCastsToInt(): void
    {
        $output = $this->runModule('Leaderboards', ['tab' => 'season'], [
            'year' => '2024',
            'team' => 'garbage',
            'sortby' => 'PPG',
            'limit' => '50',
        ], $this->dbGlobals());

        $this->assertNotEmpty($output);
        $this->assertQueryExecuted('ibl_hist');
    }

    public function testSeasonTabPostWithDefaultSortby(): void
    {
        $output = $this->runModule('Leaderboards', ['tab' => 'season'], [
            'year' => '2024',
            'team' => '0',
            'limit' => '25',
        ], $this->dbGlobals());

        $this->assertNotEmpty($output);
        $this->assertStringContainsString('Season Leaders', $output);
    }

    public function testCareerTabNotSubmittedRendersFilterForm(): void
    {
        $output = $this->runModule('Leaderboards', ['tab' => 'career'], [], $this->dbGlobals());

        $this->assertNotEmpty($output);
        $this->assertStringContainsString('Career Leaderboards', $output);
        $this->assertQueryNotExecuted('ibl_hist');
    }

    public function testCareerTabRendersCareerFilterFormMarkup(): void
    {
        $output = $this->runModule('Leaderboards', ['tab' => 'career'], [], $this->dbGlobals());

        $this->assertStringContainsString('<form name="CareerLeaderboards"', $output);
        $this->assertStringContainsString('name="boards_type"', $output);
        $this->assertStringContainsString('name="sort_cat"', $output);
    }

    public function testCareerTabSubmittedRunsLeaderboardQuery(): void
    {
        $output = $this->runModule('Leaderboards', ['tab' => 'career'], [
            'submitted' => '1',
            'boards_type' => 'Regular Season Totals',
            'sort_cat' => 'Points',
            'active' => '0',
            'display' => '50',
        ], $this->dbGlobals());

        $this->assertNotEmpty($output);
        $this->assertQueryExecuted('ibl_hist');
    }

    public function testCareerTabActiveOnlyFilterApplied(): void
    {
        $output = $this->runModule('Leaderboards', ['tab' => 'career'], [
            'submitted' => '1',
            'boards_type' => 'Regular Season Totals',
            'sort_cat' => 'Points',
            'active' => '1',
            'display' => '50',
        ], $this->dbGlobals());

        $this->assertNotEmpty($output);
        $this->assertQueryExecuted('ibl_hist');
    }

    public function testCareerTabInvalidBoardsTypeFallsBackGracefully(): void
    {
        $output = $this->runModule('Leaderboards', ['tab' => 'career'], [
            'submitted' => '1',
            'boards_type' => 'garbage',
            'sort_cat' => 'Points',
            'active' => '0',
            'display' => '50',
        ], $this->dbGlobals());

        $this->assertNotEmpty($output);
        $this->assertStringContainsString('Career Leaderboards', $output);
    }

    public function testCareerTabDoesNotRenderSeasonBoard(): void
    {
        $output = $this->runModule('Leaderboards', ['tab' => 'career'], [], $this->dbGlobals());

        $this->assertStringNotContainsString('Season Leaders', $output);
    }

    public function testTabBarRendersOnCareerTab(): void
    {
        $output = $this->runModule('Leaderboards', ['tab' => 'career'], [], $this->dbGlobals());

        $this->assertStringContainsString(
            '<a class="ibl-tab ibl-tab--active" href="modules.php?name=Leaderboards&amp;tab=career"',
            $output
        );
    }

    public function testUnknownTabFallsBackToSeason(): void
    {
        $output = $this->runModule('Leaderboards', ['tab' => 'bogus'], [], $this->dbGlobals());

        $this->assertStringContainsString('Season Leaders', $output);
        $this->assertStringNotContainsString('Career Leaderboards', $output);
    }

    public function testArrayTabParamFallsBackToSeason(): void
    {
        $output = $this->runModule('Leaderboards', ['tab' => ['career']], [], $this->dbGlobals());

        $this->assertStringContainsString('Season Leaders', $output);
        $this->assertStringNotContainsString('Career Leaderboards', $output);
    }
}
