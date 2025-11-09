<?php

namespace Statistics;

use PHPUnit\Framework\TestCase;

class StatisticsRepositoryTest extends TestCase
{
    private $mockDb;
    private StatisticsRepository $repository;

    protected function setUp(): void
    {
        global $prefix, $user_prefix;
        $prefix = 'test';
        $user_prefix = 'nuke';
        
        $this->mockDb = $this->createMockDatabase();
        $this->repository = new StatisticsRepository($this->mockDb);
    }

    private function createMockDatabase()
    {
        return new class {
            private array $queryResults = [];
            private int $queryIndex = 0;

            public function setQueryResults(array $results): void
            {
                $this->queryResults = $results;
                $this->queryIndex = 0;
            }

            public function sql_query(string $query)
            {
                return $this;
            }

            public function sql_fetchrow($result): array|false
            {
                if ($this->queryIndex < count($this->queryResults)) {
                    return $this->queryResults[$this->queryIndex++];
                }
                return false;
            }

            public function sql_numrows($result): int
            {
                return count($this->queryResults);
            }
        };
    }

    public function testGetAllCountersReturnsProcessedData(): void
    {
        $this->mockDb->setQueryResults([
            ['type' => 'total', 'var' => 'hits', 'count' => 1000],
            ['type' => 'browser', 'var' => 'FireFox', 'count' => 300],
            ['type' => 'browser', 'var' => 'MSIE', 'count' => 200],
            ['type' => 'os', 'var' => 'Windows', 'count' => 500],
            ['type' => 'os', 'var' => 'Linux', 'count' => 300],
        ]);

        $result = $this->repository->getAllCounters();

        $this->assertEquals(1000, $result['total']);
        $this->assertEquals(300, $result['browsers']['FireFox']);
        $this->assertEquals(200, $result['browsers']['MSIE']);
        $this->assertEquals(500, $result['os']['Windows']);
        $this->assertEquals(300, $result['os']['Linux']);
    }

    public function testGetTotalHitsReturnsCorrectValue(): void
    {
        $this->mockDb->setQueryResults([
            ['count' => 5000]
        ]);

        $result = $this->repository->getTotalHits();

        $this->assertEquals(5000, $result);
    }

    public function testGetTotalHitsReturnsZeroWhenNoData(): void
    {
        $this->mockDb->setQueryResults([]);

        $result = $this->repository->getTotalHits();

        $this->assertEquals(0, $result);
    }

    public function testGetHighestTrafficMonthReturnsCorrectData(): void
    {
        $this->mockDb->setQueryResults([
            ['year' => 2024, 'month' => 11, 'hits' => 15000]
        ]);

        $result = $this->repository->getHighestTrafficMonth();

        $this->assertIsArray($result);
        $this->assertEquals(2024, $result['year']);
        $this->assertEquals(11, $result['month']);
        $this->assertEquals(15000, $result['hits']);
    }

    public function testGetHighestTrafficMonthReturnsNullWhenNoData(): void
    {
        $this->mockDb->setQueryResults([]);

        $result = $this->repository->getHighestTrafficMonth();

        $this->assertNull($result);
    }

    public function testGetHighestTrafficDayReturnsCorrectData(): void
    {
        $this->mockDb->setQueryResults([
            ['year' => 2024, 'month' => 11, 'date' => 15, 'hits' => 2500]
        ]);

        $result = $this->repository->getHighestTrafficDay();

        $this->assertIsArray($result);
        $this->assertEquals(2024, $result['year']);
        $this->assertEquals(11, $result['month']);
        $this->assertEquals(15, $result['date']);
        $this->assertEquals(2500, $result['hits']);
    }

    public function testGetHighestTrafficHourReturnsCorrectData(): void
    {
        $this->mockDb->setQueryResults([
            ['year' => 2024, 'month' => 11, 'date' => 15, 'hour' => 14, 'hits' => 250]
        ]);

        $result = $this->repository->getHighestTrafficHour();

        $this->assertIsArray($result);
        $this->assertEquals(2024, $result['year']);
        $this->assertEquals(11, $result['month']);
        $this->assertEquals(15, $result['date']);
        $this->assertEquals(14, $result['hour']);
        $this->assertEquals(250, $result['hits']);
    }

    public function testGetMiscCountsReturnsAllCounts(): void
    {
        $this->mockDb->setQueryResults([
            ['user_id' => 1],
            ['user_id' => 2],
            ['user_id' => 3],
        ]);

        $result = $this->repository->getMiscCounts();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('users', $result);
        $this->assertArrayHasKey('authors', $result);
        $this->assertArrayHasKey('stories', $result);
        $this->assertArrayHasKey('comments', $result);
    }

    public function testGetYearlyStatsReturnsMultipleYears(): void
    {
        $this->mockDb->setQueryResults([
            ['year' => 2022, 'hits' => 10000],
            ['year' => 2023, 'hits' => 12000],
            ['year' => 2024, 'hits' => 15000],
        ]);

        $result = $this->repository->getYearlyStats();

        $this->assertIsArray($result);
        $this->assertCount(3, $result);
        $this->assertEquals(2022, $result[0]['year']);
        $this->assertEquals(10000, $result[0]['hits']);
        $this->assertEquals(2024, $result[2]['year']);
        $this->assertEquals(15000, $result[2]['hits']);
    }

    public function testGetTotalYearlyHitsReturnsSumOfAllYears(): void
    {
        $this->mockDb->setQueryResults([
            ['total' => 37000]
        ]);

        $result = $this->repository->getTotalYearlyHits();

        $this->assertEquals(37000, $result);
    }

    public function testGetMonthlyStatsReturnsDataForSpecificYear(): void
    {
        $this->mockDb->setQueryResults([
            ['month' => 1, 'hits' => 1200],
            ['month' => 2, 'hits' => 1100],
            ['month' => 3, 'hits' => 1300],
        ]);

        $result = $this->repository->getMonthlyStats(2024);

        $this->assertIsArray($result);
        $this->assertCount(3, $result);
        $this->assertEquals(1, $result[0]['month']);
        $this->assertEquals(1200, $result[0]['hits']);
    }

    public function testGetDailyStatsReturnsDataForSpecificMonth(): void
    {
        $this->mockDb->setQueryResults([
            ['year' => 2024, 'month' => 11, 'date' => 1, 'hits' => 100],
            ['year' => 2024, 'month' => 11, 'date' => 2, 'hits' => 120],
            ['year' => 2024, 'month' => 11, 'date' => 3, 'hits' => 110],
        ]);

        $result = $this->repository->getDailyStats(2024, 11);

        $this->assertIsArray($result);
        $this->assertCount(3, $result);
        $this->assertEquals(1, $result[0]['date']);
        $this->assertEquals(100, $result[0]['hits']);
    }

    public function testGetHourlyStatsReturnsAllHours(): void
    {
        // Mock database to return hits for a few hours
        $this->mockDb->setQueryResults([
            ['hour' => 0, 'hits' => 10],
            ['hour' => 1, 'hits' => 5],
            // Hours 2-23 will have 0 hits
        ]);

        $result = $this->repository->getHourlyStats(2024, 11, 7);

        $this->assertIsArray($result);
        $this->assertCount(24, $result);
        $this->assertEquals(10, $result[0]);
        $this->assertEquals(5, $result[1]);
        $this->assertEquals(0, $result[2]);
    }
}
