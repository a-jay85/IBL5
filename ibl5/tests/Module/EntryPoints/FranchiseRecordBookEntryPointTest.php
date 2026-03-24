<?php

declare(strict_types=1);

namespace Tests\Module\EntryPoints;

/**
 * Integration tests for modules/FranchiseRecordBook/index.php entry point.
 *
 * Exercises the `is_string($_GET['teamid'] ?? null)` guard and
 * `League::isRealFranchise()` branching (1-28 = team, else league).
 */
class FranchiseRecordBookEntryPointTest extends ModuleEntryPointTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Route RCB queries to empty results to avoid malformed mock data warnings
        $this->mockDb->onQuery('ibl_rcb_season_records', []);
        $this->mockDb->onQuery('ibl_rcb_alltime_records', []);
    }

    public function testMissingTeamidShowsLeagueRecords(): void
    {
        $this->mockDb->setMockData([]);
        $output = $this->runModule('FranchiseRecordBook');

        $this->assertNotEmpty($output);
        $this->assertQueryExecuted('ibl_rcb');
    }

    public function testValidTeamidShowsTeamRecords(): void
    {
        $this->mockDb->setMockData([]);
        $output = $this->runModule('FranchiseRecordBook', ['teamid' => '5']);

        $this->assertNotEmpty($output);
        // isRealFranchise(5) === true → team record book path
        $this->assertQueryExecuted('ibl_rcb');
    }

    public function testTeamidZeroShowsLeagueRecords(): void
    {
        $this->mockDb->setMockData([]);
        $output = $this->runModule('FranchiseRecordBook', ['teamid' => '0']);

        $this->assertNotEmpty($output);
        // isRealFranchise(0) === false → league path
        $this->assertQueryExecuted('ibl_rcb');
    }

    public function testTeamidOutOfRangeShowsLeagueRecords(): void
    {
        $this->mockDb->setMockData([]);
        $output = $this->runModule('FranchiseRecordBook', ['teamid' => '99']);

        $this->assertNotEmpty($output);
        // isRealFranchise(99) === false (max is 28) → league path
        $this->assertQueryExecuted('ibl_rcb');
    }

    public function testNonNumericTeamidCastsToZero(): void
    {
        $this->mockDb->setMockData([]);
        $output = $this->runModule('FranchiseRecordBook', ['teamid' => 'abc']);

        $this->assertNotEmpty($output);
        // (int)'abc' === 0, isRealFranchise(0) === false → league path
        $this->assertQueryExecuted('ibl_rcb');
    }

    public function testTeamidAsArrayBypassedByIsStringGuard(): void
    {
        // The is_string() guard protects against array injection.
        // $_GET['teamid'] = ['1','2'] → is_string(array) === false → $teamId stays 0
        $this->mockDb->setMockData([]);
        $_GET['teamid'] = ['1', '2'];
        $output = $this->runModule('FranchiseRecordBook', ['teamid' => ['1', '2']]);

        $this->assertNotEmpty($output);
        $this->assertQueryExecuted('ibl_rcb');
    }

    public function testNegativeTeamidShowsLeagueRecords(): void
    {
        $this->mockDb->setMockData([]);
        $output = $this->runModule('FranchiseRecordBook', ['teamid' => '-1']);

        $this->assertNotEmpty($output);
        // (int)'-1' === -1, isRealFranchise(-1) === false → league path
        $this->assertQueryExecuted('ibl_rcb');
    }

    public function testMaxValidTeamidShowsTeamRecords(): void
    {
        $this->mockDb->setMockData([]);
        $output = $this->runModule('FranchiseRecordBook', ['teamid' => '28']);

        $this->assertNotEmpty($output);
        // isRealFranchise(28) === true (MAX_REAL_TEAMID = 28)
        $this->assertQueryExecuted('ibl_rcb');
    }

    public function testTeamidJustAboveMaxShowsLeagueRecords(): void
    {
        $this->mockDb->setMockData([]);
        $output = $this->runModule('FranchiseRecordBook', ['teamid' => '29']);

        $this->assertNotEmpty($output);
        // isRealFranchise(29) === false → league path
        $this->assertQueryExecuted('ibl_rcb');
    }
}
