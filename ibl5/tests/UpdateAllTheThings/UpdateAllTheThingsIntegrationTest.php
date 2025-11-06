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
 * - Production constants validation
 * - Complete workflow integration
 */
class UpdateAllTheThingsIntegrationTest extends TestCase
{
    private $mockDb;
    private $mockCommonRepository;
    private $mockSeason;

    protected function setUp(): void
    {
        $this->mockDb = new MockDatabase();
        $this->mockCommonRepository = new \Services\CommonRepository($this->mockDb);
        $this->mockSeason = new Season($this->mockDb);
    }

    protected function tearDown(): void
    {
        $this->mockDb = null;
        $this->mockCommonRepository = null;
        $this->mockSeason = null;
    }

    /**
     * @group integration
     * @group component-initialization
     */
    public function testAllComponentsCanBeInitialized()
    {
        $scheduleUpdater = new ScheduleUpdater($this->mockDb, $this->mockCommonRepository, $this->mockSeason);
        $standingsUpdater = new StandingsUpdater($this->mockDb, $this->mockCommonRepository);
        $powerRankingsUpdater = new PowerRankingsUpdater($this->mockDb, $this->mockSeason);
        $standingsHTMLGenerator = new StandingsHTMLGenerator($this->mockDb);
        
        $this->assertInstanceOf(ScheduleUpdater::class, $scheduleUpdater);
        $this->assertInstanceOf(StandingsUpdater::class, $standingsUpdater);
        $this->assertInstanceOf(PowerRankingsUpdater::class, $powerRankingsUpdater);
        $this->assertInstanceOf(StandingsHTMLGenerator::class, $standingsHTMLGenerator);
    }

    /**
     * @group integration
     * @group production-constants
     */
    public function testProductionConstantsAreAccessible()
    {
        // Season constants from production
        $this->assertEquals(9, Season::IBL_PRESEASON_MONTH);
        $this->assertEquals(10, Season::IBL_HEAT_MONTH);
        $this->assertEquals(11, Season::IBL_REGULAR_SEASON_STARTING_MONTH);
        $this->assertEquals(2, Season::IBL_ALL_STAR_MONTH);
        $this->assertEquals(5, Season::IBL_REGULAR_SEASON_ENDING_MONTH);
        $this->assertEquals(6, Season::IBL_PLAYOFF_MONTH);
        
        // League constants from production
        $this->assertEquals(['Eastern', 'Western'], League::CONFERENCE_NAMES);
        $this->assertEquals(['Atlantic', 'Central', 'Midwest', 'Pacific'], League::DIVISION_NAMES);
        $this->assertEquals(0, League::FREE_AGENTS_TEAMID);
        $this->assertEquals(7000, League::HARD_CAP_MAX);
    }
}
