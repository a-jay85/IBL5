<?php

declare(strict_types=1);

namespace Tests\Module\EntryPoints;

class SeasonLeaderboardsEntryPointTest extends ModuleEntryPointTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->mockDb->setMockData([]);
        $this->mockDb->onQuery('cache', []);
    }

    public function testEmptyPostRendersFilterFormAndDefaultLeaderboard(): void
    {
        $output = $this->runModule('SeasonLeaderboards', [], [], $this->dbGlobals());

        $this->assertNotEmpty($output);
        $this->assertStringContainsString('Season Leaders', $output);
        $this->assertQueryExecuted('ibl_hist');
    }

    public function testPostWithFiltersRunsLeaderboardQuery(): void
    {
        $output = $this->runModule('SeasonLeaderboards', [], [
            'year' => '2024',
            'team' => '1',
            'sortby' => 'PPG',
            'limit' => '50',
        ], $this->dbGlobals());

        $this->assertNotEmpty($output);
        $this->assertQueryExecuted('ibl_hist');
    }

    public function testPostWithStringTeamCastsToInt(): void
    {
        $output = $this->runModule('SeasonLeaderboards', [], [
            'year' => '2024',
            'team' => 'garbage',
            'sortby' => 'PPG',
            'limit' => '50',
        ], $this->dbGlobals());

        $this->assertNotEmpty($output);
        $this->assertQueryExecuted('ibl_hist');
    }

    public function testPostWithDefaultSortby(): void
    {
        $output = $this->runModule('SeasonLeaderboards', [], [
            'year' => '2024',
            'team' => '0',
            'limit' => '25',
        ], $this->dbGlobals());

        $this->assertNotEmpty($output);
        $this->assertStringContainsString('Season Leaders', $output);
    }
}
