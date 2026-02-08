<?php

declare(strict_types=1);

namespace Tests\CapSpace;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use CapSpace\CapSpaceService;
use CapSpace\Contracts\CapSpaceRepositoryInterface;

/**
 * Testable subclass that exposes protected methods for testing
 */
class TestableCapSpaceService extends CapSpaceService
{
    public function publicProcessTeamCapData(\Team $team, \Season $season): array
    {
        return $this->processTeamCapData($team, $season);
    }
}

/**
 * CapSpaceServiceTest - Tests for CapSpaceService business logic
 *
 * @covers \CapSpace\CapSpaceService
 */
#[AllowMockObjectsWithoutExpectations]
class CapSpaceServiceTest extends TestCase
{
    /** @var CapSpaceRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject */
    private CapSpaceRepositoryInterface $mockRepository;

    /** @var object&\PHPUnit\Framework\MockObject\MockObject */
    private object $mockDb;

    private TestableCapSpaceService $service;

    protected function setUp(): void
    {
        $this->mockRepository = $this->createMock(CapSpaceRepositoryInterface::class);
        $this->mockDb = $this->createMock(\mysqli::class);
        $this->service = new TestableCapSpaceService($this->mockRepository, $this->mockDb);
    }

    /**
     * Test that MLE/LLE integer values are correctly converted to booleans
     * 
     * Regression test for bug where $team->hasMLE === '1' always returned false
     * because database stores integers, not strings.
     */
    public function testMleAndLleFlagsAreCorrectlyConvertedFromIntegersToBoolean(): void
    {
        // Create a mock Team object with integer MLE/LLE values (as stored in database)
        $mockTeam = $this->createMockTeamWithMleLle(1, 1);
        
        $mockSeason = $this->createMockSeason();
        $this->mockRepository->method('getPlayersUnderContractAfterSeason')->willReturn([]);
        
        $result = $this->service->publicProcessTeamCapData($mockTeam, $mockSeason);
        
        // Verify boolean conversion works correctly with integer 1
        $this->assertIsBool($result['hasMLE'], 'hasMLE should be a boolean');
        $this->assertIsBool($result['hasLLE'], 'hasLLE should be a boolean');
        $this->assertTrue($result['hasMLE'], 'hasMLE should be true when team has MLE=1');
        $this->assertTrue($result['hasLLE'], 'hasLLE should be true when team has LLE=1');
    }

    public function testMleAndLleFlagsHandleIntegerZeroCorrectly(): void
    {
        $mockTeam = $this->createMockTeamWithMleLle(0, 0);
        
        $mockSeason = $this->createMockSeason();
        $this->mockRepository->method('getPlayersUnderContractAfterSeason')->willReturn([]);
        
        $result = $this->service->publicProcessTeamCapData($mockTeam, $mockSeason);
        
        $this->assertFalse($result['hasMLE'], 'hasMLE should be false when team has MLE=0');
        $this->assertFalse($result['hasLLE'], 'hasLLE should be false when team has LLE=0');
    }

    public function testMleAndLleFlagsHandleMixedStates(): void
    {
        $mockTeam = $this->createMockTeamWithMleLle(1, 0);
        
        $mockSeason = $this->createMockSeason();
        $this->mockRepository->method('getPlayersUnderContractAfterSeason')->willReturn([]);
        
        $result = $this->service->publicProcessTeamCapData($mockTeam, $mockSeason);
        
        $this->assertTrue($result['hasMLE'], 'hasMLE should be true when team has MLE=1');
        $this->assertFalse($result['hasLLE'], 'hasLLE should be false when team has LLE=0');
    }

    public function testGetDisplayYearsForRegularSeason(): void
    {
        $mockSeason = $this->createMockSeason('Regular Season', 2024, 2025);

        $result = $this->service->getDisplayYears($mockSeason);

        $this->assertEquals(2024, $result['beginningYear']);
        $this->assertEquals(2025, $result['endingYear']);
    }

    public function testGetDisplayYearsForFreeAgency(): void
    {
        $mockSeason = $this->createMockSeason('Free Agency', 2024, 2025);

        $result = $this->service->getDisplayYears($mockSeason);

        $this->assertEquals(2025, $result['beginningYear']);
        $this->assertEquals(2026, $result['endingYear']);
    }

