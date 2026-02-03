<?php

declare(strict_types=1);

namespace Tests\DepthChartEntry;

use PHPUnit\Framework\TestCase;
use DepthChartEntry\DepthChartEntrySubmissionHandler;

/**
 * DepthChartEntrySubmissionHandlerTest - Tests for depth chart form submission handling
 *
 * Tests:
 * - Handler instantiation
 * - Interface compliance
 * - Empty submission handling
 */
class DepthChartEntrySubmissionHandlerTest extends TestCase
{
    private \MockDatabase $mockDb;
    private object $mockMysqliDb;

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

            public function real_escape_string(string $string): string
            {
                return addslashes($string);
            }
        };
        
        $GLOBALS['mysqli_db'] = $this->mockMysqliDb;
    }

    // ============================================
    // CONSTRUCTOR TESTS
    // ============================================

    public function testHandlerCanBeInstantiated(): void
    {
        $handler = new DepthChartEntrySubmissionHandler($this->mockMysqliDb);
        
        $this->assertInstanceOf(DepthChartEntrySubmissionHandler::class, $handler);
    }

    public function testHandlerImplementsCorrectInterface(): void
    {
        $handler = new DepthChartEntrySubmissionHandler($this->mockMysqliDb);
        
        $this->assertInstanceOf(
            \DepthChartEntry\Contracts\DepthChartEntrySubmissionHandlerInterface::class,
            $handler
        );
    }

    // ============================================
    // EMPTY SUBMISSION HANDLING TESTS
    // ============================================

    public function testHandlerOutputsErrorForEmptyTeamName(): void
    {
        $handler = new DepthChartEntrySubmissionHandler($this->mockMysqliDb);
        
        // Empty team name should output error
        ob_start();
        $handler->handleSubmission(['Team_Name' => '']);
        $output = ob_get_clean();
        
        $this->assertStringContainsString('Error', $output);
    }

    public function testHandlerOutputsErrorForMissingTeamName(): void
    {
        $handler = new DepthChartEntrySubmissionHandler($this->mockMysqliDb);
        
        // Missing team name should output error
        ob_start();
        $handler->handleSubmission([]);
        $output = ob_get_clean();
        
        $this->assertStringContainsString('Error', $output);
    }

    // ============================================
    // MULTIPLE INSTANCES TEST
    // ============================================

    public function testMultipleHandlersCanBeInstantiated(): void
    {
        $handler1 = new DepthChartEntrySubmissionHandler($this->mockMysqliDb);
        $handler2 = new DepthChartEntrySubmissionHandler($this->mockMysqliDb);
        
        $this->assertInstanceOf(DepthChartEntrySubmissionHandler::class, $handler1);
        $this->assertInstanceOf(DepthChartEntrySubmissionHandler::class, $handler2);
        $this->assertNotSame($handler1, $handler2);
    }
}
