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
 * Duck-types mysqli_stmt without extending it to avoid type constraints
 */
class MockPreparedStatement
{
    private $mockDb;
    private $query;
    private $boundParams = [];
    private $paramTypes = [];
    public string|int $affected_rows = 0;
    public string $error = '';
    public int $errno = 0;

    public function __construct($mockDb = null, $query = '')
    {
        $this->mockDb = $mockDb ?? new MockDatabase();
        $this->query = $query;
    }

    /**
     * Bind parameters to the prepared statement
     * @param string $types Parameter types (s=string, i=integer, d=double, b=blob)
     * @param mixed ...$params Parameters to bind
     */
    public function bind_param($types, &...$params): bool
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
    public function execute(?array $params = null): bool
    {
        // Replace placeholders with bound values in the query
        $query = $this->query;
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
        
        // Execute the query using the mock database
        $result = $this->mockDb->sql_query($query);
        
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
     * TEMPORARY: Returns object|false during migration to support MockMysqliResult
     */
    public function get_result(): object|false
    {
        // Replace placeholders with bound values in the query
        $query = $this->query;
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
        
        // Execute and return mock result wrapped to look like mysqli_result
        $mockResult = $this->mockDb->sql_query($query);
        
        if ($mockResult instanceof MockDatabaseResult) {
            return new MockMysqliResult($mockResult);
        }
        
        return false;
    }
    
    public function close(): bool
    {
        // Mock close - just return true
        return true;
    }
}

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

class Discord
{
    public static function getDiscordIDFromTeamname($teamname)
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
            $this->commonRepository = new \Services\CommonMysqliRepository($db);
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
    
    const IBL_PRESEASON_YEAR = 9998;
    const IBL_HEAT_MONTH = 10;
    const IBL_REGULAR_SEASON_STARTING_MONTH = 11;
    const IBL_ALL_STAR_MONTH = 2;
    const IBL_REGULAR_SEASON_ENDING_MONTH = 5;
    const IBL_PLAYOFF_MONTH = 6;
    
    protected $db;
    
    /**
     * Mock constructor for testing - accepts mysqli or legacy db object
     * 
     * @param object $db Database connection (mysqli or legacy mock)
     */
    public function __construct(object $db)
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
    public function getSeasonPhase(): string
    {
        return $this->phase;
    }
    
    public function getSeasonEndingYear(): string
    {
        return (string)$this->endingYear;
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

// Set up $_SERVER variables needed by config.php
if (!isset($_SERVER['SERVER_NAME'])) {
    $_SERVER['SERVER_NAME'] = 'localhost';
}
if (!isset($_SERVER['SCRIPT_FILENAME'])) {
    $_SERVER['SCRIPT_FILENAME'] = __DIR__ . '/../index.php';
}

// Load the configuration for database access
require_once __DIR__ . '/../config.php';

// Set up global $mysqli_db mock for tests that use Player or other refactored classes
// Note: Integration tests should set up their own $mysqli_db that shares the same MockDatabase
// instance used by the test. See ExtensionIntegrationTest for example.
//
// Unit tests that directly mock Player/PlayerRepository don't need this global.
