<?php

declare(strict_types=1);

namespace Tests\Waivers;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Waivers\WaiversProcessor;
use Waivers\Contracts\WaiversRepositoryInterface;
use Waivers\Contracts\WaiversValidatorInterface;
use Player\Player;
use Season\Season;

#[AllowMockObjectsWithoutExpectations]
class WaiversProcessorTest extends TestCase
{
    private WaiversProcessor $processor;
    private Season $mockSeasonRegular;
    private Season $mockSeasonFreeAgency;

    protected function setUp(): void
    {
        $repoStub = self::createStub(WaiversRepositoryInterface::class);
        $teamIdentityRepoStub = self::createStub(\Repositories\Contracts\TeamIdentityRepositoryInterface::class);
        $playerLookupRepoStub = self::createStub(\Repositories\Contracts\PlayerLookupRepositoryInterface::class);
        $validatorStub = self::createStub(WaiversValidatorInterface::class);
        $newsServiceStub = self::createStub(\Topics\News\NewsRepository::class);
        $dbStub = self::createStub(\mysqli::class);

        $this->processor = new WaiversProcessor(
            $repoStub,
            $teamIdentityRepoStub,
            $playerLookupRepoStub,
            $validatorStub,
            $newsServiceStub,
            $dbStub
        );
        
        // Create mock Season for regular season
        $this->mockSeasonRegular = $this->createMock(Season::class);
        $this->mockSeasonRegular->phase = 'Regular Season';
        $this->mockSeasonRegular->method('isOffseasonPhase')->willReturn(false);

        // Create mock Season for free agency
        $this->mockSeasonFreeAgency = $this->createMock(Season::class);
        $this->mockSeasonFreeAgency->phase = 'Free Agency';
        $this->mockSeasonFreeAgency->method('isOffseasonPhase')->willReturn(true);
    }
    
    /**
     * Helper method to create a mock Player object with contract properties
     * Maps array keys to Player getter methods.
     *
     * @param array<string, int> $properties
     */
    private function createMockPlayer(array $properties): Player
    {
        $player = $this->getMockBuilder(Player::class)
            ->disableOriginalConstructor()
            ->getMock();

        $getterMap = [
            'cy' => 'getContractCurrentYear',
            'cyt' => 'getContractTotalYears',
            'salary_yr1' => 'getContractYear1Salary',
            'salary_yr2' => 'getContractYear2Salary',
            'salary_yr3' => 'getContractYear3Salary',
            'salary_yr4' => 'getContractYear4Salary',
            'salary_yr5' => 'getContractYear5Salary',
            'salary_yr6' => 'getContractYear6Salary',
            'exp' => 'getYearsOfExperience',
        ];

        foreach ($properties as $key => $value) {
            $getter = $getterMap[$key] ?? null;
            if ($getter !== null) {
                $player->method($getter)->willReturn($value);
            }
        }

        return $player;
    }
    
    public function testCalculateVeteranMinimumSalaryFor10PlusYears(): void
    {
        $salary = $this->processor->calculateVeteranMinimumSalary(10);
        $this->assertSame(103, $salary);
        
        $salary = $this->processor->calculateVeteranMinimumSalary(15);
        $this->assertSame(103, $salary);
    }
    
    public function testCalculateVeteranMinimumSalaryFor9Years(): void
    {
        $salary = $this->processor->calculateVeteranMinimumSalary(9);
        $this->assertSame(100, $salary);
    }
    
    public function testCalculateVeteranMinimumSalaryFor8Years(): void
    {
        $salary = $this->processor->calculateVeteranMinimumSalary(8);
        $this->assertSame(89, $salary);
    }
    
    public function testCalculateVeteranMinimumSalaryFor7Years(): void
    {
        $salary = $this->processor->calculateVeteranMinimumSalary(7);
        $this->assertSame(82, $salary);
    }
    
    public function testCalculateVeteranMinimumSalaryFor6Years(): void
    {
        $salary = $this->processor->calculateVeteranMinimumSalary(6);
        $this->assertSame(76, $salary);
    }
    
    public function testCalculateVeteranMinimumSalaryFor5Years(): void
    {
        $salary = $this->processor->calculateVeteranMinimumSalary(5);
        $this->assertSame(70, $salary);
    }
    
