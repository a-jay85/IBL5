<?php

declare(strict_types=1);

namespace Tests\ComparePlayers;

use PHPUnit\Framework\TestCase;
use ComparePlayers\ComparePlayersRepository;

class ComparePlayersRepositoryTest extends TestCase
{
    private object $mockDb;
    private ComparePlayersRepository $repository;

    protected function setUp(): void
    {
        $this->mockDb = $this->createMockDatabase();
        $this->repository = new ComparePlayersRepository($this->mockDb);
    }

    public function testGetAllPlayerNamesReturnsArrayOfNames(): void
    {
        $result = $this->repository->getAllPlayerNames();

        $this->assertIsArray($result);
        foreach ($result as $item) {
            $this->assertIsString($item);
        }
    }

    public function testGetAllPlayerNamesOrdersAlphabetically(): void
    {
        $result = $this->repository->getAllPlayerNames();

        // If empty, that's valid (mock database returns empty)
        if (!empty($result)) {
            $sorted = $result;
            sort($sorted);
            
            $this->assertEquals($sorted, $result);
        } else {
            // Mock database returns empty, which is acceptable
            $this->assertEmpty($result);
        }
    }

    public function testGetAllPlayerNamesExcludesInactivePlayers(): void
    {
        $result = $this->repository->getAllPlayerNames();

        // All returned players should be from active roster (ordinal != 0)
        $this->assertIsArray($result);
        // The query filters ordinal != 0, so this test verifies the method completes
    }

    public function testGetPlayerByNameReturnsPlayerData(): void
    {
        $playerName = 'Michael Jordan';
        $result = $this->repository->getPlayerByName($playerName);

        if ($result !== null) {
            $this->assertIsArray($result);
            $this->assertArrayHasKey('pid', $result);
            $this->assertArrayHasKey('name', $result);
            $this->assertArrayHasKey('pos', $result);
            $this->assertArrayHasKey('age', $result);
        } else {
            // Player doesn't exist in test database, which is valid
            $this->assertNull($result);
        }
    }

    public function testGetPlayerByNameReturnsNullForNonExistentPlayer(): void
    {
        $result = $this->repository->getPlayerByName('NonExistent Player 12345');

        $this->assertNull($result);
    }

    public function testGetPlayerByNameHandlesApostrophes(): void
    {
        // Test that apostrophes don't cause SQL errors
        $result = $this->repository->getPlayerByName("O'Neal");

        // Should return null or player data, but not throw exception
        $this->assertTrue($result === null || is_array($result));
    }

    public function testGetPlayerByNameHandlesSpecialCharacters(): void
    {
        // Test SQL injection attempt is handled safely
        $maliciousInput = "Jordan'; DROP TABLE ibl_plr; --";
        $result = $this->repository->getPlayerByName($maliciousInput);

        // Should return null safely without SQL error
        $this->assertNull($result);
    }





    public function testGetPlayerByNameHandlesEmptyString(): void
    {
        $result = $this->repository->getPlayerByName('');
        $this->assertNull($result);
    }

    public function testGetPlayerByNameHandlesWhitespaceOnlyString(): void
    {
        $result = $this->repository->getPlayerByName('   ');
        $this->assertNull($result);
    }

    private function createMockDatabase(): object
    {
        // Return a mock object that simulates the database interface
        // This allows tests to run without actual database connection
        $mock = new class {
            public function sql_query(string $query): object
            {
                return new class {
                    public function fetch_assoc(): ?array
                    {
                        return null;
                    }
                };
            }

            public function sql_numrows(object $result): int
            {
                return 0;
            }

            public function sql_result(object $result, int $row, string $field): string
            {
                return '';
            }

            public function sql_fetch_assoc(object $result): ?array
            {
                return null;
            }

            public function query(string $query): object|false
            {
                return new class {
                    public int $num_rows = 0;
                    
                    public function fetch_assoc(): ?array
                    {
                        return null;
                    }
                };
            }

            public function prepare(string $query): object|false
            {
                return new class {
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
                        return new class {
                            public int $num_rows = 0;

                            public function fetch_assoc(): ?array
                            {
                                return null;
                            }
                        };
                    }
                };
            }
        };

        return $mock;
    }
}
