<?php

declare(strict_types=1);

namespace Tests\Module\EntryPoints;

class CareerLeaderboardsEntryPointTest extends ModuleEntryPointTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->mockDb->setMockData([]);
        $this->mockDb->onQuery('cache', []);
    }

    public function testNotSubmittedRendersFilterForm(): void
    {
        $output = $this->runModule('CareerLeaderboards', [], [], $this->dbGlobals());

        $this->assertNotSame('', $output);
        $this->assertStringContainsString('Career Leaderboards', $output);
        $this->assertQueryNotExecuted('ibl_hist');
    }

    public function testSubmittedRunsLeaderboardQuery(): void
    {
        $output = $this->runModule('CareerLeaderboards', [], [
            'submitted' => '1',
            'boards_type' => 'Regular Season Totals',
            'sort_cat' => 'Points',
            'active' => '0',
            'display' => '50',
        ], $this->dbGlobals());

        $this->assertNotSame('', $output);
        $this->assertQueryExecuted('ibl_hist');
    }

    public function testActiveOnlyFilterApplied(): void
    {
        $output = $this->runModule('CareerLeaderboards', [], [
            'submitted' => '1',
            'boards_type' => 'Regular Season Totals',
            'sort_cat' => 'Points',
            'active' => '1',
            'display' => '50',
        ], $this->dbGlobals());

        $this->assertNotSame('', $output);
        $this->assertQueryExecuted('ibl_hist');
    }

    public function testInvalidBoardsTypeFallsBackGracefully(): void
    {
        $output = $this->runModule('CareerLeaderboards', [], [
            'submitted' => '1',
            'boards_type' => 'garbage',
            'sort_cat' => 'Points',
            'active' => '0',
            'display' => '50',
        ], $this->dbGlobals());

        $this->assertNotSame('', $output);
        $this->assertStringContainsString('Career Leaderboards', $output);
    }

    public function testEmptySubmittedSkipsLeaderboardQuery(): void
    {
        $this->runModule('CareerLeaderboards', [], [
            'submitted' => '',
            'boards_type' => 'Regular Season Totals',
            'sort_cat' => 'Points',
            'active' => '0',
            'display' => '50',
        ], $this->dbGlobals());

        $this->assertQueryNotExecuted('ibl_hist');
    }

    public function testZeroStringSubmittedRunsLeaderboardQuery(): void
    {
        $this->runModule('CareerLeaderboards', [], [
            'submitted' => '0',
            'boards_type' => 'Regular Season Totals',
            'sort_cat' => 'Points',
            'active' => '0',
            'display' => '50',
        ], $this->dbGlobals());

        $this->assertQueryExecuted('ibl_hist');
    }
}
