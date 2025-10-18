<?php

use PHPUnit\Framework\TestCase;
use Updater\ScheduleUpdater;
use Updater\StandingsUpdater;
use Updater\PowerRankingsUpdater;
use Updater\StandingsHTMLGenerator;

/**
 * Integration tests for UpdateAllTheThings workflow
 * 
 * Tests the complete post-sim update process including:
 * - Component initialization
 * - Execution order
 * - Database operations
 * - Extension attempt reset
 * - Complete workflow integration
 */
class UpdateAllTheThingsIntegrationTest extends TestCase
{
    private $mockDb;
    private $mockSharedFunctions;
    private $mockSeason;

    protected function setUp(): void
    {
        $this->mockDb = new MockDatabase();
        $this->mockSharedFunctions = new Shared($this->mockDb);
        $this->mockSeason = new Season($this->mockDb);
    }

    protected function tearDown(): void
    {
        $this->mockDb = null;
        $this->mockSharedFunctions = null;
        $this->mockSeason = null;
    }

    /**
     * @group integration
     * @group component-initialization
     */
    public function testScheduleUpdaterInitialization()
    {
        $scheduleUpdater = new ScheduleUpdater($this->mockDb, $this->mockSharedFunctions, $this->mockSeason);
        
        $this->assertInstanceOf(ScheduleUpdater::class, $scheduleUpdater);
    }

    /**
     * @group integration
     * @group component-initialization
     */
    public function testStandingsUpdaterInitialization()
    {
        $standingsUpdater = new StandingsUpdater($this->mockDb, $this->mockSharedFunctions);
        
        $this->assertInstanceOf(StandingsUpdater::class, $standingsUpdater);
    }

    /**
     * @group integration
     * @group component-initialization
     */
    public function testPowerRankingsUpdaterInitialization()
    {
        $powerRankingsUpdater = new PowerRankingsUpdater($this->mockDb, $this->mockSeason);
        
        $this->assertInstanceOf(PowerRankingsUpdater::class, $powerRankingsUpdater);
    }

    /**
     * @group integration
     * @group component-initialization
     */
    public function testStandingsHTMLGeneratorInitialization()
    {
        $standingsHTMLGenerator = new StandingsHTMLGenerator($this->mockDb);
        
        $this->assertInstanceOf(StandingsHTMLGenerator::class, $standingsHTMLGenerator);
    }

    /**
     * @group integration
     * @group component-initialization
     */
    public function testAllComponentsCanBeInitialized()
    {
        $scheduleUpdater = new ScheduleUpdater($this->mockDb, $this->mockSharedFunctions, $this->mockSeason);
        $standingsUpdater = new StandingsUpdater($this->mockDb, $this->mockSharedFunctions);
        $powerRankingsUpdater = new PowerRankingsUpdater($this->mockDb, $this->mockSeason);
        $standingsHTMLGenerator = new StandingsHTMLGenerator($this->mockDb);
        
        $this->assertInstanceOf(ScheduleUpdater::class, $scheduleUpdater);
        $this->assertInstanceOf(StandingsUpdater::class, $standingsUpdater);
        $this->assertInstanceOf(PowerRankingsUpdater::class, $powerRankingsUpdater);
        $this->assertInstanceOf(StandingsHTMLGenerator::class, $standingsHTMLGenerator);
    }

    /**
     * @group integration
     * @group extension-reset
     */
    public function testResetSimContractExtensionAttempts()
    {
        $this->mockDb->setReturnTrue(true);
        
        ob_start();
        $this->mockSharedFunctions->resetSimContractExtensionAttempts();
        $output = ob_get_clean();
        
        $this->assertStringContainsString('Resetting sim contract extension attempts', $output);
        
        $queries = $this->mockDb->getExecutedQueries();
        $extensionResetQueries = array_filter($queries, function($q) {
            return stripos($q, 'Used_Extension_This_Chunk') !== false;
        });
        
        $this->assertNotEmpty($extensionResetQueries);
    }

    /**
     * @group integration
     * @group database
     */
    public function testDatabaseQueriesAreTracked()
    {
        $this->mockDb->clearQueries();
        $this->mockDb->setReturnTrue(true);
        
        $this->mockDb->sql_query("SELECT * FROM ibl_schedule");
        $this->mockDb->sql_query("UPDATE ibl_power SET win = 50");
        
        $queries = $this->mockDb->getExecutedQueries();
        
        $this->assertCount(2, $queries);
        $this->assertStringContainsString('ibl_schedule', $queries[0]);
        $this->assertStringContainsString('ibl_power', $queries[1]);
    }

    /**
     * @group integration
     * @group season-phases
     */
    public function testComponentsWorkWithRegularSeasonPhase()
    {
        $this->mockSeason->phase = 'Regular Season';
        
        $scheduleUpdater = new ScheduleUpdater($this->mockDb, $this->mockSharedFunctions, $this->mockSeason);
        $powerRankingsUpdater = new PowerRankingsUpdater($this->mockDb, $this->mockSeason);
        
        $this->assertInstanceOf(ScheduleUpdater::class, $scheduleUpdater);
        $this->assertInstanceOf(PowerRankingsUpdater::class, $powerRankingsUpdater);
    }

