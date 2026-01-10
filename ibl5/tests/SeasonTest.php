<?php

declare(strict_types=1);

/**
 * SeasonTest - Tests for Season class
 */
class SeasonTest extends \PHPUnit\Framework\TestCase
{
    private object $mockDb;

    protected function setUp(): void
    {
        $this->mockDb = $this->createMockDatabase();
    }

    private function createMockDatabase(): object
    {
        return new class extends \mysqli {
            public array $mockData = [
                'phase' => 'Regular Season',
                'year' => '2025',
            ];

            public function __construct()
            {
                // Don't call parent constructor
            }

            #[\ReturnTypeWillChange]
            public function prepare(string $query)
            {
                $mockData = $this->mockData;
                return new class($mockData, $query) {
                    private array $mockData;
                    private string $query;
                    public function __construct(array $mockData, string $query)
                    {
                        $this->mockData = $mockData;
                        $this->query = $query;
                    }
                    public function bind_param(string $types, mixed &...$vars): bool
                    {
                        return true;
                    }
                    public function execute(): bool
                    {
                        return true;
                    }
                    public function get_result(): object
                    {
                        $data = $this->mockData;
                        $query = $this->query;
                        return new class($data, $query) {
                            private array $data;
                            private string $query;
                            private int $callCount = 0;
                            public int $num_rows = 1;
                            public function __construct(array $data, string $query)
                            {
                                $this->data = $data;
                                $this->query = $query;
                            }
                            public function fetch_assoc(): ?array
                            {
                                if ($this->callCount++ > 0) {
                                    return null;
                                }
                                
                                // Return appropriate data based on query
                                if (str_contains($this->query, 'ibl_settings')) {
                                    return ['value' => $this->data['phase']];
                                }
                                if (str_contains($this->query, 'ibl_season')) {
                                    return ['year' => $this->data['year']];
                                }
                                if (str_contains($this->query, 'ibl_schedule')) {
                                    return [
                                        'Sim' => 10,
                                        'Start Date' => '2025-01-01',
                                        'End Date' => '2025-01-07'
                                    ];
                                }
                                return ['value' => 'yes'];
                            }
                            public function fetch_object(): ?object
                            {
                                $row = $this->fetch_assoc();
                                return $row ? (object) $row : null;
                            }
                        };
                    }
                    public function close(): void
                    {
                    }
                };
            }

            #[\ReturnTypeWillChange]
            public function query(string $query, int $result_mode = MYSQLI_STORE_RESULT): \mysqli_result|bool
            {
                $data = $this->mockData;
                $mockResult = new class($data) {
                    private array $rows;
                    private int $index = 0;
                    public int $num_rows = 1;
                    public function __construct(array $rows)
                    {
                        $this->rows = $rows;
                    }
                    public function fetch_assoc(): ?array
                    {
                        return ['value' => $this->rows['phase']];
                    }
                    public function fetch_object(): ?object
                    {
                        return (object) ['value' => $this->rows['phase']];
                    }
                };
                return $mockResult;
            }
        };
    }

    // ============================================
    // CONSTANT TESTS
    // ============================================

    public function testIblPreseasonYearConstant(): void
    {
        $this->assertSame(9998, \Season::IBL_PRESEASON_YEAR);
    }

    public function testIblOlympicsMonthConstant(): void
    {
        $this->assertSame(8, \Season::IBL_OLYMPICS_MONTH);
    }

    public function testIblHeatMonthConstant(): void
    {
        $this->assertSame(10, \Season::IBL_HEAT_MONTH);
    }

    public function testIblRegularSeasonStartingMonthConstant(): void
    {
        $this->assertSame(11, \Season::IBL_REGULAR_SEASON_STARTING_MONTH);
    }

    public function testIblAllStarMonthConstant(): void
    {
        $this->assertSame(2, \Season::IBL_ALL_STAR_MONTH);
    }

    public function testIblRegularSeasonEndingMonthConstant(): void
    {
        $this->assertSame(5, \Season::IBL_REGULAR_SEASON_ENDING_MONTH);
    }

    public function testIblPlayoffMonthConstant(): void
    {
        $this->assertSame(6, \Season::IBL_PLAYOFF_MONTH);
    }

    // ============================================
    // INSTANTIATION TESTS
    // ============================================

    public function testCanBeInstantiated(): void
    {
        $season = new \Season($this->mockDb);

        $this->assertInstanceOf(\Season::class, $season);
    }

    public function testHasPhaseProperty(): void
    {
        $season = new \Season($this->mockDb);

        $this->assertTrue(property_exists($season, 'phase'));
    }

    public function testHasBeginningYearProperty(): void
    {
        $season = new \Season($this->mockDb);

        $this->assertTrue(property_exists($season, 'beginningYear'));
    }

    public function testHasEndingYearProperty(): void
    {
        $season = new \Season($this->mockDb);

        $this->assertTrue(property_exists($season, 'endingYear'));
    }

    public function testHasLastSimNumberProperty(): void
    {
        $season = new \Season($this->mockDb);

        $this->assertTrue(property_exists($season, 'lastSimNumber'));
    }

    public function testHasAllowTradesProperty(): void
    {
        $season = new \Season($this->mockDb);

        $this->assertTrue(property_exists($season, 'allowTrades'));
    }

    public function testHasAllowWaiversProperty(): void
    {
        $season = new \Season($this->mockDb);

        $this->assertTrue(property_exists($season, 'allowWaivers'));
    }
}
