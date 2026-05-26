<?php

declare(strict_types=1);

namespace Tests\Updater;

use League\LeagueContext;
use PHPUnit\Framework\TestCase;
use Tests\WideUnit\Mocks\MockDatabase;
use Season\Season;

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

    private function createUpdater(string $phase = 'Regular Season', int $endingYear = 2025, bool $olympics = false): TestableScheduleUpdater
    {
        $season = $this->createStub(Season::class);
        $season->endingYear = $endingYear;
        $season->beginningYear = $endingYear - 1;
        $season->phase = $phase;

        $leagueContext = $this->createStub(LeagueContext::class);
        $leagueContext->method('getCurrentLeague')->willReturn($olympics ? 'olympics' : 'IBL');
        $leagueContext->method('isOlympics')->willReturn($olympics);
        $leagueContext->method('getTableName')->willReturnCallback(
            static fn (string $table): string => $olympics
                ? str_replace('ibl_', 'ibl_olympics_', $table)
                : $table,
        );

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

    public function testOlympicsPreloadLoadsAllTeamsWithoutFilter(): void
    {
        $this->mockDb->setMockData([
            ['team_name' => 'Eagles', 'teamid' => 1],
            ['team_name' => 'Maple', 'teamid' => 2],
            ['team_name' => 'Filler29', 'teamid' => 29],
        ]);

        $updater = $this->createUpdater(olympics: true);
        $updater->exposedPreloadTeamNameMap();

        $map = $updater->getTeamNameToIdMap();
        $this->assertCount(3, $map);
        $this->assertSame(29, $map['Filler29']);
    }

    public function testIblPreloadFiltersToMaxRealTeamId(): void
    {
        $this->mockDb->setMockData([
            ['team_name' => 'Celtics', 'teamid' => 1],
            ['team_name' => 'Lakers', 'teamid' => 2],
        ]);

        $updater = $this->createUpdater(olympics: false);
        $updater->exposedPreloadTeamNameMap();

        $queries = $this->mockDb->getExecutedQueries();
        $matched = array_filter($queries, static fn (string $q): bool => str_contains($q, 'BETWEEN 1 AND'));
        $this->assertNotEmpty($matched);
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

    public function exposedPreloadTeamNameMap(): void
    {
        $reflection = new \ReflectionMethod(parent::class, 'preloadTeamNameMap');
        $reflection->invoke($this);
    }

    /** @return array<string, int> */
    public function getTeamNameToIdMap(): array
    {
        $prop = new \ReflectionProperty(parent::class, 'teamNameToIdMap');
        /** @var array<string, int> */
        return $prop->getValue($this);
    }
}
