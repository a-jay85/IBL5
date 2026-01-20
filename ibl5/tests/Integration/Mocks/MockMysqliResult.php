<?php

namespace Tests\Integration\Mocks;

/**
 * Mock mysqli_result class for testing
 * Cannot extend mysqli_result directly due to readonly properties
 */
class MockMysqliResult
{
    private $mockResult;
    public int $current_field = 0;
    public int $field_count = 0;
    public ?array $lengths = null;
    public int|string $num_rows = 0;
    public int $type = 0;
    
    public function __construct(MockDatabaseResult $mockResult)
    {
        $this->mockResult = $mockResult;
        $this->num_rows = $mockResult->numRows();
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
}
