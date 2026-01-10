<?php

declare(strict_types=1);

/**
 * TeamTest - Tests for Team class
 */
class TeamTest extends \PHPUnit\Framework\TestCase
{
    private object $mockDb;

    protected function setUp(): void
    {
        $this->mockDb = $this->createMockDatabase();
    }

    private function createMockDatabase(): object
    {
        return new class extends \mysqli {
            public array $mockData = [];

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
                        return new class($data) {
                            private array $data;
                            private int $callCount = 0;
                            public int $num_rows = 1;
                            public function __construct(array $data)
                            {
                                $this->data = $data;
                            }
                            public function fetch_assoc(): ?array
                            {
                                if ($this->callCount++ > 0) {
                                    return null;
                                }
                                return $this->data['team'] ?? [
                                    'teamid' => 1,
                                    'city' => 'Test',
                                    'name' => 'Team',
                                    'color1' => '000000',
                                    'color2' => 'FFFFFF',
                                    'arena' => 'Test Arena',
                                    'capacity' => 20000,
                                    'owner' => 'Test Owner',
                                    'email' => 'test@test.com',
                                    'discordid' => '123',
                                    'hasusedextensionthissim' => 0,
                                    'hasusedextensionthisseason' => 0,
                                    'hasMLE' => 1,
                                    'hasLLE' => 1,
                                    'wins' => 10,
                                    'losses' => 5,
                                ];
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
                        return $this->rows['team'] ?? [];
                    }
                    public function fetch_object(): ?object
                    {
                        $row = $this->fetch_assoc();
                        return $row ? (object) $row : null;
                    }
                };
                return $mockResult;
            }
        };
    }

    // ============================================
    // CONSTANT TESTS
    // ============================================

    public function testBuyoutPercentageMaxConstant(): void
    {
        $this->assertSame(0.40, \Team::BUYOUT_PERCENTAGE_MAX);
    }

    public function testRosterSpotsMaxConstant(): void
    {
        $this->assertSame(15, \Team::ROSTER_SPOTS_MAX);
    }

    // ============================================
    // INSTANTIATION TESTS
    // ============================================

    public function testCanBeInstantiated(): void
    {
        $team = new \Team($this->mockDb);

        $this->assertInstanceOf(\Team::class, $team);
    }

    public function testHasInitializeMethod(): void
    {
        $this->assertTrue(method_exists(\Team::class, 'initialize'));
    }

    // ============================================
    // PROPERTY TESTS
    // ============================================

    public function testHasTeamIDProperty(): void
    {
        $team = new \Team($this->mockDb);

        $this->assertTrue(property_exists($team, 'teamID'));
    }

    public function testHasCityProperty(): void
    {
        $team = new \Team($this->mockDb);

        $this->assertTrue(property_exists($team, 'city'));
    }

    public function testHasNameProperty(): void
    {
        $team = new \Team($this->mockDb);

        $this->assertTrue(property_exists($team, 'name'));
    }

    public function testHasOwnerNameProperty(): void
    {
        $team = new \Team($this->mockDb);

        $this->assertTrue(property_exists($team, 'ownerName'));
    }

    public function testHasOwnerEmailProperty(): void
    {
        $team = new \Team($this->mockDb);

        $this->assertTrue(property_exists($team, 'ownerEmail'));
    }

    public function testHasArenaProperty(): void
    {
        $team = new \Team($this->mockDb);

        $this->assertTrue(property_exists($team, 'arena'));
    }

    public function testHasCapacityProperty(): void
    {
        $team = new \Team($this->mockDb);

        $this->assertTrue(property_exists($team, 'capacity'));
    }

    public function testHasHasMleProperty(): void
    {
        $team = new \Team($this->mockDb);

        $this->assertTrue(property_exists($team, 'hasMLE'));
    }

    public function testHasHasLleProperty(): void
    {
        $team = new \Team($this->mockDb);

        $this->assertTrue(property_exists($team, 'hasLLE'));
    }

    public function testHasNumberOfPlayersProperty(): void
    {
        $team = new \Team($this->mockDb);

        $this->assertTrue(property_exists($team, 'numberOfPlayers'));
    }

    public function testHasNumberOfHealthyPlayersProperty(): void
    {
        $team = new \Team($this->mockDb);

        $this->assertTrue(property_exists($team, 'numberOfHealthyPlayers'));
    }

    public function testHasSeasonRecordProperty(): void
    {
        $team = new \Team($this->mockDb);

        $this->assertTrue(property_exists($team, 'seasonRecord'));
    }
}
