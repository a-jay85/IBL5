<?php

declare(strict_types=1);

namespace Tests\Trading;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
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
            public function query(string $query, int $result_mode = MYSQLI_STORE_RESULT)
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
                /** @phpstan-ignore-next-line */
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

    // --- Merged from CashTransactionHandlerModernTest ---

    private function createLegacyMockCashHandler(): \Trading\CashTransactionHandler
    {
        if (!isset($this->legacyMockDb)) {
            $this->legacyMockDb = new \MockDatabase();
        }
        return new \Trading\CashTransactionHandler($this->legacyMockDb);
    }

    /** @var \MockDatabase|null */
    private $legacyMockDb = null;

    /**
     * @group pid-generation
     */
    public function testGeneratesUniquePidWhenRequestedPidIsAvailable(): void
    {
        // Arrange
        $requestedPid = 99999;
        $this->legacyMockDb = new \MockDatabase();
        // Mock repository to return null (PID doesn't exist)
        $this->legacyMockDb->setMockData([]);
        $cashHandler = new \Trading\CashTransactionHandler($this->legacyMockDb);

        // Act
        $result = $cashHandler->generateUniquePid($requestedPid);

        // Assert
        $this->assertEquals($requestedPid, $result);
    }

    /**
     * @group contract-calculations
     */
    #[DataProvider('contractYearScenarios')]
    public function testCalculatesContractTotalYearsCorrectly(mixed $cashDistribution, int $expectedYears, string $description): void
    {
        // Act
        $result = $this->createLegacyMockCashHandler()->calculateContractTotalYears($cashDistribution);

        // Assert
        $this->assertEquals($expectedYears, $result, $description);
    }

    /**
     * @group cash-detection
     */
    public function testDetectsCashPresenceInTradeAccurately(): void
    {
        // Test cases for cash detection
        $testCases = [
            'with_cash_first_year' => [[1 => 100, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0], true],
            'with_cash_last_year' => [[1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 500], true],
            'with_no_cash' => [[1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0], false],
            'with_empty_array' => [[], false],
            'with_multiple_years' => [[1 => 100, 2 => 200, 3 => 0, 4 => 0, 5 => 0, 6 => 0], true],
        ];

        $cashHandler = $this->createLegacyMockCashHandler();
        foreach ($testCases as $scenario => $data) {
            list($cashAmounts, $expected) = $data;

            // Act
            $result = $cashHandler->hasCashInTrade($cashAmounts);

            // Assert
            $this->assertEquals($expected, $result, "Failed for scenario: $scenario");
        }
    }

    /**
     * @group cash-transactions
     */
    public function testCreatesCashTransactionWithProperStoryText(): void
    {
        // Arrange
        $itemId = 12345;
        $fromTeamName = 'Los Angeles Lakers';
        $toTeamName = 'Boston Celtics';
        $cashYear = [1 => 100, 2 => 200, 3 => 0, 4 => 0, 5 => 0, 6 => 0];
        $seasonEndingYear = 2007;

        // Act
        $result = $this->createLegacyMockCashHandler()->createCashTransaction($itemId, $fromTeamName, $toTeamName, $cashYear, $seasonEndingYear);

        // Assert
        $this->assertTrue($result['success'], 'Cash transaction should succeed');
        $this->assertStringContainsString($fromTeamName, $result['tradeLine']);
        $this->assertStringContainsString($toTeamName, $result['tradeLine']);
        $this->assertStringContainsString('100 in cash', $result['tradeLine']);
        $this->assertStringContainsString('2006-2007', $result['tradeLine']);
        $this->assertStringContainsString('200 in cash', $result['tradeLine']);
        $this->assertStringContainsString('2007-2008', $result['tradeLine']);
    }

    /**
     * @group database-operations
     */
    public function testInsertsCashTradeDataSuccessfully(): void
    {
        // Arrange
        $tradeOfferId = 999;
        $offeringTeamName = 'Miami Heat';
        $listeningTeamName = 'Golden State Warriors';
        $cashAmounts = [1 => 100, 2 => 200, 3 => 300, 4 => 0, 5 => 0, 6 => 0];

        $this->legacyMockDb = new \MockDatabase();
        $this->legacyMockDb->setReturnTrue(true); // INSERT should return true
        $cashHandler = new \Trading\CashTransactionHandler($this->legacyMockDb);

        // Act
        $result = $cashHandler->insertCashTradeData(
            $tradeOfferId,
            $offeringTeamName,
            $listeningTeamName,
            $cashAmounts
        );

        // Assert
        $this->assertTrue($result, 'Cash trade data insertion should succeed');
    }

    /**
     * @group database-operations
     */
    public function testHandlesPartialCashDataByFillingMissingYearsWithZeros(): void
    {
        // Arrange
        $tradeOfferId = 999;
        $offeringTeamName = 'Chicago Bulls';
        $listeningTeamName = 'New York Knicks';
        $partialCashAmounts = [1 => 100, 3 => 300, 5 => 500]; // Missing years 2, 4, 6

        $this->legacyMockDb = new \MockDatabase();
        $this->legacyMockDb->setReturnTrue(true);
        $cashHandler = new \Trading\CashTransactionHandler($this->legacyMockDb);

        // Act
        $result = $cashHandler->insertCashTradeData(
            $tradeOfferId,
            $offeringTeamName,
            $listeningTeamName,
            $partialCashAmounts
        );

        // Assert
        $this->assertTrue($result, 'Partial cash data should be handled correctly');
    }

    /**
     * @group edge-cases
     */
    public function testHandlesEdgeCasesGracefully(): void
    {
        // Test various edge cases
        $edgeCases = [
            'zero_cash_all_years' => [
                [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0],
                1 // Should default to 1 year
            ],
            'cash_only_in_middle_year' => [
                [1 => 0, 2 => 0, 3 => 500, 4 => 0, 5 => 0, 6 => 0],
                3 // Should be 3 years based on last non-zero
            ],
            'maximum_contract_length' => [
                [1 => 100, 2 => 200, 3 => 300, 4 => 400, 5 => 500, 6 => 600],
                6 // Maximum 6 years
            ]
        ];

        $cashHandler = $this->createLegacyMockCashHandler();
        foreach ($edgeCases as $case => $data) {
            list($cashYear, $expectedYears) = $data;

            // Act
            $result = $cashHandler->calculateContractTotalYears($cashYear);

            // Assert
            $this->assertEquals($expectedYears, $result, "Failed for edge case: $case");
        }
    }

    /**
     * Data provider for contract year calculation scenarios
     */
    public static function contractYearScenarios(): array
    {
        return [
            'front_loaded_contract' => [
                [1 => 1000, 2 => 500, 3 => 250, 4 => 0, 5 => 0, 6 => 0],
                3,
                'Front-loaded 3-year contract should return 3 years'
            ],
            'back_loaded_contract' => [
                [1 => 0, 2 => 0, 3 => 0, 4 => 1000, 5 => 2000, 6 => 3000],
                6,
                'Back-loaded contract should return 6 years'
            ],
            'uniform_contract' => [
                [1 => 500, 2 => 500, 3 => 500, 4 => 500, 5 => 0, 6 => 0],
                4,
                'Uniform 4-year contract should return 4 years'
            ],
            'single_year_contract' => [
                [1 => 2000, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0],
                1,
                'Single year contract should return 1 year'
            ],
            'irregular_pattern' => [
                [1 => 100, 2 => 0, 3 => 300, 4 => 0, 5 => 500, 6 => 0],
                5,
                'Irregular pattern should return years based on last non-zero year'
            ],
            'maximum_length' => [
                [1 => 1000, 2 => 1100, 3 => 1200, 4 => 1300, 5 => 1400, 6 => 1500],
                6,
                'Maximum 6-year contract should return 6 years'
            ]
        ];
    }

    /**
     * @group integration
     */
    public function testPerformsCompleteCashTransactionWorkflow(): void
    {
        // This is an integration-style test that combines multiple operations

        // Arrange
        $itemId = 54321;
        $fromTeamName = 'San Antonio Spurs';
        $toTeamName = 'Portland Trail Blazers';
        $cashYear = [1 => 250, 2 => 275, 3 => 300, 4 => 0, 5 => 0, 6 => 0];
        $seasonEndingYear = 2007;

        $cashHandler = $this->createLegacyMockCashHandler();

        // Act - Test the complete workflow
        $contractYears = $cashHandler->calculateContractTotalYears($cashYear);
        $hasCash = $cashHandler->hasCashInTrade($cashYear);
        $transactionResult = $cashHandler->createCashTransaction($itemId, $fromTeamName, $toTeamName, $cashYear, $seasonEndingYear);

        // Assert - Verify the complete workflow
        $this->assertEquals(3, $contractYears, 'Should calculate 3 contract years');
        $this->assertTrue($hasCash, 'Should detect cash in trade');
        $this->assertTrue($transactionResult['success'], 'Transaction should succeed');
        $this->assertStringContainsString('250 in cash', $transactionResult['tradeLine']);
        $this->assertStringContainsString('275 in cash', $transactionResult['tradeLine']);
        $this->assertStringContainsString('300 in cash', $transactionResult['tradeLine']);
        $this->assertStringContainsString($fromTeamName, $transactionResult['tradeLine']);
        $this->assertStringContainsString($toTeamName, $transactionResult['tradeLine']);
    }

}
