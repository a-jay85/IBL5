<?php

declare(strict_types=1);

namespace Tests\DepthChartEntry;

use PHPUnit\Framework\TestCase;
use DepthChartEntry\DepthChartEntryController;

/**
 * DepthChartEntryControllerTest - Tests for the depth chart workflow controller
 *
 * Tests:
 * - Controller instantiation
 * - Interface compliance
 * - Dependency injection
 */
class DepthChartEntryControllerTest extends TestCase
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
        $controller = new DepthChartEntryController($this->mockMysqliDb);
        
        $this->assertInstanceOf(DepthChartEntryController::class, $controller);
    }

    public function testControllerImplementsCorrectInterface(): void
    {
        $controller = new DepthChartEntryController($this->mockMysqliDb);
        
        $this->assertInstanceOf(
            \DepthChartEntry\Contracts\DepthChartEntryControllerInterface::class,
            $controller
        );
    }

    // ============================================
    // MULTIPLE INSTANCES TEST
    // ============================================

    public function testMultipleControllersCanBeInstantiated(): void
    {
        $controller1 = new DepthChartEntryController($this->mockMysqliDb);
        $controller2 = new DepthChartEntryController($this->mockMysqliDb);

        $this->assertInstanceOf(DepthChartEntryController::class, $controller1);
        $this->assertInstanceOf(DepthChartEntryController::class, $controller2);
        $this->assertNotSame($controller1, $controller2);
    }

    // ============================================
    // getTableOutput() SIGNATURE TESTS
    // ============================================

    public function testGetTableOutputAcceptsSplitParameter(): void
    {
        $method = new \ReflectionMethod(DepthChartEntryController::class, 'getTableOutput');
        $params = $method->getParameters();

        $this->assertCount(3, $params);
        $this->assertSame('teamID', $params[0]->getName());
        $this->assertSame('display', $params[1]->getName());
        $this->assertSame('split', $params[2]->getName());
        $this->assertTrue($params[2]->isOptional());
        $this->assertTrue($params[2]->allowsNull());
        $this->assertNull($params[2]->getDefaultValue());
    }

    public function testInterfaceDeclaresGetTableOutputWithSplitParameter(): void
    {
        $method = new \ReflectionMethod(
            \DepthChartEntry\Contracts\DepthChartEntryControllerInterface::class,
            'getTableOutput'
        );
        $params = $method->getParameters();

        $this->assertCount(3, $params);
        $this->assertSame('split', $params[2]->getName());
        $this->assertTrue($params[2]->isOptional());
    }
}
