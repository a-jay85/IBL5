<?php

declare(strict_types=1);

namespace Tests\TrainingCampRatingsDiff;

use PHPUnit\Framework\TestCase;
use TrainingCampRatingsDiff\Contracts\TrainingCampRatingsDiffRepositoryInterface;
use TrainingCampRatingsDiff\TrainingCampRatingsDiffRepository;
use Tests\WideUnit\Mocks\MockDatabase;

class TrainingCampRatingsDiffRepositoryTest extends TestCase
{
    private MockDatabase $mockDb;

    protected function setUp(): void
    {
        $this->mockDb = new MockDatabase();
    }

    public function testImplementsInterface(): void
    {
        $repository = new TrainingCampRatingsDiffRepository($this->mockDb);

        self::assertInstanceOf(TrainingCampRatingsDiffRepositoryInterface::class, $repository);
    }

    public function testGetLatestEndOfSeasonYearReturnsNullWhenNoSnapshotsExist(): void
    {
        $this->mockDb->onQuery('MAX\(season_year\)', [['y' => null]]);
        $repository = new TrainingCampRatingsDiffRepository($this->mockDb);

        $result = $repository->getLatestEndOfSeasonYear();

        self::assertNull($result);
    }

    public function testGetLatestEndOfSeasonYearReturnsMaxYearFromSnapshots(): void
    {
        $this->mockDb->onQuery('MAX\(season_year\)', [['y' => 2025]]);
        $repository = new TrainingCampRatingsDiffRepository($this->mockDb);

        $result = $repository->getLatestEndOfSeasonYear();

        self::assertSame(2025, $result);
    }

    public function testGetLatestEndOfSeasonYearCoercesStringNumericToInt(): void
    {
        // MariaDB typically returns ints natively, but older drivers may yield strings
        $this->mockDb->onQuery('MAX\(season_year\)', [['y' => '2024']]);
        $repository = new TrainingCampRatingsDiffRepository($this->mockDb);

        $result = $repository->getLatestEndOfSeasonYear();

        self::assertSame(2024, $result);
    }

    public function testGetDiffRowsQueriesLiveAndSnapshotTables(): void
    {
        $this->mockDb->setMockData([]);
        $repository = new TrainingCampRatingsDiffRepository($this->mockDb);

        $repository->getDiffRows(2024);

        $queries = $this->mockDb->getExecutedQueries();
        self::assertNotEmpty($queries);
        $combined = implode("\n", $queries);
        self::assertStringContainsString('ibl_plr', $combined);
        self::assertStringContainsString('ibl_plr_snapshots', $combined);
        self::assertStringContainsString('end-of-season', $combined);
        self::assertStringContainsString('retired = 0', $combined);
    }

    public function testGetDiffRowsAppliesFilterTidWhenSet(): void
    {
        $this->mockDb->setMockData([]);
        $repository = new TrainingCampRatingsDiffRepository($this->mockDb);

        $repository->getDiffRows(2024, 7);

        $queries = $this->mockDb->getExecutedQueries();
        $combined = implode("\n", $queries);
        // MockPreparedStatement substitutes bound parameters; the final query
        // should contain the literal teamid filter.
        self::assertStringContainsString('p.teamid = 7', $combined);
    }

    public function testGetDiffRowsReturnsRowsFromDatabase(): void
    {
        $row = ['pid' => 1, 'name' => 'Test Player', 'pos' => 'PG', 'teamid' => 5];
        $this->mockDb->setMockData([$row]);
        $repository = new TrainingCampRatingsDiffRepository($this->mockDb);

        $result = $repository->getDiffRows(2024);

        self::assertCount(1, $result);
        self::assertSame('Test Player', $result[0]['name']);
    }
}
