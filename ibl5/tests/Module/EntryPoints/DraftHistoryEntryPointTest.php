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

        $this->assertNotSame('', $output);
        $this->assertQueryExecuted('draftyear');
    }

    public function testValidYearParam(): void
    {
        $this->mockDb->setMockData([]);
        $output = $this->runModule('DraftHistory', ['year' => '2020']);

        $this->assertNotSame('', $output);
        $this->assertQueryExecuted('draftyear');
    }

    public function testYearZeroPassedThroughAsZero(): void
    {
        // (int)'0' === 0, but the code does: $year = isset($_REQUEST['year']) ? (int)$_REQUEST['year'] : $endYear
        // So $year = 0. The repository query runs with year=0 (no draft results).
        $this->mockDb->setMockData([]);
        $output = $this->runModule('DraftHistory', ['year' => '0']);

        $this->assertNotSame('', $output);
        $this->assertQueryExecuted('draftyear');
    }

    public function testNegativeYearParam(): void
    {
        $this->mockDb->setMockData([]);
        $output = $this->runModule('DraftHistory', ['year' => '-5']);

        $this->assertNotSame('', $output);
        // (int)'-5' === -5, query runs with year=-5 (no results)
        $this->assertQueryExecuted('draftyear');
    }

    public function testNonNumericYearParam(): void
    {
        $this->mockDb->setMockData([]);
        $output = $this->runModule('DraftHistory', ['year' => 'abc']);

        $this->assertNotSame('', $output);
        // (int)'abc' === 0
        $this->assertQueryExecuted('draftyear');
    }

    public function testValidTeamIdShowsTeamHistory(): void
    {
        $this->mockDb->setMockTeamData([self::fullTeamData(['teamid' => 3, 'team_name' => 'TestTeam'])]);
        $this->mockDb->setMockData([]);
        $output = $this->runModule('DraftHistory', ['teamid' => '3']);

        $this->assertNotSame('', $output);
        $this->assertQueryExecuted('ibl_team_info');
    }

    public function testCamelCaseTeamIDShowsSameTeamHistoryAsLowercase(): void
    {
        $this->mockDb->setMockData([]);
        $yearView = $this->runModule('DraftHistory');

        $this->mockDb->setMockTeamData([self::fullTeamData(['teamid' => 3, 'team_name' => 'TestTeam'])]);
        $lower = $this->runModule('DraftHistory', ['teamid' => '3']);
        $camel = $this->runModule('DraftHistory', ['teamID' => '3']);

        $this->assertSame($lower, $camel);
        $this->assertNotSame($yearView, $camel);
    }

    public function testCamelCaseTeamIDZeroShowsYearView(): void
    {
        $this->mockDb->setMockData([]);

        $yearView = $this->runModule('DraftHistory');
        $camel = $this->runModule('DraftHistory', ['teamID' => '0']);

        $this->assertSame($yearView, $camel);
        $this->assertQueryExecuted('draftyear');
    }

    public function testTeamIdTakesPriorityOverYear(): void
    {
        // When both teamid and year are set, teamid path wins (checked first)
        $this->mockDb->setMockTeamData([self::fullTeamData(['teamid' => 3, 'team_name' => 'TestTeam'])]);
        $this->mockDb->setMockData([]);
        $output = $this->runModule('DraftHistory', ['teamid' => '3', 'year' => '2020']);

        $this->assertNotSame('', $output);
        $this->assertQueryExecuted('ibl_team_info');
    }

    public function testTeamIdZeroShowsYearView(): void
    {
        $this->mockDb->setMockData([]);
        $output = $this->runModule('DraftHistory', ['teamid' => '0']);

        $this->assertNotSame('', $output);
        // teamid=0 fails > 0 guard, falls to year view
        $this->assertQueryExecuted('draftyear');
    }

    public function testOpApiReturnsHtmlFragment(): void
    {
        $this->mockDb->setMockData([]);
        $output = $this->runModule('DraftHistory', ['op' => 'api']);

        $this->assertNotSame('', $output);
        $this->assertQueryExecuted('draftyear');
    }

    public function testOpApiWithValidYearReturnsHtml(): void
    {
        $this->mockDb->setMockData([]);
        $output = $this->runModule('DraftHistory', ['op' => 'api', 'year' => '2020']);

        $this->assertNotSame('', $output);
    }

    public function testOpApiWithNonNumericYearFallsBackToLatest(): void
    {
        $this->mockDb->setMockData([]);
        $output = $this->runModule('DraftHistory', ['op' => 'api', 'year' => 'garbage']);

        $this->assertNotSame('', $output);
    }

    public function testNonNumericTeamIdCastsToZero(): void
    {
        $this->mockDb->setMockData([]);
        $output = $this->runModule('DraftHistory', ['teamid' => 'garbage']);

        $this->assertNotSame('', $output);
        // (int)'garbage' === 0, fails > 0 guard, falls to year view
        $this->assertQueryExecuted('draftyear');
    }

    public function testTeamIdWithTrailingJunkParsesLeadingDigits(): void
    {
        // (int)'5abc' === 5, so the team branch renders for team 5.
        $this->mockDb->setMockTeamData([self::fullTeamData(['teamid' => 5, 'team_name' => 'TrailingJunkTeam'])]);
        $this->mockDb->setMockData([]);
        $output = $this->runModule('DraftHistory', ['teamid' => '5abc']);

        $this->assertStringContainsString('TrailingJunkTeam', $output);
        $this->assertQueryExecuted("draftedby = 'TrailingJunkTeam'");
    }

    public function testArrayTeamIdRendersYearIndex(): void
    {
        // teamid[]=5 is an array, not a numeric string, so it falls back to 0 and the year index renders.
        $this->mockDb->setMockData([]);
        $output = $this->runModule('DraftHistory', ['teamid' => ['5']]);

        $this->assertNotSame('', $output);
        $this->assertQueryExecuted('draftyear');
        $this->assertQueryNotExecuted("draftedby = '");
    }
}
