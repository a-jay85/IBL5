<?php

namespace Tests\Integration\Mocks;

/**
 * Mock prepared statement for testing mysqli-style prepared statements
 * Duck-types mysqli_stmt without extending it to avoid type constraints
 */
class MockPreparedStatement
{
    private ?MockDatabase $mockDb;
    private string $query;
    private array $boundParams = [];
    private array $paramTypes = [];
    public string|int $affected_rows = 0;
    public string $error = '';
    public int $errno = 0;

    /** @var MockDatabaseResult|bool|null Stored result from execute() for get_result() */
    private MockDatabaseResult|bool|null $lastResult = null;

    /**
     * @param MockDatabase|null $mockDb Mock database instance to use.
     *                                  If null (or omitted), a new MockDatabase
     *                                  instance will be created for this statement,
     *                                  rather than using any shared instance.
     * @param string $query             The SQL query string for this mock statement.
     */
    public function __construct(?MockDatabase $mockDb, string $query = '')
    {
        $this->mockDb = $mockDb ?? new MockDatabase();
        $this->query = $query;
    }

    /**
     * Bind parameters to the prepared statement
     * @param string $types Parameter types (s=string, i=integer, d=double, b=blob)
     * @param mixed ...$params Parameters to bind
     */
    public function bind_param(string $types, &...$params): bool
    {
        $this->paramTypes = str_split($types);
        foreach ($params as $index => $param) {
            $this->boundParams[$index] = $param;
        }
        return true;
    }

    /**
     * Execute the prepared statement
     * Stores the result for later retrieval via get_result()
     */
    public function execute(?array $params = null): bool
    {
        $query = $this->replacePlaceholders($this->query);

        // Execute the query using the mock database and store result
        $result = $this->mockDb->sql_query($query);
        $this->lastResult = $result;

        // Set affected_rows if query was UPDATE/INSERT/DELETE
        if (stripos($query, 'UPDATE') === 0 ||
            stripos($query, 'INSERT') === 0 ||
            stripos($query, 'DELETE') === 0) {
            $this->affected_rows = $this->mockDb->sql_affectedrows();
        }

        return $result !== false;
    }

    /**
     * Get the result of the prepared statement
     * Returns the stored result from the last execute() call
     */
    public function get_result(): object|false
    {
        // Return stored result from execute() instead of calling sql_query() again
        if ($this->lastResult instanceof MockDatabaseResult) {
            return new MockMysqliResult($this->lastResult);
        }

        return false;
    }
    
    public function close(): bool
    {
        // Mock close - just return true
        return true;
    }
    
    /**
     * Replace placeholders with bound values in the query
     * Extracted to avoid code duplication between execute() and get_result()
     */
    private function replacePlaceholders(string $query): string
    {
        foreach ($this->boundParams as $param) {
            // Simple placeholder replacement (?) with the actual value
            // Handle null values to avoid PHP 8.3 deprecation warning
            if ($param === null) {
                $value = 'NULL';
            } else {
                $value = is_string($param) ? "'" . addslashes($param) . "'" : $param;
            }
            $query = preg_replace('/\?/', (string)$value, $query, 1);
        }
        return $query;
    }
}
