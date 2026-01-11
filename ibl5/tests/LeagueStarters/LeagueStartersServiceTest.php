<?php

declare(strict_types=1);

namespace Tests\LeagueStarters;

use PHPUnit\Framework\TestCase;
use LeagueStarters\LeagueStartersService;

/**
 * LeagueStartersServiceTest - Tests for LeagueStartersService
 */
class LeagueStartersServiceTest extends TestCase
{
    private \MockDatabase $mockDb;
    private object $mockMysqliDb;
    private \League $mockLeague;

    protected function setUp(): void
    {
        $this->mockDb = new \MockDatabase();
        $this->setupMockMysqliDb();
        $this->mockLeague = new \League($this->mockMysqliDb);
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
        $service = new LeagueStartersService($this->mockMysqliDb, $this->mockLeague);
        
        $this->assertInstanceOf(LeagueStartersService::class, $service);
    }

    public function testServiceImplementsCorrectInterface(): void
    {
        $service = new LeagueStartersService($this->mockMysqliDb, $this->mockLeague);
        
        $this->assertInstanceOf(
            \LeagueStarters\Contracts\LeagueStartersServiceInterface::class,
            $service
        );
    }

    // ============================================
    // GET ALL STARTERS BY POSITION TESTS
    // ============================================

    public function testGetAllStartersByPositionReturnsEmptyPositionsWhenNoTeams(): void
    {
        $service = new LeagueStartersService($this->mockMysqliDb, $this->mockLeague);
        
        $result = $service->getAllStartersByPosition();
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('PG', $result);
        $this->assertArrayHasKey('SG', $result);
        $this->assertArrayHasKey('SF', $result);
        $this->assertArrayHasKey('PF', $result);
        $this->assertArrayHasKey('C', $result);
    }

    public function testGetAllStartersByPositionReturnsEmptyArraysForEachPosition(): void
    {
        $service = new LeagueStartersService($this->mockMysqliDb, $this->mockLeague);
        
        $result = $service->getAllStartersByPosition();
        
        $this->assertEmpty($result['PG']);
        $this->assertEmpty($result['SG']);
        $this->assertEmpty($result['SF']);
        $this->assertEmpty($result['PF']);
        $this->assertEmpty($result['C']);
    }

    // ============================================
    // MULTIPLE INSTANCES TEST
    // ============================================

    public function testMultipleServicesCanBeInstantiated(): void
    {
        $service1 = new LeagueStartersService($this->mockMysqliDb, $this->mockLeague);
        $service2 = new LeagueStartersService($this->mockMysqliDb, $this->mockLeague);
        
        $this->assertNotSame($service1, $service2);
    }
}
