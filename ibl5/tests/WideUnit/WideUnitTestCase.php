<?php

declare(strict_types=1);

namespace Tests\WideUnit;

use PHPUnit\Framework\TestCase;
use Tests\WideUnit\Mocks\MockDatabase;

/**
 * Base class for wide-unit tests (multi-class workflows using MockDatabase).
 *
 * Provides standardized MockDatabase setup, global $mysqli_db injection,
 * and query tracking helpers for testing complete workflows.
 * For real-database integration tests, see tests/DatabaseIntegration/.
 *
 * Usage:
 *   class MyWorkflowTest extends WideUnitTestCase
 *   {
 *       public function testCompleteWorkflow(): void
 *       {
 *           $this->mockDb->setQueryResult('SELECT...', [['id' => 1]]);
 *           // ... run workflow
 *           $this->assertQueryExecuted('UPDATE...');
 *       }
 *   }
 */
abstract class WideUnitTestCase extends TestCase
{
    protected ?MockDatabase $mockDb = null;
    private string|false $previousErrorLog = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockDb = new MockDatabase();
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
     * Inject mock database into global $mysqli_db for classes that use it.
     *
     * MockDatabase is itself a `\mysqli` subclass that routes prepare()/query()/
     * real_escape_string() through the same in-memory mock data, so the global is
     * the same instance as $this->mockDb — queries issued via the global are
     * tracked identically to those issued through an injected repository.
     */
    protected function injectGlobalMockDb(): void
    {
        $GLOBALS['mysqli_db'] = $this->mockDb;
    }

    /**
     * Execute a callable that writes to stdout, capturing and returning the output.
     * Cleans the buffer on exception so it never leaks into subsequent tests.
     */
    protected function captureOutput(callable $fn): string
    {
        ob_start();
        try {
            $fn();
            return (string) ob_get_clean();
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
    }

    /**
     * Get all queries executed during the test
     *
     * @return list<string>
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
                self::fail(
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
