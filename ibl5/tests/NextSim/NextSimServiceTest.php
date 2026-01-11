<?php

declare(strict_types=1);

namespace Tests\NextSim;

use PHPUnit\Framework\TestCase;
use NextSim\NextSimService;

/**
 * NextSimServiceTest - Tests for NextSimService
 */
class NextSimServiceTest extends TestCase
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
                return false;
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

    public function testServiceCanBeInstantiated(): void
    {
        $service = new NextSimService($this->mockMysqliDb);
        
        $this->assertInstanceOf(NextSimService::class, $service);
    }

    public function testServiceImplementsCorrectInterface(): void
    {
        $service = new NextSimService($this->mockMysqliDb);
        
        $this->assertInstanceOf(
            \NextSim\Contracts\NextSimServiceInterface::class,
            $service
        );
    }

    // ============================================
    // MULTIPLE INSTANCES TEST
    // ============================================

    public function testMultipleServicesCanBeInstantiated(): void
    {
        $service1 = new NextSimService($this->mockMysqliDb);
        $service2 = new NextSimService($this->mockMysqliDb);
        
        $this->assertNotSame($service1, $service2);
    }
}
