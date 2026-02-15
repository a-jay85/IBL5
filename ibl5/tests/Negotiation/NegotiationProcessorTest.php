<?php

declare(strict_types=1);

namespace Tests\Negotiation;

use PHPUnit\Framework\TestCase;
use Negotiation\NegotiationProcessor;

/**
 * NegotiationProcessorTest - Tests for the negotiation workflow processor
 *
 * Tests:
 * - Processor instantiation
 * - Player loading and error handling
 * - Validation flow behavior
 */
class NegotiationProcessorTest extends TestCase
{
    private \MockDatabase $mockDb;
    private \mysqli $mockMysqliDb;

    protected function setUp(): void
    {
        $this->mockDb = new \MockDatabase();
        $this->setupMockMysqliDb();
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['mysqli_db']);
    }

    private function setupMockMysqliDb(): void
    {
        $mockDb = $this->mockDb;
        
        $this->mockMysqliDb = new class($mockDb) extends \mysqli {
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
        
        $GLOBALS['mysqli_db'] = $this->mockMysqliDb;
    }

    // ============================================
    // CONSTRUCTOR TESTS
    // ============================================

    public function testProcessorCanBeInstantiated(): void
    {
        $processor = new NegotiationProcessor($this->mockMysqliDb);

        $this->assertInstanceOf(NegotiationProcessor::class, $processor);
    }

    // ============================================
    // PROCESS NEGOTIATION ERROR HANDLING TESTS
    // ============================================

    public function testProcessNegotiationReturnsErrorForInvalidPlayer(): void
    {
        $processor = new NegotiationProcessor($this->mockMysqliDb);
        
        // Empty mock data means player won't be found
        $this->mockDb->setMockData([]);
        
        $result = $processor->processNegotiation(999, 'Test Team', 'prefix');
        
        $this->assertStringContainsString('not found', $result);
    }

    public function testProcessNegotiationReturnsHtmlOutput(): void
    {
        $processor = new NegotiationProcessor($this->mockMysqliDb);
        
        // Setup complete player data
        $this->mockDb->setMockData([$this->getCompletePlayerData()]);
        
        $result = $processor->processNegotiation(1, 'Test Team', 'prefix');
        
        $this->assertIsString($result);
        // Result should contain HTML markup
        $this->assertMatchesRegularExpression('/<.*>/', $result);
    }

    // ============================================
    // INTERFACE COMPLIANCE TESTS
    // ============================================

    public function testProcessorImplementsCorrectInterface(): void
    {
        $processor = new NegotiationProcessor($this->mockMysqliDb);
        
        $this->assertInstanceOf(
            \Negotiation\Contracts\NegotiationProcessorInterface::class,
            $processor
        );
    }

    // ============================================
    // HELPER METHODS
    // ============================================

    /**
     * Get complete player data with all required fields
     */
    private function getCompletePlayerData(array $overrides = []): array
    {
        return array_merge([
            // Basic fields
            'pid' => 1,
            'name' => 'Test Player',
            'firstname' => 'Test',
            'lastname' => 'Player',
            'nickname' => '',
            'teamname' => 'Test Team',
            'tid' => 1,
            'pos' => 'G',
            'position' => 'G',
            'age' => 25,
            'ordinal' => 1,
            // Physical attributes
            'height' => 75,
            'weight' => 200,
            'htft' => 6,
            'htin' => 3,
            'wt' => 200,
            // Contract fields
            'cy' => 0,
            'cyt' => 0,
            'cy1' => 0,
            'cy2' => 0,
            'cy3' => 0,
            'cy4' => 0,
            'cy5' => 0,
            'cy6' => 0,
            'exp' => 3,
            'bird' => 0,
            'bird_years' => 0,
            // Status fields
            'retired' => 0,
            'injured' => 0,
            'signed' => 0,
            'droptime' => 0,
            // Free agency preferences
            'loyalty' => 50,
            'playingTime' => 50,
            'winner' => 50,
            'tradition' => 50,
            'security' => 50,
            // Base rating fields
            'r_fga' => 50,
            'r_fgp' => 50,
            'r_fta' => 50,
            'r_ftp' => 50,
            'r_tga' => 50,
            'r_tgp' => 50,
            'r_orb' => 50,
            'r_drb' => 50,
            'r_ast' => 50,
            'r_stl' => 50,
            'r_to' => 50,
            'r_blk' => 50,
            'r_foul' => 50,
            // Position-based ratings
            'oo' => 50,
            'od' => 50,
            'do' => 50,
            'dd' => 50,
            'po' => 50,
            'pd' => 50,
            'to' => 50,
            'td' => 50,
            // Other ratings
            'Clutch' => 50,
            'Consistency' => 50,
            'talent' => 50,
            'skill' => 50,
            'intangibles' => 50,
            'ovr' => 75,
            // Draft fields
            'draftyear' => 2020,
            'draftround' => 1,
            'draftpickno' => 10,
            'draftedby' => 'Test Team',
            'draftedbycurrentname' => 'Test Team',
            'college' => 'Test University',
            // Season info
            'Phase' => 'Regular Season',
            'Beginning_Year' => 2024,
            'Ending_Year' => 2025,
        ], $overrides);
    }
}
