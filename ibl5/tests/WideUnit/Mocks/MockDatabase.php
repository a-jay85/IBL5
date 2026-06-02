<?php

declare(strict_types=1);

namespace Tests\WideUnit\Mocks;

/**
 * Mock database class for testing
 * Provides a mock implementation of database operations without requiring actual database connections
 * Extends mysqli to satisfy type hints in modern code while supporting legacy sql_* methods
 *
 * This is the single canonical mysqli mock: tests pass a MockDatabase instance
 * directly anywhere a `\mysqli` connection is type-hinted (e.g. BaseMysqliRepository
 * subclasses). The no-argument parent constructor allocates an unconnected mysqli
 * shell — connect_errno is 0 and connect_error is null, so connection guards pass —
 * while every method a SUT actually calls (prepare/query/real_escape_string/the
 * sql_* helpers/transactions) is overridden to route through in-memory mock data.
 */
class MockDatabase extends \mysqli
{
    /**
     * Allocate an unconnected mysqli shell.
     *
     * Calling parent::__construct() with no arguments does NOT open a connection
     * (verified on PHP 8.x): connect_errno stays 0 and connect_error stays null,
     * so BaseMysqliRepository's connection guard accepts a MockDatabase passed
     * directly. The real connection is never used because every consumed method
     * is overridden below.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /** @var list<array<string, mixed>> */
    private array $mockData = [];
    /** @var list<array<string, mixed>> */
    private array $mockTradeInfo = [];
    /** @var list<array<string, mixed>> */
    private array $mockTeamData = [];
    /** @var array<string, mixed> */
    private array $mockPythagoreanData = [];
    /** @var list<list<array<string, mixed>>> */
    private array $votingResultsQueue = [];
    private ?int $numRows = null;
    private bool $returnTrue = true;
    /** @var list<string> */
    private array $executedQueries = [];
    /**
     * Ordered log of SQL queries AND transaction lifecycle markers
     * (BEGIN/COMMIT/ROLLBACK), kept separate from $executedQueries so callers
     * that assert on the SQL-only log are unaffected by transaction recording.
     * @var list<string>
     */
    private array $operationLog = [];
    private int $affectedRows = 0;
    private ?int $failOnNthInsert = null;
    private int $insertCount = 0;

    /**
     * Pattern-based query routing: maps SQL regex patterns to specific result sets.
     * Checked BEFORE all other routing logic in sql_query().
     * @var array<string, list<array<string, mixed>>>
     */
    private array $queryPatterns = [];

    /**
     * Register a query pattern with specific result rows.
     * Patterns are checked in registration order BEFORE all other routing logic.
     *
     * @param string $pattern Regex pattern (without delimiters) matched case-insensitively against the SQL query
     * @param list<array<string, mixed>> $rows The result rows to return when the pattern matches
     */
    public function onQuery(string $pattern, array $rows): void
    {
        $this->queryPatterns[$pattern] = $rows;
    }

    /**
     * Clear all registered query patterns.
     */
    public function clearQueryPatterns(): void
    {
        $this->queryPatterns = [];
    }

    /**
     * mysqli-style query() entry point.
     *
     * Routes through sql_query() for tracking and mock-data resolution. Returns a
     * narrowed bool (covariant with mysqli::query()'s mysqli_result|bool): write
     * statements return their success bool, while SELECT-style queries that would
     * yield a result set return false — matching the historical inline-mock
     * behavior that callers of this mock already relied on (no consumer reads a
     * mysqli_result back from query() on the mock).
     */
    public function query(string $query, int $resultMode = MYSQLI_STORE_RESULT): bool
    {
        // Transaction-introspection/control statements are connection machinery,
        // not SQL the SUT meaningfully issued, so they stay OUT of the recorded
        // query log (mirroring how begin_transaction()/commit()/rollback() record
        // only into operationLog). BaseMysqliRepository::isInTransaction() probes
        // with "SELECT @@in_transaction": returning a non-result bool makes it
        // correctly treat the mock as not-in-transaction, and skipping the record
        // keeps getExecutedQueries() limited to the repository's real statements.
        if (stripos($query, '@@in_transaction') !== false
            || stripos($query, 'ROLLBACK TO SAVEPOINT') === 0) {
            return false;
        }

        $result = $this->sql_query($query);
        return !($result instanceof MockDatabaseResult) && (bool) $result;
    }

