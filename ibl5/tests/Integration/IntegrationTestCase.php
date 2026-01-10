<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;

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
    protected \MockDatabase $mockDb;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockDb = new \MockDatabase();
        $this->injectGlobalMockDb();
    }

    protected function tearDown(): void
    {
        $this->mockDb = null;
        unset($GLOBALS['mysqli_db']);
        parent::tearDown();
    }

    /**
     * Inject mock database into global $mysqli_db for classes that use it
     */
    protected function injectGlobalMockDb(): void
    {
        $mockDb = $this->mockDb;
        
        $GLOBALS['mysqli_db'] = new class($mockDb) {
            private \MockDatabase $mockDb;
            public int $connect_errno = 0;
            public ?string $connect_error = null;

            public function __construct(\MockDatabase $mockDb)
            {
                $this->mockDb = $mockDb;
            }

            public function prepare(string $query): \MockPreparedStatement
            {
                return new \MockPreparedStatement($this->mockDb, $query);
            }

            public function query(string $query): mixed
            {
                return $this->mockDb->sql_query($query);
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

    /**
     * Setup common player data for tests
     */
    protected function setupMockPlayer(array $overrides = []): array
    {
        $defaults = [
            'pid' => 1,
            'name' => 'Test Player',
            'firstname' => 'Test',
            'lastname' => 'Player',
            'teamname' => 'Test Team',
            'tid' => 1,
            'position' => 'G',
            'age' => 25,
            'ordinal' => 1,
            'cy' => 1,
            'ty' => 4,
            'c1' => 500,
            'c2' => 550,
            'c3' => 600,
            'c4' => 650,
            'c5' => 0,
            'c6' => 0,
            'exp' => 3,
            'bird_years' => 2,
            'retired' => 0,
            'injured' => 0,
        ];

        return array_merge($defaults, $overrides);
    }

    /**
     * Setup common team data for tests
     */
    protected function setupMockTeam(array $overrides = []): array
    {
        $defaults = [
            'teamid' => 1,
            'team_name' => 'Test Team',
            'Salary_Total' => 5000,
            'Salary_Cap' => 8250,
            'Tax_Line' => 10000,
            'Apron' => 11500,
            'Hard_Cap' => 12000,
            'color1' => 'FF0000',
            'color2' => '000000',
        ];

        return array_merge($defaults, $overrides);
    }

    /**
     * Setup common season data for tests
     */
    protected function setupMockSeason(array $overrides = []): array
    {
        $defaults = [
            'Phase' => 'Regular Season',
            'Beginning_Year' => 2024,
            'Ending_Year' => 2025,
            'Allow_Trades' => 'Yes',
            'Allow_Waivers' => 'Yes',
        ];

        return array_merge($defaults, $overrides);
    }
}