    public function testCalculateVeteranMinimumSalaryFor4Years(): void
    {
        $salary = $this->processor->calculateVeteranMinimumSalary(4);
        $this->assertSame(64, $salary);
    }
    
    public function testCalculateVeteranMinimumSalaryFor3Years(): void
    {
        $salary = $this->processor->calculateVeteranMinimumSalary(3);
        $this->assertSame(61, $salary);
    }
    
    public function testCalculateVeteranMinimumSalaryForRookies(): void
    {
        $salary = $this->processor->calculateVeteranMinimumSalary(0);
        $this->assertSame(35, $salary);
        
        $salary = $this->processor->calculateVeteranMinimumSalary(1);
        $this->assertSame(35, $salary);
        
        $salary = $this->processor->calculateVeteranMinimumSalary(2);
        $this->assertSame(51, $salary);
    }
    
    public function testGetPlayerContractDisplayWithNoSalary(): void
    {
        $player = $this->createMockPlayer([
            'salary_yr1' => 0,
            'exp' => 5
        ]);
        
        $contract = $this->processor->getPlayerContractDisplay($player, $this->mockSeasonRegular);
        $this->assertSame('70', $contract);
    }
    
    public function testGetPlayerContractDisplayWithExistingContract(): void
    {
        $player = $this->createMockPlayer([
            'salary_yr1' => 500,
            'cy' => 1,
            'cyt' => 3,
            'salary_yr2' => 550,
            'salary_yr3' => 600
        ]);
        
        $contract = $this->processor->getPlayerContractDisplay($player, $this->mockSeasonRegular);
        $this->assertSame('500 550 600', $contract);
    }
    
    public function testGetPlayerContractDisplayWithPartialContract(): void
    {
        $player = $this->createMockPlayer([
            'salary_yr1' => 500,
            'cy' => 2,
            'cyt' => 3,
            'salary_yr2' => 550,
            'salary_yr3' => 600
        ]);
        
        $contract = $this->processor->getPlayerContractDisplay($player, $this->mockSeasonRegular);
        $this->assertSame('550 600', $contract);
    }
    
    public function testGetPlayerContractDisplayWithOneYearRemaining(): void
    {
        $player = $this->createMockPlayer([
            'salary_yr1' => 500,
            'cy' => 3,
            'cyt' => 3,
            'salary_yr3' => 600
        ]);
        
        $contract = $this->processor->getPlayerContractDisplay($player, $this->mockSeasonRegular);
        $this->assertSame('600', $contract);
    }
    
    public function testGetWaiverWaitTimeReturnsEmptyWhenCleared(): void
    {
        $dropTime = time() - 90000; // More than 24 hours ago
        $currentTime = time();
        
        $waitTime = $this->processor->getWaiverWaitTime($dropTime, $currentTime);
        $this->assertSame('', $waitTime);
    }
    
    public function testGetWaiverWaitTimeCalculatesRemainingTime(): void
    {
        $currentTime = time();
        $dropTime = $currentTime - 3600; // 1 hour ago
        
        $waitTime = $this->processor->getWaiverWaitTime($dropTime, $currentTime);
        $this->assertStringContainsString('Clears in', $waitTime);
        $this->assertStringContainsString('23 h', $waitTime); // Should be 23 hours remaining
    }
    
    public function testGetWaiverWaitTimeWithMinutes(): void
    {
        $currentTime = time();
        $dropTime = $currentTime - 82800; // 23 hours ago
        
        $waitTime = $this->processor->getWaiverWaitTime($dropTime, $currentTime);
        $this->assertStringContainsString('Clears in', $waitTime);
        $this->assertStringContainsString('1 h', $waitTime); // Should be 1 hour remaining
    }
    
    // The $playerData arrays below are intentionally partial (salary_yr1/exp/cy/cyt only);
    // each omits keys that the tested code-path never reads, exercising specific defaulting
    // branches. The array{} shape mismatch for the missing keys is a documented baseline
    // defer, not a defect to fix by completing the arrays (that would obscure which keys
    // determineContractData actually requires for each path).
    public function testDetermineContractDataForNewContract(): void
    {
        $playerData = [
            'salary_yr1' => 0,
            'exp' => 8
        ];
        
        $contractData = $this->processor->determineContractData($playerData, $this->mockSeasonRegular);
        
        $this->assertFalse($contractData['hasExistingContract']);
        $this->assertSame(89, $contractData['salary']);
    }
    