    public function testGetDisplayYearsForPlayoffs(): void
    {
        $mockSeason = $this->createMockSeason('Playoffs', 2024, 2025);

        $result = $this->service->getDisplayYears($mockSeason);

        $this->assertEquals(2024, $result['beginningYear']);
        $this->assertEquals(2025, $result['endingYear']);
    }

    public function testFreeAgencySlotsCalculation(): void
    {
        $mockTeam = $this->createMockTeamWithMleLle(1, 1);
        
        $mockSeason = $this->createMockSeason();
        
        // Mock 5 players under contract (15 total slots - 5 = 10 FA slots)
        $contractedPlayers = array_fill(0, 5, ['cy' => 2024, 'cyt' => 2025]);
        $this->mockRepository->method('getPlayersUnderContractAfterSeason')->willReturn($contractedPlayers);
        
        $result = $this->service->publicProcessTeamCapData($mockTeam, $mockSeason);
        
        $this->assertEquals(10, $result['freeAgencySlots']);
    }

    public function testAvailableSalaryStructure(): void
    {
        $mockTeam = $this->createMockTeamWithMleLle(1, 1);
        
        $mockSeason = $this->createMockSeason();
        $this->mockRepository->method('getPlayersUnderContractAfterSeason')->willReturn([]);
        
        $result = $this->service->publicProcessTeamCapData($mockTeam, $mockSeason);
        
        // Should have availableSalary for 6 years
        $this->assertArrayHasKey('availableSalary', $result);
        $this->assertCount(6, $result['availableSalary']);
        $this->assertArrayHasKey('year1', $result['availableSalary']);
        $this->assertArrayHasKey('year6', $result['availableSalary']);
    }

    public function testPositionSalariesStructure(): void
    {
        $mockTeam = $this->createMockTeamWithMleLle(1, 1);
        
        $mockSeason = $this->createMockSeason();
        $this->mockRepository->method('getPlayersUnderContractAfterSeason')->willReturn([]);
        
        $result = $this->service->publicProcessTeamCapData($mockTeam, $mockSeason);
        
        $this->assertArrayHasKey('positionSalaries', $result);
        $positions = ['PG', 'SG', 'SF', 'PF', 'C'];
        foreach ($positions as $position) {
            $this->assertArrayHasKey($position, $result['positionSalaries']);
        }
    }

    /**
     * Create a mock Team object with specific MLE/LLE values
     *
     * @param int $hasMLE MLE flag (0 or 1)
     * @param int $hasLLE LLE flag (0 or 1)
     * @return \Team&\PHPUnit\Framework\MockObject\MockObject Mock Team
     */
    private function createMockTeamWithMleLle(int $hasMLE, int $hasLLE): \Team
    {
        $mockTeam = $this->createMock(\Team::class);
        $mockTeam->teamID = 1;
        $mockTeam->name = 'Test Team';
        $mockTeam->city = 'Test City';
        $mockTeam->color1 = '000000';
        $mockTeam->color2 = 'FFFFFF';
        $mockTeam->hasMLE = $hasMLE;
        $mockTeam->hasLLE = $hasLLE;
        
        // Mock the methods called by processTeamCapData
        $mockTeam->method('getSalaryCapArray')->willReturn([
            'year1' => 0,
            'year2' => 0,
            'year3' => 0,
            'year4' => 0,
            'year5' => 0,
            'year6' => 0,
        ]);
        
        $mockTeam->method('getPlayersUnderContractByPositionResult')->willReturn([]);
        $mockTeam->method('getTotalNextSeasonSalariesFromPlrResult')->willReturn(0);
        
        return $mockTeam;
    }

    /**
     * Create a mock Season object
     *
     * @param string $phase Season phase
     * @param int $beginningYear Starting year
     * @param int $endingYear Ending year
     * @return \Season&\PHPUnit\Framework\MockObject\MockObject Mock Season
     */
    private function createMockSeason(string $phase = 'Regular Season', int $beginningYear = 2024, int $endingYear = 2025): \Season
    {
        $mockSeason = $this->createMock(\Season::class);
        $mockSeason->phase = $phase;
        $mockSeason->beginningYear = $beginningYear;
        $mockSeason->endingYear = $endingYear;
        return $mockSeason;
    }
}
