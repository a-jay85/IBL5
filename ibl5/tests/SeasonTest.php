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
                                    return ['max_date' => null];
                                }
                                if (str_contains($this->query, 'ibl_sim_dates')) {
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
            public function query(string $query, int $result_mode = MYSQLI_STORE_RESULT)
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
                /** @phpstan-ignore-next-line */
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

    public function testIblAllStarBreakStartDayConstant(): void
    {
        $this->assertSame(1, \Season::IBL_ALL_STAR_BREAK_START_DAY);
    }

    public function testIblRisingStarsGameDayConstant(): void
    {
        $this->assertSame(2, \Season::IBL_RISING_STARS_GAME_DAY);
    }

    public function testIblAllStarGameDayConstant(): void
    {
        $this->assertSame(3, \Season::IBL_ALL_STAR_GAME_DAY);
    }

    public function testIblAllStarBreakEndDayConstant(): void
    {
        $this->assertSame(4, \Season::IBL_ALL_STAR_BREAK_END_DAY);
    }

    public function testIblPostAllStarFirstDayConstant(): void
    {
        $this->assertSame(5, \Season::IBL_POST_ALL_STAR_FIRST_DAY);
    }

    // ============================================
    // INSTANTIATION TESTS
    // ============================================

    public function testCanBeInstantiated(): void
    {
        $season = new \Season($this->mockDb);

        $this->assertInstanceOf(\Season::class, $season);
    }


    public function testHasShowDraftLinkProperty(): void
    {
        $season = new \Season($this->mockDb);

        $this->assertTrue(property_exists($season, 'showDraftLink'));
    }

    // ============================================
    // RS-TO-PLAYOFFS GAP SKIP TESTS
    // ============================================

    /**
     * Create a Season mock configured for gap-skip testing
     *
     * Uses the mock Season (via class_alias) with properties set directly
     * instead of database queries.
     *
     * @param int $simLengthInDays Sim length in days
     * @param string|null $lastRSGameDate Last regular season game date (YYYY-MM-DD), or null
     * @return \Season Configured mock Season
     */
    private function createGapSkipSeason(
        int $simLengthInDays,
        ?string $lastRSGameDate,
    ): \Season {
        $season = new \Season($this->mockDb);
        $season->endingYear = 2025;
        $season->playoffsStartDate = new \DateTime('2025-06-01');
        $season->simLengthInDays = $simLengthInDays;
        $season->lastRegularSeasonGameDate = $lastRSGameDate;
        return $season;
    }

    public function testGapSkipWhenSimCrossesGap(): void
    {
        $season = $this->createGapSkipSeason(7, '2025-05-15');

        $result = $season->getProjectedNextSimEndDate('2025-05-10');

        // May 10 + 7 = May 17, crosses gap (May 16 to June 1 = 16 days), May 17 + 16 = June 2
        $this->assertSame('2025-06-02', $result->format('Y-m-d'));
    }

    public function testGapSkipWhenLastRsSimDone(): void
    {
        $season = $this->createGapSkipSeason(7, '2025-05-15');

        $result = $season->getProjectedNextSimEndDate('2025-05-15');

        // May 15 + 7 = May 22, crosses gap (May 16 to June 1 = 16 days), May 22 + 16 = June 7
        $this->assertSame('2025-06-07', $result->format('Y-m-d'));
    }

    public function testNoGapSkipWhenSimEndsBeforeGap(): void
    {
        $season = $this->createGapSkipSeason(3, '2025-05-15');

        $result = $season->getProjectedNextSimEndDate('2025-05-08');

        // May 8 + 3 = May 11, does not reach gap (May 16), no adjustment
        $this->assertSame('2025-05-11', $result->format('Y-m-d'));
    }

    public function testNoGapSkipWhenAlreadyInPlayoffs(): void
    {
        $season = $this->createGapSkipSeason(7, '2025-05-15');

        $result = $season->getProjectedNextSimEndDate('2025-06-01');

        // June 1 + 7 = June 8, lastSimEnd (June 1) is NOT < gapStart (May 16), no adjustment
        $this->assertSame('2025-06-08', $result->format('Y-m-d'));
    }

    public function testNoGapSkipWhenNoScheduleData(): void
    {
        $season = $this->createGapSkipSeason(7, null);

        $result = $season->getProjectedNextSimEndDate('2025-05-10');

        // No lastRSGameDate, no adjustment: May 10 + 7 = May 17
        $this->assertSame('2025-05-17', $result->format('Y-m-d'));
    }

    public function testNoGapSkipWhenRsEndsMay31(): void
    {
        $season = $this->createGapSkipSeason(7, '2025-05-31');

        $result = $season->getProjectedNextSimEndDate('2025-05-28');

        // gapStart = June 1, playoffsStart = June 1, gapStart < playoffsStart is false, no adjustment
        // May 28 + 7 = June 4
        $this->assertSame('2025-06-04', $result->format('Y-m-d'));
    }
}