    public function testDetermineContractDataForExistingContract(): void
    {
        $playerData = [
            'salary_yr1' => 500,
            'cy' => 1,
            'cyt' => 3,
            'salary_yr2' => 550,
            'salary_yr3' => 600
        ];
        
        $contractData = $this->processor->determineContractData($playerData, $this->mockSeasonRegular);
        
        $this->assertTrue($contractData['hasExistingContract']);
        $this->assertSame(500, $contractData['salary']);
    }
    
    public function testDetermineContractDataForMidContract(): void
    {
        $playerData = [
            'salary_yr1' => 500,
            'cy' => 2,
            'cyt' => 3,
            'salary_yr2' => 550,
            'salary_yr3' => 600
        ];
        
        $contractData = $this->processor->determineContractData($playerData, $this->mockSeasonRegular);
        
        $this->assertTrue($contractData['hasExistingContract']);
        $this->assertSame(550, $contractData['salary']);
    }
    
    public function testGetPlayerContractDisplayWithMissingExperience(): void
    {
        $player = $this->createMockPlayer([
            'salary_yr1' => 0,
            'exp' => 0
        ]);
        
        $contract = $this->processor->getPlayerContractDisplay($player, $this->mockSeasonRegular);
        // With rookie experience (0), should return vet min for 0 experience = 35 (first year minimum)
        $this->assertSame('35', $contract);
    }
    
    public function testGetPlayerContractDisplayWithEmptyContract(): void
    {
        $player = $this->createMockPlayer([
            'salary_yr1' => 0,
            'cy' => 1,
            'cyt' => 1,
            'exp' => 0
        ]);
        
        $contract = $this->processor->getPlayerContractDisplay($player, $this->mockSeasonRegular);
        $this->assertSame('35', $contract); // Should use vet min calculation for rookie (first year minimum)
    }
    
    public function testDetermineContractDataForNewContractDuringFreeAgency(): void
    {
        $playerData = [
            'salary_yr1' => 0,
            'salary_yr2' => 0,
            'exp' => 6
        ];
        
        $contractData = $this->processor->determineContractData($playerData, $this->mockSeasonFreeAgency);
        
        $this->assertFalse($contractData['hasExistingContract']);
        $this->assertSame(82, $contractData['salary']);
    }
    
    public function testGetPlayerContractDisplayDuringFreeAgency(): void
    {
        $player = $this->createMockPlayer([
            'salary_yr1' => 0,
            'salary_yr2' => 0,
            'exp' => 4
        ]);
        
        $contract = $this->processor->getPlayerContractDisplay($player, $this->mockSeasonFreeAgency);
        $this->assertSame('70', $contract);
    }
    
    public function testGetPlayerContractDisplayWithExistingContractDuringFreeAgency(): void
    {
        $player = $this->createMockPlayer([
            'salary_yr1' => 0,
            'salary_yr2' => 500,
            'cy' => 2,
            'cyt' => 4,
            'salary_yr3' => 550,
            'salary_yr4' => 600
        ]);

        $contract = $this->processor->getPlayerContractDisplay($player, $this->mockSeasonFreeAgency);
        $this->assertSame('500 550 600', $contract);
    }

    // ── Mutation hardening: null experience paths ────────────

    public function testGetPlayerContractDisplayWithNullExperience(): void
    {
        $player = $this->createMockPlayer([
            'salary_yr1' => 0,
            // 'exp' not set → yearsOfExperience is null → defaults to 0
        ]);

        $contract = $this->processor->getPlayerContractDisplay($player, $this->mockSeasonRegular);
        // Null experience defaults to 0 (rookie) → vet min for rookies
        $this->assertSame('35', $contract);
    }

    public function testGetPlayerContractDisplayDuringFreeAgencyWithNullExperience(): void
    {
        $player = $this->createMockPlayer([
            'salary_yr1' => 0,
            // 'exp' not set → offseason path: (null ?? 0) + 1 = 1 year experience
        ]);

        $contract = $this->processor->getPlayerContractDisplay($player, $this->mockSeasonFreeAgency);
        // Offseason bumps experience by 1: 0 + 1 = 1 → vet min for 1 year
        $this->assertSame('35', $contract);
    }

}
