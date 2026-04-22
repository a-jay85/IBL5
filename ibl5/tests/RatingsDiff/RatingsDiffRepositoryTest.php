<?php

declare(strict_types=1);

namespace Tests\RatingsDiff;

use PHPUnit\Framework\TestCase;
use RatingsDiff\Contracts\RatingsDiffRepositoryInterface;
use RatingsDiff\RatingsDiffRepository;

class RatingsDiffRepositoryTest extends TestCase
{
    private \MockDatabase $mockDb;
    private \mysqli $mockMysqliDb;

    protected function setUp(): void
    {
        $this->mockDb = new \MockDatabase();
        $this->mockMysqliDb = $this->buildMockMysqliDb($this->mockDb);
    }

    public function testImplementsInterface(): void
    {
        $repository = new RatingsDiffRepository($this->mockMysqliDb);

        self::assertInstanceOf(RatingsDiffRepositoryInterface::class, $repository);
    }

    public function testGetLatestEndOfSeasonYearReturnsNullWhenNoSnapshotsExist(): void
    {
        $this->mockDb->onQuery('MAX\(season_year\)', [['y' => null]]);
        $repository = new RatingsDiffRepository($this->mockMysqliDb);

        $result = $repository->getLatestEndOfSeasonYear();

        self::assertNull($result);
    }

    public function testGetLatestEndOfSeasonYearReturnsMaxYearFromSnapshots(): void
    {
        $this->mockDb->onQuery('MAX\(season_year\)', [['y' => 2025]]);
        $repository = new RatingsDiffRepository($this->mockMysqliDb);

        $result = $repository->getLatestEndOfSeasonYear();

        self::assertSame(2025, $result);
    }

    public function testGetLatestEndOfSeasonYearCoercesStringNumericToInt(): void
    {
        // MariaDB typically returns ints natively, but older drivers may yield strings
        $this->mockDb->onQuery('MAX\(season_year\)', [['y' => '2024']]);
        $repository = new RatingsDiffRepository($this->mockMysqliDb);

        $result = $repository->getLatestEndOfSeasonYear();

        self::assertSame(2024, $result);
    }

    public function testGetDiffRowsQueriesLiveAndSnapshotTables(): void
    {
        $this->mockDb->setMockData([]);
        $repository = new RatingsDiffRepository($this->mockMysqliDb);

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
        $repository = new RatingsDiffRepository($this->mockMysqliDb);

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
        $repository = new RatingsDiffRepository($this->mockMysqliDb);

        $result = $repository->getDiffRows(2024);

        self::assertCount(1, $result);
        self::assertSame('Test Player', $result[0]['name']);
    }

    private function buildMockMysqliDb(\MockDatabase $mockDb): \mysqli
    {
        return new class($mockDb) extends \mysqli {
            private \MockDatabase $mockDb;
            public int $connect_errno = 0;
            public ?string $connect_error = null;

            public function __construct(\MockDatabase $mockDb)
            {
                $this->mockDb = $mockDb;
            }

            #[\ReturnTypeWillChange]
            public function prepare(string $query): \MockPreparedStatement|false
            {
                return new \MockPreparedStatement($this->mockDb, $query);
            }
        };
    }
}
