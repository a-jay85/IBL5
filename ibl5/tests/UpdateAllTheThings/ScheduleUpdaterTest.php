<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Updater\ScheduleUpdater;
use League\LeagueContext;

/**
 * Tests for ScheduleUpdater class
 *
 * Tests schedule update functionality including:
 * - Date extraction for different season phases
 * - Schedule database operations
 * - Team ID resolution
 */
class ScheduleUpdaterTest extends TestCase
{
    private MockDatabase $mockDb;
    private Season $mockSeason;
    private ScheduleUpdater $scheduleUpdater;
    private LeagueContext $leagueContext;

    protected function setUp(): void
    {
        // Initialize session for LeagueContext
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Initialize global LeagueContext for ScheduleUpdater
        global $leagueContext;
        $this->leagueContext = new LeagueContext();
        $leagueContext = $this->leagueContext;

        // Set default league to IBL for tests
        $_SESSION['current_league'] = LeagueContext::LEAGUE_IBL;

        $this->mockDb = new MockDatabase();
        $this->mockSeason = new Season($this->mockDb);
        $this->scheduleUpdater = new ScheduleUpdater($this->mockDb, $this->mockSeason);
    }

    protected function tearDown(): void
    {
        // Clean up global LeagueContext
        global $leagueContext;
        $leagueContext = null;

        // Clean up session
        if (isset($_SESSION['current_league'])) {
            unset($_SESSION['current_league']);
        }
    }

    /**
     * @group schedule-updater
     * @group date-extraction
     */
    public function testExtractDateConvertsPostToJune(): void
    {
        $reflection = new ReflectionClass($this->scheduleUpdater);
        $method = $reflection->getMethod('extractDate');

        $result = $method->invoke($this->scheduleUpdater, 'Post 15, 2024');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('month', $result);
        $this->assertArrayHasKey('day', $result);
        $this->assertArrayHasKey('year', $result);
        $this->assertSame(6, $result['month']);
        $this->assertSame(15, $result['day']);
    }

    /**
     * @group schedule-updater
     * @group date-extraction
     */
    public function testExtractDateHandlesPreseasonPhase(): void
    {
        $this->mockSeason->phase = 'Preseason';

        $reflection = new ReflectionClass($this->scheduleUpdater);
        $method = $reflection->getMethod('extractDate');

        $result = $method->invoke($this->scheduleUpdater, 'November 1, 2023');

        $this->assertIsArray($result);
        $this->assertSame(Season::IBL_REGULAR_SEASON_STARTING_MONTH, $result['month']);
    }

    /**
     * @group schedule-updater
     * @group date-extraction
     */
    public function testExtractDateHandlesHEATPhase(): void
    {
        $this->mockSeason->phase = 'HEAT';

        $reflection = new ReflectionClass($this->scheduleUpdater);
        $method = $reflection->getMethod('extractDate');

        $result = $method->invoke($this->scheduleUpdater, 'November 1, 2023');

        $this->assertIsArray($result);
        $this->assertSame(Season::IBL_HEAT_MONTH, $result['month']);
    }

    /**
     * @group schedule-updater
     * @group date-extraction
     */
    public function testExtractDateReturnsNullForEmptyString(): void
    {
        $reflection = new ReflectionClass($this->scheduleUpdater);
        $method = $reflection->getMethod('extractDate');

        $result = $method->invoke($this->scheduleUpdater, '');

        $this->assertNull($result);
    }

    /**
     * @group schedule-updater
     * @group database
     */
    public function testUpdateTruncatesScheduleTable(): void
    {
        $this->mockDb->setReturnTrue(true);

        ob_start();

        // update() will fail because the .sch file doesn't exist at DOCUMENT_ROOT, but TRUNCATE is executed first
        try {
            $this->scheduleUpdater->update();
        } catch (\RuntimeException $e) {
            // Expected: .sch file not found
        }

        ob_get_clean();

        $queries = $this->mockDb->getExecutedQueries();
        $this->assertNotEmpty($queries);
        $this->assertSame('TRUNCATE TABLE ibl_schedule', $queries[0]);
    }

    /**
     * @group schedule-updater
     * @group date-formatting
     */
    public function testExtractDateFormatsDateCorrectly(): void
    {
        $reflection = new ReflectionClass($this->scheduleUpdater);
        $method = $reflection->getMethod('extractDate');

        $result = $method->invoke($this->scheduleUpdater, 'January 15, 2024');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('date', $result);
        $this->assertStringContainsString('2024', $result['date']);
        $this->assertStringContainsString('1', $result['date']);
        $this->assertStringContainsString('15', $result['date']);
    }

    /**
     * @group schedule-updater
     * @group date-formatting
     */
    public function testExtractDateRemovesLeadingZeros(): void
    {
        $reflection = new ReflectionClass($this->scheduleUpdater);
        $method = $reflection->getMethod('extractDate');

        $result = $method->invoke($this->scheduleUpdater, 'January 05, 2024');

        $this->assertIsArray($result);
        $this->assertSame(5, $result['day']);
    }

    /**
     * @group schedule-updater
     * @group date-extraction
     */
    public function testExtractDateHandlesVariousDateFormats(): void
    {
        $reflection = new ReflectionClass($this->scheduleUpdater);
        $method = $reflection->getMethod('extractDate');

        $dates = [
            'November 1, 2023',
            'December 25, 2023',
            'January 1, 2024',
            'June 30, 2024',
        ];

        foreach ($dates as $dateString) {
            $result = $method->invoke($this->scheduleUpdater, $dateString);
            $this->assertIsArray($result);
            $this->assertArrayHasKey('date', $result);
            $this->assertArrayHasKey('month', $result);
            $this->assertArrayHasKey('day', $result);
            $this->assertArrayHasKey('year', $result);
        }
    }
}
