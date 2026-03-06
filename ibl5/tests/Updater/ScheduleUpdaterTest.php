<?php

declare(strict_types=1);

namespace Tests\Updater;

use League\LeagueContext;
use PHPUnit\Framework\TestCase;
use Tests\Integration\Mocks\MockDatabase;

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

    private function createUpdater(string $phase = 'Regular Season', int $endingYear = 2025): TestableScheduleUpdater
    {
        $season = $this->createStub(\Season::class);
        $season->endingYear = $endingYear;
        $season->beginningYear = $endingYear - 1;
        $season->phase = $phase;

        $leagueContext = $this->createStub(LeagueContext::class);
        $leagueContext->method('getCurrentLeague')->willReturn('IBL');

        return new TestableScheduleUpdater($this->mockDb, $season, $leagueContext);
    }

    public function testExtractDateReturnsNullForEmptyString(): void
    {
        $updater = $this->createUpdater();

        $this->assertNull($updater->exposedExtractDate(''));
    }

    public function testExtractDateParsesNovemberDate(): void
    {
        $updater = $this->createUpdater();

        $result = $updater->exposedExtractDate('November 5, 2000');

        $this->assertNotNull($result);
        $this->assertSame(11, $result['month']);
        $this->assertSame(5, $result['day']);
        $this->assertSame(2024, $result['year']);
    }

    public function testExtractDateParsesAprilDate(): void
    {
        $updater = $this->createUpdater();

        $result = $updater->exposedExtractDate('April 10, 2000');

        $this->assertNotNull($result);
        $this->assertSame(4, $result['month']);
        $this->assertSame(10, $result['day']);
        $this->assertSame(2025, $result['year']);
    }

    public function testExtractDateParsesJuneDate(): void
    {
        $updater = $this->createUpdater();

        $result = $updater->exposedExtractDate('June 15, 2000');

        $this->assertNotNull($result);
        $this->assertSame(6, $result['month']);
        $this->assertSame(15, $result['day']);
        $this->assertSame(2025, $result['year']);
    }
}

/**
 * Testable subclass that exposes protected methods for unit testing.
 */
class TestableScheduleUpdater extends \Updater\ScheduleUpdater
{
    /**
     * @return array{date: string, year: int, month: int, day: int}|null
     */
    public function exposedExtractDate(string $rawDate): ?array
    {
        return $this->extractDate($rawDate);
    }
}
