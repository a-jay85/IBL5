<?php

declare(strict_types=1);

namespace Tests\Negotiation;

use PHPUnit\Framework\TestCase;
use League\League;
use Negotiation\NegotiationRepository;
use Repositories\Contracts\SalaryCapRepositoryInterface;
use Tests\WideUnit\Mocks\MockDatabase;

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
    private MockDatabase $mockDb;

    protected function setUp(): void
    {
        $this->mockDb = new MockDatabase();
        $GLOBALS['mysqli_db'] = $this->mockDb;
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['mysqli_db']);
    }

    // ============================================
    // CONSTRUCTOR TESTS
    // ============================================

    // ============================================
    // GET TEAM PERFORMANCE TESTS
    // ============================================

    public function testGetTeamPerformanceReturnsDefaultsWhenNoData(): void
    {
        $repository = new NegotiationRepository($this->mockDb, self::createStub(SalaryCapRepositoryInterface::class));
        $this->mockDb->setMockData([]);

        $result = $repository->getTeamPerformance('Test Team');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('contract_wins', $result);
        $this->assertArrayHasKey('contract_losses', $result);
        $this->assertSame(41, $result['contract_wins']);
        $this->assertSame(41, $result['contract_losses']);
    }

    public function testGetTeamPerformanceReturnsTeamData(): void
    {
        $repository = new NegotiationRepository($this->mockDb, self::createStub(SalaryCapRepositoryInterface::class));
        $this->mockDb->setMockData([
            [
                'contract_wins' => 50,
                'contract_losses' => 32,
                'contract_avg_w' => 45,
                'contract_avg_l' => 37
            ]
        ]);

        $result = $repository->getTeamPerformance('Test Team');

        $this->assertIsArray($result);
        $this->assertSame(50, $result['contract_wins']);
        $this->assertSame(32, $result['contract_losses']);
    }

    // ============================================
    // GET POSITION SALARY COMMITMENT TESTS
    // ============================================

    public function testGetPositionSalaryCommitmentReturnsZeroWhenNoPlayers(): void
    {
        $repository = new NegotiationRepository($this->mockDb, self::createStub(SalaryCapRepositoryInterface::class));
        $this->mockDb->setMockData([]);

        $result = $repository->getPositionSalaryCommitment('Test Team', 'G', 'Excluded Player');

        $this->assertIsInt($result);
        $this->assertSame(0, $result);
    }

    // ============================================
    // GET TEAM CAP SPACE NEXT SEASON TESTS
    // ============================================

    public function testGetTeamCapSpaceNextSeasonReturnsHardCapWhenNoSalaryData(): void
    {
        $commonRepo = self::createStub(SalaryCapRepositoryInterface::class);
        $commonRepo->method('getTeamCapSpaceNextSeason')->willReturn(League::HARD_CAP_MAX);
        $repository = new NegotiationRepository($this->mockDb, $commonRepo);

        $result = $repository->getTeamCapSpaceNextSeason('Empty Team');

        $this->assertSame(League::HARD_CAP_MAX, $result);
    }

    public function testGetTeamCapSpaceNextSeasonReturnsCapMinusSalary(): void
    {
        $commonRepo = self::createStub(SalaryCapRepositoryInterface::class);
        $commonRepo->method('getTeamCapSpaceNextSeason')->willReturn(League::HARD_CAP_MAX - 5000);
        $repository = new NegotiationRepository($this->mockDb, $commonRepo);

        $result = $repository->getTeamCapSpaceNextSeason('Test Team');

        $this->assertSame(League::HARD_CAP_MAX - 5000, $result);
    }

    // ============================================
    // MULTIPLE INSTANCES TEST
    // ============================================

    public function testMultipleRepositoriesCanBeInstantiated(): void
    {
        $commonRepo = self::createStub(SalaryCapRepositoryInterface::class);
        $repo1 = new NegotiationRepository($this->mockDb, $commonRepo);
        $repo2 = new NegotiationRepository($this->mockDb, $commonRepo);
        
        $this->assertNotSame($repo1, $repo2);
    }
}
