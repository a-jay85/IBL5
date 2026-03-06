<?php

declare(strict_types=1);

namespace Tests\Updater;

use PHPUnit\Framework\TestCase;
use Tests\Integration\Mocks\MockDatabase;
use Utilities\SchFileParser;

/**
 * @covers \Updater\ScheduleUpdater
 */
class ScheduleUpdaterTest extends TestCase
{
    private MockDatabase $mockDb;

    protected function setUp(): void
    {
        $this->mockDb = new MockDatabase();
    }

    /**
     * Create a testable ScheduleUpdater subclass that exposes protected methods.
     */
    private function createUpdater(): TestableScheduleUpdater
    {
        $season = $this->createStub(\Season::class);
        $season->endingYear = 2025;
        $season->phase = 'Regular Season';

        return new TestableScheduleUpdater($this->mockDb, $season);
    }

    public function testGetPlayoffMatchupsReturnsTeamPairings(): void
    {
        $updater = $this->createUpdater();

        $this->mockDb->onQuery('ibl_box_scores_teams', [
            ['visitorTeamID' => 6, 'homeTeamID' => 17],
            ['visitorTeamID' => 9, 'homeTeamID' => 22],
            ['visitorTeamID' => 17, 'homeTeamID' => 6],
        ]);

        $matchups = $updater->exposedGetPlayoffMatchups();

        $this->assertTrue(isset($matchups['6-17']));
        $this->assertTrue(isset($matchups['9-22']));
        $this->assertTrue(isset($matchups['17-6']));
        $this->assertFalse(isset($matchups['1-2']));
    }

    public function testGetPlayoffMatchupsReturnsEmptyWhenNoJuneGames(): void
    {
        $updater = $this->createUpdater();

        $this->mockDb->onQuery('ibl_box_scores_teams', []);

        $matchups = $updater->exposedGetPlayoffMatchups();

        $this->assertSame([], $matchups);
    }

    public function testRegularSeasonGamesKeepAprilDates(): void
    {
        $updater = $this->createUpdater();

        $result = $updater->exposedBuildDateString(4, 5, null);

        $this->assertSame('April 5, 2000', $result);
    }

    public function testPlayoffGamesGetJuneMonthOverride(): void
    {
        $updater = $this->createUpdater();

        $result = $updater->exposedBuildDateString(4, 15, \Season::IBL_PLAYOFF_MONTH);

        $this->assertSame('June 15, 2000', $result);
    }

    public function testMonthOverridePreservesDay(): void
    {
        $updater = $this->createUpdater();

        $result = $updater->exposedBuildDateString(4, 22, \Season::IBL_PLAYOFF_MONTH);

        $this->assertSame('June 22, 2000', $result);
    }

    public function testBuildDateStringWithoutOverrideUsesOriginalMonth(): void
    {
        $updater = $this->createUpdater();

        $result = $updater->exposedBuildDateString(11, 15, null);

        $this->assertSame('November 15, 2000', $result);
    }
}

/**
 * Testable subclass that exposes protected methods for unit testing.
 */
class TestableScheduleUpdater extends \Updater\ScheduleUpdater
{
    /**
     * @return array<string, true>
     */
    public function exposedGetPlayoffMatchups(): array
    {
        return $this->getPlayoffMatchups();
    }

    public function exposedBuildDateString(int $month, int $day, ?int $monthOverride = null): string
    {
        return $this->buildDateString($month, $day, $monthOverride);
    }
}