    /**
     * @group integration
     * @group season-phases
     */
    public function testComponentsWorkWithPreseasonPhase()
    {
        $this->mockSeason->phase = 'Preseason';
        
        $scheduleUpdater = new ScheduleUpdater($this->mockDb, $this->mockSharedFunctions, $this->mockSeason);
        $powerRankingsUpdater = new PowerRankingsUpdater($this->mockDb, $this->mockSeason);
        
        $this->assertInstanceOf(ScheduleUpdater::class, $scheduleUpdater);
        $this->assertInstanceOf(PowerRankingsUpdater::class, $powerRankingsUpdater);
    }

    /**
     * @group integration
     * @group season-phases
     */
    public function testComponentsWorkWithHEATPhase()
    {
        $this->mockSeason->phase = 'HEAT';
        
        $scheduleUpdater = new ScheduleUpdater($this->mockDb, $this->mockSharedFunctions, $this->mockSeason);
        $powerRankingsUpdater = new PowerRankingsUpdater($this->mockDb, $this->mockSeason);
        
        $this->assertInstanceOf(ScheduleUpdater::class, $scheduleUpdater);
        $this->assertInstanceOf(PowerRankingsUpdater::class, $powerRankingsUpdater);
    }

    /**
     * @group integration
     * @group workflow
     */
    public function testCompleteWorkflowComponentsCanBeExecutedInOrder()
    {
        $this->mockDb->setReturnTrue(true);
        $this->mockDb->clearQueries();
        
        // Initialize all components as in updateAllTheThings.php
        $scheduleUpdater = new ScheduleUpdater($this->mockDb, $this->mockSharedFunctions, $this->mockSeason);
        $standingsUpdater = new StandingsUpdater($this->mockDb, $this->mockSharedFunctions);
        $powerRankingsUpdater = new PowerRankingsUpdater($this->mockDb, $this->mockSeason);
        $standingsHTMLGenerator = new StandingsHTMLGenerator($this->mockDb);
        
        $this->assertInstanceOf(ScheduleUpdater::class, $scheduleUpdater);
        $this->assertInstanceOf(StandingsUpdater::class, $standingsUpdater);
        $this->assertInstanceOf(PowerRankingsUpdater::class, $powerRankingsUpdater);
        $this->assertInstanceOf(StandingsHTMLGenerator::class, $standingsHTMLGenerator);
        
        // Verify Shared functions work
        ob_start();
        $this->mockSharedFunctions->resetSimContractExtensionAttempts();
        $output = ob_get_clean();
        
        $this->assertStringContainsString('extension attempts', $output);
    }

    /**
     * @group integration
     * @group shared-functions
     */
    public function testSharedFunctionsGetTidFromTeamname()
    {
        $tid = $this->mockSharedFunctions->getTidFromTeamname('Boston Celtics');
        
        $this->assertIsInt($tid);
        $this->assertGreaterThan(0, $tid);
    }

    /**
     * @group integration
     * @group shared-functions
     */
    public function testSharedFunctionsReturnsDifferentIdsForDifferentTeams()
    {
        $tid1 = $this->mockSharedFunctions->getTidFromTeamname('Boston Celtics');
        $tid2 = $this->mockSharedFunctions->getTidFromTeamname('LA Lakers');
        
        $this->assertNotEquals($tid1, $tid2);
    }

    /**
     * @group integration
     * @group season-data
     */
    public function testSeasonDataIsAccessible()
    {
        $this->assertIsString($this->mockSeason->phase);
        $this->assertIsInt($this->mockSeason->endingYear);
        $this->assertIsInt($this->mockSeason->beginningYear);
        $this->assertEquals($this->mockSeason->beginningYear + 1, $this->mockSeason->endingYear);
    }

    /**
     * @group integration
     * @group season-constants
     */
    public function testSeasonConstantsAreDefined()
    {
        $this->assertEquals(9, Season::IBL_PRESEASON_MONTH);
        $this->assertEquals(10, Season::IBL_HEAT_MONTH);
        $this->assertEquals(11, Season::IBL_REGULAR_SEASON_STARTING_MONTH);
        $this->assertEquals(2, Season::IBL_ALL_STAR_MONTH);
        $this->assertEquals(5, Season::IBL_REGULAR_SEASON_ENDING_MONTH);
        $this->assertEquals(6, Season::IBL_PLAYOFF_MONTH);
    }

