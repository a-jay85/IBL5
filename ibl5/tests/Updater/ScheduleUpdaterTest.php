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
     * Create a testable ScheduleUpdater subclass that exposes detectPlayoffBoundaryDay.
     */
    private function createUpdater(): TestableScheduleUpdater
    {
        $season = $this->createStub(\Season::class);
        $season->endingYear = 2025;
        $season->phase = 'Regular Season';

        return new TestableScheduleUpdater($this->mockDb, $season);
    }

    /**
     * Build a game array for offset 6 at a specific day (1-based).
     *
     * @return array{date_slot: int, game_index: int, visitor: int, home: int, visitor_score: int, home_score: int, played: bool}
     */
    private static function makeOffset6Game(int $day, bool $played = true, int $gameIndex = 0): array
    {
        $offset6Start = 6 * SchFileParser::DAYS_PER_MONTH;
        return [
            'date_slot' => $offset6Start + $day - 1,
            'game_index' => $gameIndex,
            'visitor' => 1,
            'home' => 2,
            'visitor_score' => $played ? 100 : 0,
            'home_score' => $played ? 95 : 0,
            'played' => $played,
        ];
    }

    public function testPlayoffBoundaryDetectedFromGapInPopulatedDays(): void
    {
        $updater = $this->createUpdater();

        // RS games on days 1-13, playoff games on days 15-16 (gap at day 14)
        $games = [];
        for ($d = 1; $d <= 13; $d++) {
            $games[] = self::makeOffset6Game($d);
        }
        $games[] = self::makeOffset6Game(15);
        $games[] = self::makeOffset6Game(16);

        $boundary = $updater->exposedDetectPlayoffBoundaryDay($games);

        $this->assertSame(15, $boundary);
    }

    public function testPlayoffBoundaryReturnsNullWhenNoOffset6Games(): void
    {
        $updater = $this->createUpdater();

        // Games only in offset 3 (January)
        $games = [
            [
                'date_slot' => 3 * SchFileParser::DAYS_PER_MONTH + 5,
                'game_index' => 0,
                'visitor' => 1,
                'home' => 2,
                'visitor_score' => 100,
                'home_score' => 95,
                'played' => true,
            ],
        ];

        $boundary = $updater->exposedDetectPlayoffBoundaryDay($games);

        $this->assertNull($boundary);
    }

    public function testPlayoffBoundaryReturnsNullWhenAllDaysContiguous(): void
    {
        $updater = $this->createUpdater();

        // All days 1-13 are contiguous, no gap → falls through to DB fallback
        // DB returns empty → null
        $this->mockDb->onQuery('ibl_box_scores_teams', []);

        $games = [];
        for ($d = 1; $d <= 13; $d++) {
            $games[] = self::makeOffset6Game($d);
        }

        $boundary = $updater->exposedDetectPlayoffBoundaryDay($games);

        $this->assertNull($boundary);
    }

    public function testPlayoffBoundaryFallsBackToBoxScores(): void
    {
        $updater = $this->createUpdater();

        // All days 1-20 contiguous (no gap) — forces box score fallback
        $games = [];
        for ($d = 1; $d <= 20; $d++) {
            $games[] = self::makeOffset6Game($d);
        }

        // Box scores indicate June games start on day 14
        $this->mockDb->onQuery('ibl_box_scores_teams', [['day_num' => 14], ['day_num' => 15]]);

        $boundary = $updater->exposedDetectPlayoffBoundaryDay($games);

        $this->assertSame(14, $boundary);
    }

    public function testRegularSeasonGamesKeepAprilDates(): void
    {
        $updater = $this->createUpdater();

        // Day 5 in offset 6, before boundary at day 14
        $month = 4; // April
        $day = 5;
        $result = $updater->exposedBuildDateString($month, $day, null);

        $this->assertSame('April 5, 2000', $result);
    }

    public function testPlayoffGamesGetJuneMonthOverride(): void
    {
        $updater = $this->createUpdater();

        // Day 15 in offset 6, after boundary — override to June
        $month = 4; // April (original)
        $day = 15;
        $result = $updater->exposedBuildDateString($month, $day, \Season::IBL_PLAYOFF_MONTH);

        $this->assertSame('June 15, 2000', $result);
    }
}

/**
 * Testable subclass that exposes protected methods for unit testing.
 */
class TestableScheduleUpdater extends \Updater\ScheduleUpdater
{
    /**
     * @param list<array{date_slot: int, game_index: int, visitor: int, home: int, visitor_score: int, home_score: int, played: bool}> $games
     */
    public function exposedDetectPlayoffBoundaryDay(array $games): ?int
    {
        return $this->detectPlayoffBoundaryDay($games);
    }

    public function exposedBuildDateString(int $month, int $day, ?int $monthOverride = null): string
    {
        return $this->buildDateString($month, $day, $monthOverride);
    }
}
