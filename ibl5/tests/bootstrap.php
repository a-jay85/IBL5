<?php
/**
 * PHPUnit Bootstrap for Trading Module Tests
 * 
 * This bootstrap file sets up the testing environment for the Trading module
 * without requiring the full IBL5 application context.
 */

// Define constants that might be needed
if (!defined('DIRECTORY_SEPARATOR')) {
    define('DIRECTORY_SEPARATOR', '/');
}

// Define mock classes for testing BEFORE loading the autoloader
// This ensures mock classes take precedence over real classes
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
            return $this->returnTrue;
        }
        
        // Special handling for trade info queries
        if (stripos($query, 'ibl_trade_info') !== false && !empty($this->mockTradeInfo)) {
            return new MockDatabaseResult($this->mockTradeInfo);
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

/**
 * Mock prepared statement for testing mysqli-style prepared statements
 */
class MockPreparedStatement
{
    private $mockDb;
    private $query;
    private $boundParams = [];
    private $paramTypes = [];

    public function __construct($mockDb, $query)
    {
        $this->mockDb = $mockDb;
        $this->query = $query;
    }

    /**
     * Bind parameters to the prepared statement
     * @param string $types Parameter types (s=string, i=integer, d=double, b=blob)
     * @param mixed ...$params Parameters to bind
     */
    public function bind_param($types, &...$params)
    {
        $this->paramTypes = str_split($types);
        foreach ($params as $index => $param) {
            $this->boundParams[$index] = $param;
        }
        return true;
    }

    /**
     * Execute the prepared statement
     */
    public function execute()
    {
        // Replace placeholders with bound values in the query
        $query = $this->query;
        foreach ($this->boundParams as $param) {
            // Simple placeholder replacement (?) with the actual value
            $value = is_string($param) ? "'" . addslashes($param) . "'" : $param;
            $query = preg_replace('/\?/', $value, $query, 1);
        }
        
        // Execute the query using the mock database
        return $this->mockDb->sql_query($query);
    }

    /**
     * Get the result of the prepared statement
     */
    public function get_result()
    {
        // Execute and return result
        return $this->execute();
    }
}

class Discord
{
    public static function getDiscordIDFromTeamname($db, $teamname)
    {
        return '123456789';
    }
    
    public static function postToChannel($channel, $message)
    {
        return true;
    }
}

class Shared
{
        protected $db;
        protected $commonRepository;
        
        public function __construct($db)
        {
            $this->db = $db;
            $this->commonRepository = new \Services\CommonRepository($db);
        }
        
        public function getTidFromTeamname($teamname)
        {
            return $this->commonRepository->getTidFromTeamname($teamname);
        }
        
        public function resetSimContractExtensionAttempts()
        {
            echo '<p>Resetting sim contract extension attempts...</p>';
            $sqlQueryString = "UPDATE ibl_team_info SET Used_Extension_This_Chunk = 0;";
            if ($this->db->sql_query($sqlQueryString)) {
                echo '<p>Sim contract extension attempts have been reset.</p>';
                return;
            }
        }
        
        public function getCurrentOwnerOfDraftPick($draftYear, $draftRound, $teamNameOfDraftPickOrigin)
        {
            // Mock implementation for testing
            return $teamNameOfDraftPickOrigin;
        }
}

class UI
{
    public static function displayDebugOutput($content, $title = 'Debug Output')
    {
            // In test mode, don't output anything
            // This prevents test output pollution
            if (defined('PHPUNIT_RUNNING') || php_sapi_name() === 'cli') {
                return;
            }
            
            // Otherwise, output normally (though this shouldn't happen in tests)
            static $debugId = 0;
            $debugId++;
            
            echo "<div style='margin: 10px 0; border: 1px solid #ccc; border-radius: 4px;'>
                <div style='padding: 8px; background-color: #f5f5f5; border-bottom: 1px solid #ccc; cursor: pointer;'
                     onclick='toggleDebug$debugId()'>
                    <span id='debugIcon$debugId'>▶</span> $title
                </div>
                <pre id='debugContent$debugId' style='display: none; margin: 0; padding: 8px; background-color: #fff; overflow: auto;'>$content</pre>
            </div>
            <script>
                function toggleDebug$debugId() {
                    var content = document.getElementById('debugContent$debugId');
                    var icon = document.getElementById('debugIcon$debugId');
                    if (content.style.display === 'none') {
                        content.style.display = 'block';
                        icon.textContent = '▼';
                    } else {
                        content.style.display = 'none';
                        icon.textContent = '▶';
                    }
                }
            </script>";
        }
}

class Season
{
    public $phase = 'Regular Season';
    public $endingYear = 2024;
    public $beginningYear = 2023;
    public $regularSeasonStartDate;
    public $postAllStarStartDate;
    public $playoffsStartDate;
    public $playoffsEndDate;
    public $lastSimNumber = 1;
    public $lastSimStartDate = '2024-01-01';
    public $lastSimEndDate = '2024-01-02';
    public $projectedNextSimEndDate = '2024-01-03';
    public $allowTrades = 'Yes';
    public $allowWaivers = 'Yes';
    public $freeAgencyNotificationsState = 'Off';
    
    const IBL_PRESEASON_MONTH = 9;
    const IBL_HEAT_MONTH = 10;
    const IBL_REGULAR_SEASON_STARTING_MONTH = 11;
    const IBL_ALL_STAR_MONTH = 2;
    const IBL_REGULAR_SEASON_ENDING_MONTH = 5;
    const IBL_PLAYOFF_MONTH = 6;
    
    protected $db;
    
    public function __construct($db)
    {
        $this->db = $db;
        // Initialize properties without database calls for testing
        $this->phase = 'Regular Season';
        $this->endingYear = 2024;
        $this->beginningYear = 2023;
        $this->regularSeasonStartDate = date_create("2023-11-01");
        $this->postAllStarStartDate = date_create("2024-02-04");
        $this->playoffsStartDate = date_create("2024-06-01");
        $this->playoffsEndDate = date_create("2024-06-30");
    }
    
    // Mock the methods that would normally query the database
    private function getSeasonPhase()
    {
        return $this->phase;
    }
    
    private function getSeasonEndingYear()
    {
        return $this->endingYear;
    }
    
    private function getLastSimDatesArray()
    {
        return [
            'Sim' => $this->lastSimNumber,
            'Start Date' => $this->lastSimStartDate,
            'End Date' => $this->lastSimEndDate
        ];
    }
    
    private function getProjectedNextSimEndDate($db, $lastSimEndDate)
    {
        return $this->projectedNextSimEndDate;
    }
}

// Load the IBL5 autoloader AFTER defining mock classes
// This ensures mock classes take precedence over real classes
require_once __DIR__ . '/../autoloader.php';

