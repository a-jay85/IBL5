<?php

declare(strict_types=1);

namespace Tests\Negotiation;

use PHPUnit\Framework\TestCase;
use Negotiation\NegotiationRepository;

/**
 * NegotiationRepositoryTest - Tests for NegotiationRepository database operations
 *
 * Tests:
 * - Repository instantiation
 * - Interface compliance
 * - Query execution via mock
 */
class NegotiationRepositoryTest extends TestCase
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
        $repository = new NegotiationRepository($this->mockMysqliDb);
        
        $this->assertInstanceOf(NegotiationRepository::class, $repository);
    }

    public function testRepositoryImplementsCorrectInterface(): void
    {
        $repository = new NegotiationRepository($this->mockMysqliDb);
        
        $this->assertInstanceOf(
            \Negotiation\Contracts\NegotiationRepositoryInterface::class,
            $repository
        );
    }

    public function testRepositoryExtendsBaseMysqliRepository(): void
    {
        $repository = new NegotiationRepository($this->mockMysqliDb);
        
        $this->assertInstanceOf(
            \BaseMysqliRepository::class,
            $repository
        );
    }

    // ============================================
    // GET TEAM PERFORMANCE TESTS
    // ============================================

    public function testGetTeamPerformanceReturnsDefaultsWhenNoData(): void
    {
        $repository = new NegotiationRepository($this->mockMysqliDb);
        $this->mockDb->setMockData([]);

        $result = $repository->getTeamPerformance('Test Team');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('Contract_Wins', $result);
        $this->assertArrayHasKey('Contract_Losses', $result);
        $this->assertEquals(41, $result['Contract_Wins']);
        $this->assertEquals(41, $result['Contract_Losses']);
    }

    public function testGetTeamPerformanceReturnsTeamData(): void
    {
        $repository = new NegotiationRepository($this->mockMysqliDb);
        $this->mockDb->setMockData([
            [
                'Contract_Wins' => 50,
                'Contract_Losses' => 32,
                'Contract_AvgW' => 45,
                'Contract_AvgL' => 37
            ]
        ]);

        $result = $repository->getTeamPerformance('Test Team');

        $this->assertIsArray($result);
        $this->assertEquals(50, $result['Contract_Wins']);
        $this->assertEquals(32, $result['Contract_Losses']);
    }

    // ============================================
    // GET POSITION SALARY COMMITMENT TESTS
    // ============================================

    public function testGetPositionSalaryCommitmentReturnsZeroWhenNoPlayers(): void
    {
        $repository = new NegotiationRepository($this->mockMysqliDb);
        $this->mockDb->setMockData([]);

        $result = $repository->getPositionSalaryCommitment('Test Team', 'G', 'Excluded Player');

        $this->assertIsInt($result);
        $this->assertEquals(0, $result);
    }

    // ============================================
    // MULTIPLE INSTANCES TEST
    // ============================================

    public function testMultipleRepositoriesCanBeInstantiated(): void
    {
        $repo1 = new NegotiationRepository($this->mockMysqliDb);
        $repo2 = new NegotiationRepository($this->mockMysqliDb);
        
        $this->assertInstanceOf(NegotiationRepository::class, $repo1);
        $this->assertInstanceOf(NegotiationRepository::class, $repo2);
        $this->assertNotSame($repo1, $repo2);
    }
}
