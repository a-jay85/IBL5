<?php

declare(strict_types=1);

namespace Tests\Trading;

use PHPUnit\Framework\TestCase;
use Trading\TradeProcessor;
use Trading\Contracts\TradeProcessorInterface;

/**
 * TradeProcessorTest - Tests for TradeProcessor
 */
class TradeProcessorTest extends TestCase
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
                return new class($mockData) {
                    private array $mockData;
                    public function __construct(array $mockData)
                    {
                        $this->mockData = $mockData;
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
                    }
                    public function close(): void
                    {
                    }
                };
            }

            public function begin_transaction(int $flags = 0, ?string $name = null): bool
            {
                return true;
            }

            public function commit(int $flags = 0, ?string $name = null): bool
            {
                return true;
            }

            public function rollback(int $flags = 0, ?string $name = null): bool
            {
                return true;
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
        $processor = new TradeProcessor($this->mockDb);

        $this->assertInstanceOf(TradeProcessor::class, $processor);
    }

    public function testImplementsInterface(): void
    {
        $processor = new TradeProcessor($this->mockDb);

        $this->assertInstanceOf(TradeProcessorInterface::class, $processor);
    }

    // ============================================
    // METHOD EXISTENCE TESTS
    // ============================================

    public function testHasProcessTradeMethod(): void
    {
        $processor = new TradeProcessor($this->mockDb);

        $this->assertTrue(method_exists($processor, 'processTrade'));
    }

    // ============================================
    // PROCESS TRADE TESTS
    // ============================================

    public function testProcessTradeReturnsErrorWhenNoTradeDataFound(): void
    {
        $processor = new TradeProcessor($this->mockDb);

        $result = $processor->processTrade(99999);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('No trade data found', $result['error']);
    }
}
