<?php

declare(strict_types=1);

namespace Tests\Trading;

use PHPUnit\Framework\TestCase;
use Trading\TradingRepository;

/**
 * TradingRepositoryTest - Tests for TradingRepository database operations
 *
 * Tests:
 * - Repository instantiation
 * - Interface compliance
 * - Query execution via mock
 */
class TradingRepositoryTest extends TestCase
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

    public function testRepositoryCanBeInstantiated(): void
    {
        $repository = new TradingRepository($this->mockMysqliDb);
        
        $this->assertInstanceOf(TradingRepository::class, $repository);
    }

    public function testRepositoryImplementsCorrectInterface(): void
    {
        $repository = new TradingRepository($this->mockMysqliDb);
        
        $this->assertInstanceOf(
            \Trading\Contracts\TradingRepositoryInterface::class,
            $repository
        );
    }

    public function testRepositoryExtendsBaseMysqliRepository(): void
    {
        $repository = new TradingRepository($this->mockMysqliDb);
        
        $this->assertInstanceOf(
            \BaseMysqliRepository::class,
            $repository
        );
    }

    // ============================================
    // GET PLAYER FOR TRADE VALIDATION TESTS
    // ============================================

    public function testGetPlayerForTradeValidationReturnsNullWhenNoData(): void
    {
        $repository = new TradingRepository($this->mockMysqliDb);
        $this->mockDb->setMockData([]);

        $result = $repository->getPlayerForTradeValidation(1);

        $this->assertNull($result);
    }

    public function testGetPlayerForTradeValidationReturnsPlayerData(): void
    {
        $repository = new TradingRepository($this->mockMysqliDb);
        $this->mockDb->setMockData([
            ['ordinal' => 5, 'cy' => 2]
        ]);

        $result = $repository->getPlayerForTradeValidation(1);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('ordinal', $result);
        $this->assertArrayHasKey('cy', $result);
    }

    // ============================================
    // GET ALL TEAMS TESTS
    // ============================================

    public function testGetAllTeamsReturnsEmptyArrayWhenNoTeams(): void
    {
        $repository = new TradingRepository($this->mockMysqliDb);
        $this->mockDb->setMockData([]);

        $result = $repository->getAllTeams();

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testGetAllTeamsReturnsTeamList(): void
    {
        $repository = new TradingRepository($this->mockMysqliDb);
        $this->mockDb->setMockData([
            ['team_name' => 'Team A'],
            ['team_name' => 'Team B'],
            ['team_name' => 'Team C'],
        ]);

        $result = $repository->getAllTeams();

        $this->assertIsArray($result);
        $this->assertCount(3, $result);
    }

    // ============================================
    // GET TRADE ROWS TESTS
    // ============================================

    public function testGetTradeRowsReturnsEmptyArrayWhenNoTrades(): void
    {
        $repository = new TradingRepository($this->mockMysqliDb);
        $this->mockDb->setMockData([]);

        $result = $repository->getTradeRows();

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    // ============================================
    // MULTIPLE INSTANCES TEST
    // ============================================

    public function testMultipleRepositoriesCanBeInstantiated(): void
    {
        $repo1 = new TradingRepository($this->mockMysqliDb);
        $repo2 = new TradingRepository($this->mockMysqliDb);
        
        $this->assertInstanceOf(TradingRepository::class, $repo1);
        $this->assertInstanceOf(TradingRepository::class, $repo2);
        $this->assertNotSame($repo1, $repo2);
    }
}
