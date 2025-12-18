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
        return new class extends \MockDatabase {
            private array $queryResults = [];
            private array $sharedState = ['fetchIndex' => 0];

            public function setQueryResults(array $results): void
            {
                $this->queryResults = $results;
                $this->setMockData($results);
                $this->sharedState['fetchIndex'] = 0;
            }
            
            // Override prepare to handle both fetchAll and fetchOne patterns
            // For fetchOne, we need to track consumption across multiple prepare() calls
            public function prepare($query)
            {
                $sharedState = &$this->sharedState;
                $results = $this->queryResults;
                
                $stmt = new class($this, $results, $sharedState) extends \MockPreparedStatement {
                    private array $results;
                    private array $sharedState;
                    
                    public function __construct($db, array $results, array &$state) {
                        parent::__construct($db, '');
                        $this->results = $results;
                        $this->sharedState = &$state;
                    }
                    
                    public function get_result(): object|false
                    {
                        // For fetchOne pattern: consume one result and return it, then return null for subsequent fetches
                        $currentIndex = $this->sharedState['fetchIndex'];
                        $this->sharedState['fetchIndex']++; // Consume the result for the next prepare() call
                        
                        return new class($this->results, $currentIndex) extends \MockMysqliResult {
                            private int $localFetchIndex = 0;
                            private array $allResults;
                            private int $startIndex;
                            
                            public function __construct(array $results, int $startIndex) {
                                $mockResult = new \MockDatabaseResult($results);
                                parent::__construct($mockResult);
                                $this->allResults = $results;
                                $this->startIndex = $startIndex;
                                $this->localFetchIndex = 0;
                            }
                            
                            public function fetch_assoc(): array|null|false
                            {
                                // For fetchOne: return only the result at startIndex on first fetch
                                if ($this->localFetchIndex === 0) {
                                    $this->localFetchIndex++;
                                    if ($this->startIndex < count($this->allResults)) {
                                        return $this->allResults[$this->startIndex];
                                    }
                                    return null;
                                }
                                // For fetchAll: continue fetching from the array
                                $absoluteIndex = $this->startIndex + $this->localFetchIndex;
                                if ($absoluteIndex < count($this->allResults)) {
                                    $this->localFetchIndex++;
                                    return $this->allResults[$absoluteIndex];
                                }
                                return null;
                            }
                        };
                    }
                };
                return $stmt;
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
        // The method makes 24 separate queries (one per hour)
        // We need to set up the mock to return appropriate data for each query
        // Since the mock returns the same data for each query, we simulate the pattern
        // where only hours 0 and 1 have hits, rest return null
        $this->mockDb->setQueryResults([
            ['hour' => 0, 'hits' => 10],
        ]);

        $result = $this->repository->getHourlyStats(2024, 11, 7);

        // Method should return array with 24 entries (one for each hour)
        $this->assertIsArray($result);
        $this->assertCount(24, $result);
        
        // First hour should have the data from our mock
        $this->assertEquals(10, $result[0]);
        
        // Remaining hours should be 0 (no data)
        // The method returns 0 when fetchOne returns null
        for ($i = 1; $i < 24; $i++) {
            $this->assertEquals(0, $result[$i], "Hour $i should be 0");
        }
    }
}
