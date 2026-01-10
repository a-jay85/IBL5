<?php

declare(strict_types=1);

namespace Tests\SeriesRecords;

use PHPUnit\Framework\TestCase;
use SeriesRecords\SeriesRecordsController;
use SeriesRecords\Contracts\SeriesRecordsControllerInterface;

/**
 * SeriesRecordsControllerTest - Tests for SeriesRecordsController
 */
class SeriesRecordsControllerTest extends TestCase
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
                            public function __construct(array $rows)
                            {
                                $this->rows = $rows;
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
                return $mockResult;
            }
        };
    }

    // ============================================
    // INSTANTIATION TESTS
    // ============================================

    public function testCanBeInstantiated(): void
    {
        $controller = new SeriesRecordsController($this->mockDb);

        $this->assertInstanceOf(SeriesRecordsController::class, $controller);
    }

    public function testImplementsInterface(): void
    {
        $controller = new SeriesRecordsController($this->mockDb);

        $this->assertInstanceOf(SeriesRecordsControllerInterface::class, $controller);
    }

    // ============================================
    // METHOD EXISTENCE TESTS
    // ============================================

    public function testHasDisplaySeriesRecordsMethod(): void
    {
        $controller = new SeriesRecordsController($this->mockDb);

        $this->assertTrue(method_exists($controller, 'displaySeriesRecords'));
    }

    public function testHasDisplayLoginPromptMethod(): void
    {
        $controller = new SeriesRecordsController($this->mockDb);

        $this->assertTrue(method_exists($controller, 'displayLoginPrompt'));
    }
}
