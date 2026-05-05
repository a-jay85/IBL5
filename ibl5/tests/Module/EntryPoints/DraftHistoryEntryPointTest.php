<?php

declare(strict_types=1);

namespace Tests\Module\EntryPoints;

/**
 * Integration tests for modules/DraftHistory/index.php entry point.
 *
 * Exercises (int) $_GET['teamid'] and (int) $_REQUEST['year'] type-casting boundaries,
 * plus the HTMX API handler (op=api) which returns HTML fragments.
 */
class DraftHistoryEntryPointTest extends ModuleEntryPointTestCase
{
    public function testNoParamsShowsLatestDraftYear(): void
    {
        $this->mockDb->setMockData([]);
        $output = $this->runModule('DraftHistory');

        $this->assertNotEmpty($output);
        $this->assertQueryExecuted('draftyear');
    }

    public function testValidYearParam(): void
    {
        $this->mockDb->setMockData([]);
        $output = $this->runModule('DraftHistory', ['year' => '2020']);

        $this->assertNotEmpty($output);
        $this->assertQueryExecuted('draftyear');
    }

    public function testYearZeroPassedThroughAsZero(): void
    {
        // (int)'0' === 0, but the code does: $year = isset($_REQUEST['year']) ? (int)$_REQUEST['year'] : $endYear
        // So $year = 0. The repository query runs with year=0 (no draft results).
        $this->mockDb->setMockData([]);
        $output = $this->runModule('DraftHistory', ['year' => '0']);

        $this->assertNotEmpty($output);
        $this->assertQueryExecuted('draftyear');
    }

    public function testNegativeYearParam(): void
    {
        $this->mockDb->setMockData([]);
        $output = $this->runModule('DraftHistory', ['year' => '-5']);

        $this->assertNotEmpty($output);
        // (int)'-5' === -5, query runs with year=-5 (no results)
        $this->assertQueryExecuted('draftyear');
    }

    public function testNonNumericYearParam(): void
    {
        $this->mockDb->setMockData([]);
        $output = $this->runModule('DraftHistory', ['year' => 'abc']);

        $this->assertNotEmpty($output);
        // (int)'abc' === 0
        $this->assertQueryExecuted('draftyear');
    }

    public function testValidTeamIdShowsTeamHistory(): void
    {
        $this->mockDb->setMockTeamData([self::fullTeamData(['teamid' => 3, 'team_name' => 'TestTeam'])]);
        $this->mockDb->setMockData([]);
        $output = $this->runModule('DraftHistory', ['teamid' => '3']);

        $this->assertNotEmpty($output);
        $this->assertQueryExecuted('ibl_team_info');
    }

    public function testTeamIdTakesPriorityOverYear(): void
    {
        // When both teamid and year are set, teamid path wins (checked first)
        $this->mockDb->setMockTeamData([self::fullTeamData(['teamid' => 3, 'team_name' => 'TestTeam'])]);
        $this->mockDb->setMockData([]);
        $output = $this->runModule('DraftHistory', ['teamid' => '3', 'year' => '2020']);

        $this->assertNotEmpty($output);
        $this->assertQueryExecuted('ibl_team_info');
    }

    public function testTeamIdZeroShowsYearView(): void
    {
        $this->mockDb->setMockData([]);
        $output = $this->runModule('DraftHistory', ['teamid' => '0']);

        $this->assertNotEmpty($output);
        // teamid=0 fails > 0 guard, falls to year view
        $this->assertQueryExecuted('draftyear');
    }

    public function testOpApiReturnsHtmlFragment(): void
    {
        $this->mockDb->setMockData([]);
        $output = $this->runModule('DraftHistory', ['op' => 'api']);

        $this->assertNotEmpty($output);
        $this->assertQueryExecuted('draftyear');
    }

    public function testOpApiWithValidYearReturnsHtml(): void
    {
        $this->mockDb->setMockData([]);
        $output = $this->runModule('DraftHistory', ['op' => 'api', 'year' => '2020']);

        $this->assertNotEmpty($output);
    }

    public function testOpApiWithNonNumericYearFallsBackToLatest(): void
    {
        $this->mockDb->setMockData([]);
        $output = $this->runModule('DraftHistory', ['op' => 'api', 'year' => 'garbage']);

        $this->assertNotEmpty($output);
    }

    public function testNonNumericTeamIdCastsToZero(): void
    {
        $this->mockDb->setMockData([]);
        $output = $this->runModule('DraftHistory', ['teamid' => 'garbage']);

        $this->assertNotEmpty($output);
        // (int)'garbage' === 0, fails > 0 guard, falls to year view
        $this->assertQueryExecuted('draftyear');
    }
}
