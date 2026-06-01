<?php

declare(strict_types=1);

namespace Tests\WideUnit\Mocks;

/**
 * Mock database result class
 * Simulates database query results for testing
 */
class MockDatabaseResult
{
    /** @var list<array<string, mixed>> */
    private array $data;
    private int $position = 0;

    /**
     * @param list<array<string, mixed>> $data
     */
    public function __construct(array $data = [])
    {
        $this->data = $data;
    }
    
    public function getResult(int $row, int|string|null $field): mixed
    {
        // Handle numeric field access
        if (is_numeric($field)) {
            $values = array_values($this->data[$row] ?? []);
            return $values[$field] ?? null;
        }
        // Handle associative field access
        return isset($this->data[$row][$field]) ? $this->data[$row][$field] : null;
    }
    
    /**
     * @return array<int|string, mixed>|false
     */
    public function fetchRow(): array|false
    {
        if ($this->position < count($this->data)) {
            $row = $this->data[$this->position++];
            // Return both numeric and associative keys for compatibility
            if (is_array($row) && !isset($row[0])) {
                // Only has associative keys, add numeric ones
                $row = array_merge(array_values($row), $row);
            }
            return $row;
        }
        return false;
    }

    /**
     * @return array<string, mixed>|false
     */
    public function fetchAssoc(): array|false
    {
        if ($this->position < count($this->data)) {
            return $this->data[$this->position++];
        }
        return false;
    }

    /**
     * MySQLi-style fetch_assoc method (snake_case alias)
     * Used by VotingResultsService which expects mysqli result interface
     * Returns null instead of false to match mysqli_result::fetch_assoc()
     *
     * @return array<string, mixed>|null|false
     */
    public function fetch_assoc(): array|null|false
    {
        $result = $this->fetchAssoc();
        return $result === false ? null : $result;
    }
    
    public function numRows(): int
    {
        return count($this->data);
    }
}
