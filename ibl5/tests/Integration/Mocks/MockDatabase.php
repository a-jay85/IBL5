<?php

namespace Tests\Integration\Mocks;

/**
 * Mock database class for testing
 * Provides a mock implementation of database operations without requiring actual database connections
 * Extends mysqli to satisfy type hints in modern code while supporting legacy sql_* methods
 */
class MockDatabase extends \mysqli
{
    /**
     * Override constructor to prevent actual database connection
     */
    public function __construct()
    {
        // Don't call parent constructor - we're a mock that doesn't need a real connection
    }

    private array $mockData = [];
    private array $mockTradeInfo = [];
    private array $mockTeamData = [];
    private array $mockPythagoreanData = [];
    private array $votingResultsQueue = [];
    private ?int $numRows = null;
    private bool $returnTrue = true;
    private array $executedQueries = [];
    private int $affectedRows = 0;
    
    public function sql_query(string $query): bool|object
    {
        // Track all executed queries for verification
        $this->executedQueries[] = $query;
        
        // For queries that expect boolean return (INSERT, UPDATE, DELETE)
        if (stripos($query, 'INSERT') === 0 || 
            stripos($query, 'UPDATE') === 0 || 
            stripos($query, 'DELETE') === 0) {
            // Set affected rows for UPDATE/DELETE operations (default to 1 for successful operations)
            if ($this->returnTrue) {
                $this->affectedRows = 1;
            }
            return $this->returnTrue;
        }
        
        // Special handling for PID existence checks (generateUniquePid)
        // Return empty result to indicate PID is available unless explicitly configured
        // Only match the specific "SELECT 1 FROM ibl_plr WHERE pid = X" pattern for existence checks
        if (stripos($query, 'SELECT 1 FROM ibl_plr WHERE pid = ') !== false) {
            return new MockDatabaseResult([]);
        }
        
        // Special handling for trade info queries (support both direct and prepared statement patterns)
        if (stripos($query, 'ibl_trade_info') !== false &&
            stripos($query, 'tradeofferid') !== false &&
            !empty($this->mockTradeInfo)) {
            return new MockDatabaseResult($this->mockTradeInfo);
        }

        // Special handling for team info queries - return mock team data if available
        if (stripos($query, 'ibl_team_info') !== false && !empty($this->mockTeamData)) {
            // Try to match by teamid if specified in query
            if (preg_match('/teamid\s*=\s*[\'"]?(\d+)[\'"]?/i', $query, $matches)) {
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

        // Special handling for pythagorean stats queries (offense/defense stats)
        // Always intercept these queries to avoid returning standings data
        // The JOIN query uses aliases: off_fgm, off_ftm, off_tgm, def_fgm, def_ftm, def_tgm
        if (stripos($query, 'ibl_team_offense_stats') !== false ||
            stripos($query, 'ibl_team_defense_stats') !== false) {
            if (!empty($this->mockPythagoreanData)) {
                $data = $this->mockPythagoreanData;
                // Translate base keys to aliased JOIN keys if needed
                if (isset($data['fgm']) && !isset($data['off_fgm'])) {
                    $data = [
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
        if (stripos($query, 'MAX(') !== false && stripos($query, 'ibl_plr') !== false) {
            // If mock data has the correct aliased keys, use it directly
            if (!empty($this->mockData) && isset($this->mockData[0]['fga'])) {
                return new MockDatabaseResult($this->mockData);
            }
            // Return safe defaults (1 for each market maximum stat)
            $defaults = [
                'fga' => 1, 'fgp' => 1, 'fta' => 1, 'ftp' => 1,
                'tga' => 1, 'tgp' => 1, 'orb' => 1, 'drb' => 1,
                'ast' => 1, 'stl' => 1, 'r_to' => 1, 'blk' => 1,
                'foul' => 1, 'oo' => 1, 'od' => 1, 'do' => 1,
                'dd' => 1, 'po' => 1, 'pd' => 1, 'td' => 1,
            ];
            return new MockDatabaseResult([$defaults]);
        }

        // Special handling for voting queries (ASG and EOY tables)
        // Returns results from queue for consecutive queries
        if ((stripos($query, 'ibl_votes_ASG') !== false ||
             stripos($query, 'ibl_votes_EOY') !== false) &&
            !empty($this->votingResultsQueue)) {
            $data = array_shift($this->votingResultsQueue);
            return new MockDatabaseResult($data ?? []);
        }
        
        // Smart filtering for player queries with pid/itemid/pickid
        // Match patterns like: WHERE pid = 1001, WHERE `pid` = 1001, WHERE pid=1001
        if (preg_match('/WHERE\s+`?(?:pid|itemid|pickid)`?\s*=\s*[\'"]?(\d+)[\'"]?/i', $query, $matches)) {
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
            if (!empty($filteredData)) {
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
    
    public function sql_fetchrow(object $result): array|false
    {
        if ($result instanceof MockDatabaseResult) {
            return $result->fetchRow();
        }
        return false;
    }
    
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
    
    public function setMockData(array $data): void
    {
        $this->mockData = $data;
    }
    
    public function setMockTradeInfo(array $data): void
    {
        $this->mockTradeInfo = $data;
        // Also set numRows to match trade info count
        $this->numRows = count($data);
    }

    /**
     * Set mock team data for team queries (ibl_team_info)
     * Used when tests need Team::initialize() to return valid team objects
     */
    public function setMockTeamData(array $data): void
    {
        $this->mockTeamData = $data;
    }

    /**
     * Set mock pythagorean stats data for offense/defense stats queries
     * Used when tests need StandingsRepository::getTeamPythagoreanStats() to return valid data
     */
    public function setMockPythagoreanData(array $data): void
    {
        $this->mockPythagoreanData = $data;
    }

    /**
     * Set voting results queue for ASG/EOY voting queries
     * Each element is returned for consecutive sql_query() calls to voting tables
     * Used when tests need VotingResultsService to return voting data
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
    
    public function getExecutedQueries(): array
    {
        return $this->executedQueries;
    }
    
    public function clearQueries(): void
    {
        $this->executedQueries = [];
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
     * Mock transaction support - no-op stubs to prevent "object is already closed" errors
     */
    public function begin_transaction(int $flags = 0, ?string $name = null): bool
    {
        return true;
    }

    public function commit(int $flags = 0, ?string $name = null): bool
    {
        return true;
    }

    public function rollback(int $flags = 0, ?string $name = null): bool
    {
        return true;
    }

    /**
     * Mock prepared statement support
     * Returns a MockPreparedStatement that supports bind_param and execute
     */
    #[\ReturnTypeWillChange]
    public function prepare(string $query): MockPreparedStatement
    {
        return new MockPreparedStatement($this, $query);
    }
}