    /**
     * Mock close() — no real connection to release.
     */
    public function close(): bool
    {
        // Mock close - nothing to release.
        return true;
    }

    public function sql_query(string $query): bool|object
    {
        // Track all executed queries for verification
        $this->executedQueries[] = $query;
        $this->operationLog[] = $query;

        // Strip backticks for pattern matching (SQL table names may be backtick-quoted)
        $normalized = str_replace('`', '', $query);

        // For queries that expect boolean return (INSERT, UPDATE, DELETE)
        if (stripos($normalized, 'INSERT') === 0 ||
            stripos($normalized, 'UPDATE') === 0 ||
            stripos($normalized, 'DELETE') === 0) {
            // Optional failure injection: make the Nth INSERT fail, simulating a
            // constraint violation mid-import so tests can exercise rollback.
            if (stripos($normalized, 'INSERT') === 0) {
                $this->insertCount++;
                if ($this->failOnNthInsert !== null && $this->insertCount === $this->failOnNthInsert) {
                    return false;
                }
            }
            // Set affected rows for UPDATE/DELETE operations (default to 1 for successful operations)
            if ($this->returnTrue) {
                $this->affectedRows = 1;
            }
            return $this->returnTrue;
        }

        // Check registered query patterns first (highest priority)
        foreach ($this->queryPatterns as $pattern => $rows) {
            if (preg_match('/' . $pattern . '/i', $normalized) === 1) {
                return new MockDatabaseResult($rows);
            }
        }

        // Special handling for PID existence checks (generateUniquePid)
        // Return empty result to indicate PID is available unless explicitly configured
        // Only match the specific "SELECT 1 FROM ibl_plr WHERE pid = X" pattern for existence checks
        if (stripos($normalized, 'SELECT 1 FROM ibl_plr WHERE pid = ') !== false) {
            return new MockDatabaseResult([]);
        }

        // Special handling for trade info queries (support both direct and prepared statement patterns)
        if (stripos($normalized, 'ibl_trade_info') !== false &&
            stripos($normalized, 'tradeofferid') !== false &&
            $this->mockTradeInfo !== []) {
            return new MockDatabaseResult($this->mockTradeInfo);
        }

        // Special handling for team info queries - return mock team data if available
        if (stripos($normalized, 'ibl_team_info') !== false && $this->mockTeamData !== []) {
            // Try to match by teamid if specified in query
            if (preg_match('/teamid\s*=\s*[\'"]?(\d+)[\'"]?/i', $normalized, $matches)) {
                $searchId = (int)$matches[1];
                foreach ($this->mockTeamData as $team) {
                    if (isset($team['teamid']) && (int)$team['teamid'] === $searchId) {
                        return new MockDatabaseResult([$team]);
                    }
                }
            }
            // Return all team data if no specific match
            return new MockDatabaseResult($this->mockTeamData);
        }

        // Special handling for bulk ibl_power queries (getAllStreakData)
        // Only intercept queries WITHOUT a WHERE clause to avoid breaking
        // single-team queries (getTeamStreakData, getTeamPowerData) that set up their own mock data
        if (stripos($normalized, 'ibl_power') !== false && stripos($normalized, 'WHERE') === false) {
            return new MockDatabaseResult([]);
        }

        // Special handling for pythagorean stats queries (offense/defense stats)
        // Always intercept these queries to avoid returning standings data
        // The JOIN query uses aliases: off_fgm, off_ftm, off_tgm, def_fgm, def_ftm, def_tgm
        // Detects both old view names and inlined queries that aggregate from ibl_box_scores_teams
        if (stripos($normalized, 'ibl_team_offense_stats') !== false ||
            stripos($normalized, 'ibl_team_defense_stats') !== false ||
            (stripos($normalized, 'off_fgm') !== false && stripos($normalized, 'def_fgm') !== false)) {
            if ($this->mockPythagoreanData !== []) {
                $data = $this->mockPythagoreanData;
                // Translate base keys to aliased JOIN keys if needed
                if (isset($data['fgm']) && !isset($data['off_fgm'])) {
                    $data = [
                        'teamid' => 1,
                        'off_fgm' => $data['fgm'], 'off_ftm' => $data['ftm'], 'off_tgm' => $data['tgm'],
                        'def_fgm' => $data['fgm'], 'def_ftm' => $data['ftm'], 'def_tgm' => $data['tgm'],
                    ];
                }
                return new MockDatabaseResult([$data]);
            }
            // Return empty result if no pythagorean data configured
            return new MockDatabaseResult([]);
        }

        // Special handling for market maximums query (bulk MAX from ibl_plr)
        // Returns sensible defaults so tests don't produce undefined-key warnings
        // Exclude queries that use ibl_plr only in a LEFT JOIN subquery (e.g., FranchiseRecordBook)
        if (stripos($normalized, 'MAX(') !== false && stripos($normalized, 'ibl_plr') !== false
            && stripos($normalized, 'ibl_rcb') === false) {
            // If mock data has the correct aliased keys, use it directly
            if ($this->mockData !== [] && isset($this->mockData[0]['fga'])) {
                return new MockDatabaseResult($this->mockData);
            }
            // Return safe defaults (1 for each market maximum stat)
            $defaults = [
                'fga' => 1, 'fgp' => 1, 'fta' => 1, 'ftp' => 1,
                'tga' => 1, 'tgp' => 1, 'orb' => 1, 'drb' => 1,
                'ast' => 1, 'stl' => 1, 'r_tvr' => 1, 'blk' => 1,
                'foul' => 1, 'oo' => 1, 'od' => 1, 'r_drive_off' => 1,
                'dd' => 1, 'po' => 1, 'pd' => 1, 'r_trans_off' => 1, 'td' => 1,
            ];
            return new MockDatabaseResult([$defaults]);
        }

        // Special handling for voting queries (ASG and EOY tables)
        // Returns results from queue for consecutive queries
        if ((stripos($normalized, 'ibl_votes_ASG') !== false ||
             stripos($normalized, 'ibl_votes_EOY') !== false) &&
            $this->votingResultsQueue !== []) {
            $data = array_shift($this->votingResultsQueue);
            return new MockDatabaseResult($data ?? []);
        }
        
        // Smart filtering for player queries with pid/itemid/pickid
        // Match patterns like: WHERE pid = 1001, WHERE `pid` = 1001, WHERE pid=1001
        if (preg_match('/WHERE\s+(?:pid|itemid|pickid)\s*=\s*[\'"]?(\d+)[\'"]?/i', $normalized, $matches)) {
            $searchId = (int)$matches[1];
            $filteredData = [];
            
            // If mockData has multiple rows, find the matching one(s)
            foreach ($this->mockData as $row) {
                if (isset($row['pid']) && (int)$row['pid'] === $searchId) {
                    $filteredData[] = $row;
                } elseif (isset($row['itemid']) && (int)$row['itemid'] === $searchId) {
                    $filteredData[] = $row;
                } elseif (isset($row['pickid']) && (int)$row['pickid'] === $searchId) {
                    $filteredData[] = $row;
                }
            }
            
            // If we found matching row(s), return them; otherwise return all mockData (for backward compatibility)
            if ($filteredData !== []) {
                return new MockDatabaseResult($filteredData);
            }
        }
        
        // Return a mock result for SELECT queries
        return new MockDatabaseResult($this->mockData);
    }
    
