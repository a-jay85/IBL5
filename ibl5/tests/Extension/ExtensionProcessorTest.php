<?php

declare(strict_types=1);

namespace Tests\Extension;

use PHPUnit\Framework\TestCase;
use Extension\ExtensionProcessor;
use Extension\Contracts\ExtensionProcessorInterface;

/**
 * ExtensionProcessorTest - Tests for ExtensionProcessor
 */
class ExtensionProcessorTest extends TestCase
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
        $processor = new ExtensionProcessor($this->mockDb);

        $this->assertInstanceOf(ExtensionProcessor::class, $processor);
    }

    public function testImplementsInterface(): void
    {
        $processor = new ExtensionProcessor($this->mockDb);

        $this->assertInstanceOf(ExtensionProcessorInterface::class, $processor);
    }

    // ============================================
    // METHOD EXISTENCE TESTS
    // ============================================

    public function testHasProcessExtensionMethod(): void
    {
        $processor = new ExtensionProcessor($this->mockDb);

        $this->assertTrue(method_exists($processor, 'processExtension'));
    }

    // ============================================
    // ERROR HANDLING TESTS
    // ============================================

    public function testProcessExtensionReturnsErrorWhenPlayerNotFound(): void
    {
        $processor = new ExtensionProcessor($this->mockDb);
        $extensionData = [
            'offer' => [1 => 5000000],
            'playerID' => 99999,
        ];

        $result = $processor->processExtension($extensionData);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Player not found', $result['error']);
    }
}
