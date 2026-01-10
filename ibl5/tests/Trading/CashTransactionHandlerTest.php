<?php

declare(strict_types=1);

namespace Tests\Trading;

use PHPUnit\Framework\TestCase;
use Trading\CashTransactionHandler;
use Trading\Contracts\CashTransactionHandlerInterface;

/**
 * CashTransactionHandlerTest - Tests for CashTransactionHandler
 */
class CashTransactionHandlerTest extends TestCase
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
            public bool $playerExists = false;

            public function __construct()
            {
                // Don't call parent constructor
            }

            #[\ReturnTypeWillChange]
            public function prepare(string $query)
            {
                $mockData = $this->mockData;
                $playerExists = $this->playerExists;
                return new class($mockData, $playerExists) {
                    private array $mockData;
                    private bool $playerExists;
                    public function __construct(array $mockData, bool $playerExists)
                    {
                        $this->mockData = $mockData;
                        $this->playerExists = $playerExists;
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
                        $exists = $this->playerExists;
                        return new class($data, $exists) {
                            private array $rows;
                            private int $index = 0;
                            private bool $playerExists;
                            public int $num_rows;
                            public function __construct(array $rows, bool $playerExists)
                            {
                                $this->rows = $rows;
                                $this->playerExists = $playerExists;
                                $this->num_rows = $playerExists ? 1 : 0;
                            }
                            public function fetch_assoc(): ?array
                            {
                                return $this->rows[$this->index++] ?? null;
                            }
                            public function fetch_object(): ?object
                            {
                                $row = $this->rows[$this->index++] ?? null;
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
                    public int $num_rows = 0;
                    public function __construct(array $rows)
                    {
                        $this->rows = $rows;
                        $this->num_rows = count($rows);
                    }
                    public function fetch_assoc(): ?array
                    {
                        return $this->rows[$this->index++] ?? null;
                    }
                    public function fetch_object(): ?object
                    {
                        $row = $this->rows[$this->index++] ?? null;
                        return $row ? (object) $row : null;
                    }
                };
                return $mockResult;
            }
        };
    }

    // ============================================
    // INSTANTIATION TESTS
    // ============================================

    public function testCanBeInstantiated(): void
    {
        $handler = new CashTransactionHandler($this->mockDb);

        $this->assertInstanceOf(CashTransactionHandler::class, $handler);
    }

    public function testImplementsInterface(): void
    {
        $handler = new CashTransactionHandler($this->mockDb);

        $this->assertInstanceOf(CashTransactionHandlerInterface::class, $handler);
    }

    // ============================================
    // CALCULATE CONTRACT TOTAL YEARS TESTS
    // ============================================

    public function testCalculateContractTotalYearsReturnsOneForOneYearContract(): void
    {
        $handler = new CashTransactionHandler($this->mockDb);
        $cashYear = [1 => 5000000];

        $result = $handler->calculateContractTotalYears($cashYear);

        $this->assertSame(1, $result);
    }

    public function testCalculateContractTotalYearsReturnsTwoForTwoYearContract(): void
    {
        $handler = new CashTransactionHandler($this->mockDb);
        $cashYear = [1 => 5000000, 2 => 5500000];

        $result = $handler->calculateContractTotalYears($cashYear);

        $this->assertSame(2, $result);
    }

    public function testCalculateContractTotalYearsReturnsThreeForThreeYearContract(): void
    {
        $handler = new CashTransactionHandler($this->mockDb);
        $cashYear = [1 => 5000000, 2 => 5500000, 3 => 6000000];

        $result = $handler->calculateContractTotalYears($cashYear);

        $this->assertSame(3, $result);
    }

    public function testCalculateContractTotalYearsReturnsFourForFourYearContract(): void
    {
        $handler = new CashTransactionHandler($this->mockDb);
        $cashYear = [1 => 5000000, 2 => 5500000, 3 => 6000000, 4 => 6500000];

        $result = $handler->calculateContractTotalYears($cashYear);

        $this->assertSame(4, $result);
    }

    public function testCalculateContractTotalYearsReturnsFiveForFiveYearContract(): void
    {
        $handler = new CashTransactionHandler($this->mockDb);
        $cashYear = [1 => 5000000, 2 => 5500000, 3 => 6000000, 4 => 6500000, 5 => 7000000];

        $result = $handler->calculateContractTotalYears($cashYear);

        $this->assertSame(5, $result);
    }

    public function testCalculateContractTotalYearsReturnsSixForSixYearContract(): void
    {
        $handler = new CashTransactionHandler($this->mockDb);
        $cashYear = [1 => 5000000, 2 => 5500000, 3 => 6000000, 4 => 6500000, 5 => 7000000, 6 => 7500000];

        $result = $handler->calculateContractTotalYears($cashYear);

        $this->assertSame(6, $result);
    }

    public function testCalculateContractTotalYearsHandlesEmptyArray(): void
    {
        $handler = new CashTransactionHandler($this->mockDb);
        $cashYear = [];

        $result = $handler->calculateContractTotalYears($cashYear);

        $this->assertSame(1, $result);
    }

    // ============================================
    // METHOD EXISTENCE TESTS
    // ============================================

    public function testHasGenerateUniquePidMethod(): void
    {
        $handler = new CashTransactionHandler($this->mockDb);

        $this->assertTrue(method_exists($handler, 'generateUniquePid'));
    }

    public function testHasCreateCashTransactionMethod(): void
    {
        $handler = new CashTransactionHandler($this->mockDb);

        $this->assertTrue(method_exists($handler, 'createCashTransaction'));
    }
}