    public function sql_result(object $result, int $row, int|string|null $field = null): mixed
    {
        if ($result instanceof MockDatabaseResult) {
            return $result->getResult($row, $field);
        }
        return null;
    }
    
    /**
     * @return array<int|string, mixed>|false
     */
    public function sql_fetchrow(object $result): array|false
    {
        if ($result instanceof MockDatabaseResult) {
            return $result->fetchRow();
        }
        return false;
    }

    /**
     * @return array<string, mixed>|false
     */
    public function sql_fetch_assoc(object $result): array|false
    {
        if ($result instanceof MockDatabaseResult) {
            return $result->fetchAssoc();
        }
        return false;
    }
    
    public function sql_numrows(object $result): int
    {
        // Allow manual override for testing
        if ($this->numRows !== null) {
            return $this->numRows;
        }
        
        if ($result instanceof MockDatabaseResult) {
            return $result->numRows();
        }
        return 0;
    }
    
    public function sql_affectedrows(): int
    {
        return $this->affectedRows;
    }
    
    /**
     * @param list<array<string, mixed>> $data
     */
    public function setMockData(array $data): void
    {
        $this->mockData = $data;
    }

    /**
     * @param list<array<string, mixed>> $data
     */
    public function setMockTradeInfo(array $data): void
    {
        $this->mockTradeInfo = $data;
        // Also set numRows to match trade info count
        $this->numRows = count($data);
    }

