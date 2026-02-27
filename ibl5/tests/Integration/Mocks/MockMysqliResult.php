<?php

declare(strict_types=1);

namespace Tests\Integration\Mocks;

/**
 * Mock mysqli_result class for testing
 * Cannot extend mysqli_result directly due to readonly properties
 * Implements Iterator to support foreach loops (like mysqli_result in PHP 8+)
 */
class MockMysqliResult implements \Iterator
{
    private $mockResult;
    private array $data;
    private int $position = 0;
    public int $current_field = 0;
    public int $field_count = 0;
    public ?array $lengths = null;
    public int|string $num_rows = 0;
    public int $type = 0;

    public function __construct(MockDatabaseResult $mockResult)
    {
        $this->mockResult = $mockResult;
        $this->num_rows = $mockResult->numRows();
        // Store data for iteration
        $this->data = [];
        while (($row = $mockResult->fetchAssoc()) !== false) {
            $this->data[] = $row;
        }
        // Reset the mockResult for fetch_assoc() calls
        $this->mockResult = new MockDatabaseResult($this->data);
    }

    public function fetch_assoc(): array|null|false
    {
        return $this->mockResult->fetchAssoc();
    }

    public function fetch_array(int $mode = MYSQLI_BOTH): array|null|false
    {
        return $this->mockResult->fetchAssoc();
    }

    public function free(): void
    {
        // Mock free - do nothing
    }

    // Iterator interface methods
    public function current(): mixed
    {
        return $this->data[$this->position] ?? null;
    }

    public function key(): int
    {
        return $this->position;
    }

    public function next(): void
    {
        $this->position++;
    }

    public function rewind(): void
    {
        $this->position = 0;
    }

    public function valid(): bool
    {
        return isset($this->data[$this->position]);
    }
}
