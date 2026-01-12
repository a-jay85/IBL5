<?php

namespace Tests\Integration\Mocks;

/**
 * Mock database class for testing
 * Provides a mock implementation of database operations without requiring actual database connections
 */
class MockDatabase
{
    private $mockData = [];
    private $mockTradeInfo = [];
    private $numRows = null;
    private $returnTrue = true;
    private $executedQueries = [];
    private $affectedRows = 0;
    
    public function sql_query($query)
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
    
    public function sql_result($result, $row, $field = null)
    {
        if ($result instanceof MockDatabaseResult) {
            return $result->getResult($row, $field);
        }
        return null;
    }
    
    public function sql_fetchrow($result)
    {
        if ($result instanceof MockDatabaseResult) {
            return $result->fetchRow();
        }
        return false;
    }
    
    public function sql_fetch_assoc($result)
    {
        if ($result instanceof MockDatabaseResult) {
            return $result->fetchAssoc();
        }
        return false;
    }
    
    public function sql_numrows($result)
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
    
    public function sql_affectedrows()
    {
        return $this->affectedRows;
    }
    
    public function setMockData($data)
    {
        $this->mockData = $data;
    }
    
    public function setMockTradeInfo($data)
    {
        $this->mockTradeInfo = $data;
        // Also set numRows to match trade info count
        $this->numRows = count($data);
    }
    
    public function setNumRows($numRows)
    {
        $this->numRows = $numRows;
    }
    
    public function setAffectedRows($affectedRows)
    {
        $this->affectedRows = $affectedRows;
    }
    
    public function setReturnTrue($returnTrue = true)
    {
        $this->returnTrue = $returnTrue;
    }
    
    public function getExecutedQueries()
    {
        return $this->executedQueries;
    }
    
    public function clearQueries()
    {
        $this->executedQueries = [];
    }
    
    public function sql_escape_string($string)
    {
        // Simple escaping for mock - in production this would use mysqli_real_escape_string
        return addslashes($string);
    }

    /**
     * Mock prepared statement support
     * Returns a MockPreparedStatement that supports bind_param and execute
     */
    public function prepare($query)
    {
        return new MockPreparedStatement($this, $query);
    }
}