    /**
     * Set mock team data for team queries (ibl_team_info)
     * Used when tests need Team::initialize() to return valid team objects
     *
     * @param list<array<string, mixed>> $data
     */
    public function setMockTeamData(array $data): void
    {
        $this->mockTeamData = $data;
    }

    /**
     * Set mock pythagorean stats data for offense/defense stats queries
     * Used when tests need StandingsRepository::getTeamPythagoreanStats() to return valid data
     *
     * @param array<string, mixed> $data
     */
    public function setMockPythagoreanData(array $data): void
    {
        $this->mockPythagoreanData = $data;
    }

    /**
     * Set voting results queue for ASG/EOY voting queries
     * Each element is returned for consecutive sql_query() calls to voting tables
     * Used when tests need VotingResultsService to return voting data
     *
     * @param list<list<array<string, mixed>>> $resultsQueue
     */
    public function setVotingResultsQueue(array $resultsQueue): void
    {
        $this->votingResultsQueue = $resultsQueue;
    }
    
    public function setNumRows(int $numRows): void
    {
        $this->numRows = $numRows;
    }
    
    public function setAffectedRows(int $affectedRows): void
    {
        $this->affectedRows = $affectedRows;
    }
    
    public function setReturnTrue(bool $returnTrue = true): void
    {
        $this->returnTrue = $returnTrue;
    }

    /**
     * Configure the mock so the Nth INSERT (1-based, counting only INSERTs)
     * returns false, mimicking a constraint violation mid-import.
     */
    public function failOnNthInsert(int $n): void
    {
        $this->failOnNthInsert = $n;
    }
    
    /**
     * @return list<string>
     */
    public function getExecutedQueries(): array
    {
        return array_map(
            static fn (string $q): string => str_replace('`', '', $q),
            $this->executedQueries
        );
    }

    /**
     * SQL queries interleaved with transaction markers (BEGIN/COMMIT/ROLLBACK),
     * in execution order. Use this to assert transactional wrapping/ordering.
     *
     * @return list<string>
     */
    public function getOperationLog(): array
    {
        return array_map(
            static fn (string $q): string => str_replace('`', '', $q),
            $this->operationLog
        );
    }

    public function clearQueries(): void
    {
        $this->executedQueries = [];
        $this->operationLog = [];
        $this->insertCount = 0;
    }
    
    public function sql_escape_string(string $string): string
    {
        // Simple escaping for mock - in production this would use mysqli_real_escape_string
        return addslashes($string);
    }

    /**
     * Override real_escape_string to work without a real connection
     * Uses addslashes as a simple substitute for testing
     */
    public function real_escape_string(string $string): string
    {
        return addslashes($string);
    }

    /**
     * Mock transaction support — record lifecycle into the operation log (not the
     * SQL-only executed-query log) so tests can assert the BEGIN/COMMIT/ROLLBACK
     * ordering around a unit of work without perturbing getExecutedQueries().
     */
    public function begin_transaction(int $flags = 0, ?string $name = null): bool
    {
        $this->operationLog[] = 'BEGIN';
        return true;
    }

    public function commit(int $flags = 0, ?string $name = null): bool
    {
        $this->operationLog[] = 'COMMIT';
        return true;
    }

    public function rollback(int $flags = 0, ?string $name = null): bool
    {
        $this->operationLog[] = 'ROLLBACK';
        return true;
    }

    /**
     * Mock prepared statement support
     * Returns a MockPreparedStatement that supports bind_param and execute.
     *
     * The return type cannot be made covariant with mysqli::prepare()'s
     * mysqli_stmt|false: MockPreparedStatement cannot extend mysqli_stmt because
     * mysqli_stmt::get_result() returns mysqli_result|false and mysqli_result
     * declares read-only properties (num_rows, field_count, …) that a mock must
     * write, so it cannot be subclassed. The ReturnTypeWillChange attribute
     * suppresses the runtime LSP deprecation; the ignore below is its
     * static-analysis twin.
     *
     * @phpstan-ignore method.childReturnType
     */
    #[\ReturnTypeWillChange]
    public function prepare(string $query): MockPreparedStatement
    {
        return new MockPreparedStatement($this, $query);
    }
}
