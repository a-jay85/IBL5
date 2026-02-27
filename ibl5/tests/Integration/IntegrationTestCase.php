<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use Tests\Integration\Mocks\MockDatabase;

/**
 * Base class for integration tests
 *
 * Provides standardized MockDatabase setup, global $mysqli_db injection,
 * and query tracking helpers for testing complete workflows.
 *
 * Usage:
 *   class MyWorkflowTest extends IntegrationTestCase
 *   {
 *       public function testCompleteWorkflow(): void
 *       {
 *           $this->mockDb->setQueryResult('SELECT...', [['id' => 1]]);
 *           // ... run workflow
 *           $this->assertQueryExecuted('UPDATE...');
 *       }
 *   }
 */
abstract class IntegrationTestCase extends TestCase
{
    protected ?MockDatabase $mockDb = null;
    private string|false $previousErrorLog = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockDb = new \MockDatabase();
        $this->injectGlobalMockDb();
    }

    protected function tearDown(): void
    {
        if ($this->previousErrorLog !== false) {
            ini_set('error_log', $this->previousErrorLog);
            $this->previousErrorLog = false;
        }
        $this->mockDb = null;
        unset($GLOBALS['mysqli_db']);
        parent::tearDown();
    }

    /**
     * Suppress error_log() output for tests that intentionally trigger database errors.
     * Automatically restored in tearDown().
     */
    protected function suppressErrorLog(): void
    {
        $this->previousErrorLog = ini_get('error_log') ?: '';
        ini_set('error_log', '/dev/null');
    }

    /**
     * Inject mock database into global $mysqli_db for classes that use it
     */
    protected function injectGlobalMockDb(): void
    {
        $mockDb = $this->mockDb;
        
        $GLOBALS['mysqli_db'] = new class($mockDb) extends \mysqli {
            private \MockDatabase $mockDb;
            public int $connect_errno = 0;
            public ?string $connect_error = null;

            public function __construct(\MockDatabase $mockDb)
            {
                // Don't call parent::__construct() to avoid real DB connection
                $this->mockDb = $mockDb;
            }

            #[\ReturnTypeWillChange]
            public function prepare(string $query): \MockPreparedStatement|false
            {
                return new \MockPreparedStatement($this->mockDb, $query);
            }

            #[\ReturnTypeWillChange]
            public function query(string $query, int $resultMode = MYSQLI_STORE_RESULT): \mysqli_result|bool
            {
                $result = $this->mockDb->sql_query($query);
                if ($result instanceof \MockDatabaseResult) {
                    return false;
                }
                return (bool) $result;
            }

            public function real_escape_string(string $value): string
            {
                return addslashes($value);
            }
        };
    }

    /**
     * Get all queries executed during the test
     */
    protected function getExecutedQueries(): array
    {
        return $this->mockDb->getExecutedQueries();
    }

    /**
     * Assert that a query containing the given string was executed
     */
    protected function assertQueryExecuted(string $querySubstring): void
    {
        $queries = $this->getExecutedQueries();
        $found = false;
        
        foreach ($queries as $query) {
            if (stripos($query, $querySubstring) !== false) {
                $found = true;
                break;
            }
        }
        
        $this->assertTrue(
            $found,
            "Expected query containing '$querySubstring' was not executed.\nExecuted queries:\n" .
            implode("\n", $queries)
        );
    }

    /**
     * Assert that a query containing the given string was NOT executed
     */
    protected function assertQueryNotExecuted(string $querySubstring): void
    {
        $queries = $this->getExecutedQueries();
        
        foreach ($queries as $query) {
            if (stripos($query, $querySubstring) !== false) {
                $this->fail(
                    "Query containing '$querySubstring' was executed but should not have been.\n" .
                    "Matched query: $query"
                );
            }
        }
        
        $this->assertTrue(true); // No matching query found, assertion passes
    }

    /**
     * Count how many queries matched a pattern
     */
    protected function countQueriesMatching(string $querySubstring): int
    {
        $count = 0;
        foreach ($this->getExecutedQueries() as $query) {
            if (stripos($query, $querySubstring) !== false) {
                $count++;
            }
        }
        return $count;
    }

}
