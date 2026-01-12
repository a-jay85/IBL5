<?php

namespace Tests\Integration\Mocks;

/**
 * Mock database result class
 * Simulates database query results for testing
 */
class MockDatabaseResult
{
    private $data;
    private $position = 0;
    
    public function __construct($data = [])
    {
        $this->data = is_array($data) ? $data : [];
    }
    
    public function getResult($row, $field)
    {
        // Handle numeric field access
        if (is_numeric($field)) {
            $values = array_values($this->data[$row] ?? []);
            return $values[$field] ?? null;
        }
        // Handle associative field access
        return isset($this->data[$row][$field]) ? $this->data[$row][$field] : null;
    }
    
    public function fetchRow()
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
    
    public function fetchAssoc()
    {
        if ($this->position < count($this->data)) {
            return $this->data[$this->position++];
        }
        return false;
    }
    
    public function numRows()
    {
        return count($this->data);
    }
}