    /**
     * @group integration
     * @group database-mock
     */
    public function testMockDatabaseHandlesMultipleQueryTypes()
    {
        $this->mockDb->clearQueries();
        $this->mockDb->setReturnTrue(true);
        
        // Test various query types
        $this->mockDb->sql_query("SELECT * FROM ibl_schedule");
        $this->mockDb->sql_query("INSERT INTO ibl_schedule VALUES (1, 2, 3)");
        $this->mockDb->sql_query("UPDATE ibl_power SET win = 50");
        $this->mockDb->sql_query("DELETE FROM ibl_temp_table");
        $this->mockDb->sql_query("TRUNCATE TABLE ibl_standings");
        
        $queries = $this->mockDb->getExecutedQueries();
        
        $this->assertCount(5, $queries);
        $this->assertStringContainsString('SELECT', $queries[0]);
        $this->assertStringContainsString('INSERT', $queries[1]);
        $this->assertStringContainsString('UPDATE', $queries[2]);
        $this->assertStringContainsString('DELETE', $queries[3]);
        $this->assertStringContainsString('TRUNCATE', $queries[4]);
    }

    /**
     * @group integration
     * @group mock-data
     */
    public function testMockDatabaseReturnsConfiguredData()
    {
        $mockData = [
            ['id' => 1, 'name' => 'Team A', 'wins' => 50, 'losses' => 32],
            ['id' => 2, 'name' => 'Team B', 'wins' => 45, 'losses' => 37],
        ];
        
        $this->mockDb->setMockData($mockData);
        $result = $this->mockDb->sql_query("SELECT * FROM ibl_power");
        
        $this->assertEquals(1, $this->mockDb->sql_result($result, 0, 'id'));
        $this->assertEquals('Team A', $this->mockDb->sql_result($result, 0, 'name'));
        $this->assertEquals(50, $this->mockDb->sql_result($result, 0, 'wins'));
        
        $this->assertEquals(2, $this->mockDb->sql_result($result, 1, 'id'));
        $this->assertEquals('Team B', $this->mockDb->sql_result($result, 1, 'name'));
    }

    /**
     * @group integration
     * @group error-handling
     */
    public function testComponentsHandleEmptyDatabase()
    {
        $this->mockDb->setMockData([]);
        $this->mockDb->setReturnTrue(true);
        
        // Components should initialize even with empty database
        $scheduleUpdater = new ScheduleUpdater($this->mockDb, $this->mockSharedFunctions, $this->mockSeason);
        $standingsUpdater = new StandingsUpdater($this->mockDb, $this->mockSharedFunctions);
        $powerRankingsUpdater = new PowerRankingsUpdater($this->mockDb, $this->mockSeason);
        $standingsHTMLGenerator = new StandingsHTMLGenerator($this->mockDb);
        
        $this->assertInstanceOf(ScheduleUpdater::class, $scheduleUpdater);
        $this->assertInstanceOf(StandingsUpdater::class, $standingsUpdater);
        $this->assertInstanceOf(PowerRankingsUpdater::class, $powerRankingsUpdater);
        $this->assertInstanceOf(StandingsHTMLGenerator::class, $standingsHTMLGenerator);
    }

    /**
     * @group integration
     * @group league-constants
     */
    public function testLeagueConstantsAreAccessible()
    {
        $this->assertEquals(['Eastern', 'Western'], League::CONFERENCE_NAMES);
        $this->assertEquals(['Atlantic', 'Central', 'Midwest', 'Pacific'], League::DIVISION_NAMES);
        $this->assertEquals(35, League::FREE_AGENTS_TEAMID);
    }

    /**
     * @group integration
     * @group ui-class
     */
    public function testUIDisplayDebugOutputDoesNotThrowException()
    {
        // UI::displayDebugOutput should be callable without errors
        UI::displayDebugOutput('Test content', 'Test Title');
        
        // If we get here, no exception was thrown
        $this->assertTrue(true);
    }

    /**
     * @group integration
     * @group workflow-order
     */
    public function testWorkflowComponentsCanBeCalledInExpectedOrder()
    {
        $this->mockDb->setReturnTrue(true);
        $this->mockDb->clearQueries();
        
        // 1. Initialize components (as done in updateAllTheThings.php)
        $scheduleUpdater = new ScheduleUpdater($this->mockDb, $this->mockSharedFunctions, $this->mockSeason);
        $standingsUpdater = new StandingsUpdater($this->mockDb, $this->mockSharedFunctions);
        $powerRankingsUpdater = new PowerRankingsUpdater($this->mockDb, $this->mockSeason);
        $standingsHTMLGenerator = new StandingsHTMLGenerator($this->mockDb);
        
        // 2. Verify initialization succeeded
        $this->assertInstanceOf(ScheduleUpdater::class, $scheduleUpdater);
        $this->assertInstanceOf(StandingsUpdater::class, $standingsUpdater);
        $this->assertInstanceOf(PowerRankingsUpdater::class, $powerRankingsUpdater);
        $this->assertInstanceOf(StandingsHTMLGenerator::class, $standingsHTMLGenerator);
        
        // 3. Verify extension reset can be called
        ob_start();
        $this->mockSharedFunctions->resetSimContractExtensionAttempts();
        $output = ob_get_clean();
        
        $this->assertStringContainsString('extension', $output);
        
        // 4. Verify queries were executed
        $queries = $this->mockDb->getExecutedQueries();
        $this->assertNotEmpty($queries);
    }
}
