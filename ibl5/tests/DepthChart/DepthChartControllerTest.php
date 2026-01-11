<?php

declare(strict_types=1);

namespace Tests\DepthChart;

use PHPUnit\Framework\TestCase;
use DepthChart\DepthChartController;

/**
 * DepthChartControllerTest - Tests for the depth chart workflow controller
 *
 * Tests:
 * - Controller instantiation
 * - Interface compliance
 * - Dependency injection
 */
class DepthChartControllerTest extends TestCase
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

    public function testControllerCanBeInstantiated(): void
    {
        $controller = new DepthChartController($this->mockMysqliDb);
        
        $this->assertInstanceOf(DepthChartController::class, $controller);
    }

    public function testControllerImplementsCorrectInterface(): void
    {
        $controller = new DepthChartController($this->mockMysqliDb);
        
        $this->assertInstanceOf(
            \DepthChart\Contracts\DepthChartControllerInterface::class,
            $controller
        );
    }

    // ============================================
    // MULTIPLE INSTANCES TEST
    // ============================================

    public function testMultipleControllersCanBeInstantiated(): void
    {
        $controller1 = new DepthChartController($this->mockMysqliDb);
        $controller2 = new DepthChartController($this->mockMysqliDb);
        
        $this->assertInstanceOf(DepthChartController::class, $controller1);
        $this->assertInstanceOf(DepthChartController::class, $controller2);
        $this->assertNotSame($controller1, $controller2);
    }
}
