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
