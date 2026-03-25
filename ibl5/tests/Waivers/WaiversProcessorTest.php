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
        $repoStub = $this->createStub(WaiversRepositoryInterface::class);
        $commonRepoStub = $this->createStub(\Services\CommonMysqliRepository::class);
        $validatorStub = $this->createStub(WaiversValidatorInterface::class);
        $newsServiceStub = $this->createStub(\Services\NewsService::class);
        $dbStub = $this->createStub(\mysqli::class);

        $this->processor = new WaiversProcessor(
            $repoStub,
            $commonRepoStub,
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
     * Maps array keys to Player properties:
     * - 'cy' => contractCurrentYear
     * - 'cyt' => contractTotalYears  
     * - 'cy1' => contractYear1Salary
     * - 'cy2' => contractYear2Salary
     * - 'cy3' => contractYear3Salary
     * - 'cy4' => contractYear4Salary
     * - 'cy5' => contractYear5Salary
     * - 'cy6' => contractYear6Salary
     * - 'exp' => yearsOfExperience
     */
    private function createMockPlayer(array $properties): Player
    {
        $player = $this->getMockBuilder(Player::class)
            ->disableOriginalConstructor()
            ->getMock();
        
        // Map database array keys to Player property names
        $propertyMap = [
            'cy' => 'contractCurrentYear',
            'cyt' => 'contractTotalYears',
            'cy1' => 'contractYear1Salary',
            'cy2' => 'contractYear2Salary',
            'cy3' => 'contractYear3Salary',
            'cy4' => 'contractYear4Salary',
            'cy5' => 'contractYear5Salary',
            'cy6' => 'contractYear6Salary',
            'exp' => 'yearsOfExperience',
        ];
        
        foreach ($properties as $key => $value) {
            $propertyName = $propertyMap[$key] ?? $key;
            $player->{$propertyName} = $value;
        }
        
        return $player;
    }
    
    public function testCalculateVeteranMinimumSalaryFor10PlusYears()
    {
        $salary = $this->processor->calculateVeteranMinimumSalary(10);
        $this->assertEquals(103, $salary);
        
        $salary = $this->processor->calculateVeteranMinimumSalary(15);
        $this->assertEquals(103, $salary);
    }
    
    public function testCalculateVeteranMinimumSalaryFor9Years()
    {
        $salary = $this->processor->calculateVeteranMinimumSalary(9);
        $this->assertEquals(100, $salary);
    }
    
    public function testCalculateVeteranMinimumSalaryFor8Years()
    {
        $salary = $this->processor->calculateVeteranMinimumSalary(8);
        $this->assertEquals(89, $salary);
    }
    
    public function testCalculateVeteranMinimumSalaryFor7Years()
    {
        $salary = $this->processor->calculateVeteranMinimumSalary(7);
        $this->assertEquals(82, $salary);
    }
    
    public function testCalculateVeteranMinimumSalaryFor6Years()
    {
        $salary = $this->processor->calculateVeteranMinimumSalary(6);
        $this->assertEquals(76, $salary);
    }
    
    public function testCalculateVeteranMinimumSalaryFor5Years()
    {
        $salary = $this->processor->calculateVeteranMinimumSalary(5);
        $this->assertEquals(70, $salary);
    }
    
    public function testCalculateVeteranMinimumSalaryFor4Years()
    {
        $salary = $this->processor->calculateVeteranMinimumSalary(4);
        $this->assertEquals(64, $salary);
    }
    
    public function testCalculateVeteranMinimumSalaryFor3Years()
    {
        $salary = $this->processor->calculateVeteranMinimumSalary(3);
        $this->assertEquals(61, $salary);
    }
    
    public function testCalculateVeteranMinimumSalaryForRookies()
    {
        $salary = $this->processor->calculateVeteranMinimumSalary(0);
        $this->assertEquals(35, $salary);
        
        $salary = $this->processor->calculateVeteranMinimumSalary(1);
        $this->assertEquals(35, $salary);
        
        $salary = $this->processor->calculateVeteranMinimumSalary(2);
        $this->assertEquals(51, $salary);
    }
    
    public function testGetPlayerContractDisplayWithNoSalary()
    {
        $player = $this->createMockPlayer([
            'cy1' => 0,
            'exp' => 5
        ]);
        
        $contract = $this->processor->getPlayerContractDisplay($player, $this->mockSeasonRegular);
        $this->assertEquals('70', $contract);
    }
    
    public function testGetPlayerContractDisplayWithExistingContract()
    {
        $player = $this->createMockPlayer([
            'cy1' => 500,
            'cy' => 1,
            'cyt' => 3,
            'cy2' => 550,
            'cy3' => 600
        ]);
        
        $contract = $this->processor->getPlayerContractDisplay($player, $this->mockSeasonRegular);
        $this->assertEquals('500 550 600', $contract);
    }
    
    public function testGetPlayerContractDisplayWithPartialContract()
    {
        $player = $this->createMockPlayer([
            'cy1' => 500,
            'cy' => 2,
            'cyt' => 3,
            'cy2' => 550,
            'cy3' => 600
        ]);
        
        $contract = $this->processor->getPlayerContractDisplay($player, $this->mockSeasonRegular);
        $this->assertEquals('550 600', $contract);
    }
    
    public function testGetPlayerContractDisplayWithOneYearRemaining()
    {
        $player = $this->createMockPlayer([
            'cy1' => 500,
            'cy' => 3,
            'cyt' => 3,
            'cy3' => 600
        ]);
        
        $contract = $this->processor->getPlayerContractDisplay($player, $this->mockSeasonRegular);
        $this->assertEquals('600', $contract);
    }
    
    public function testGetWaiverWaitTimeReturnsEmptyWhenCleared()
    {
        $dropTime = time() - 90000; // More than 24 hours ago
        $currentTime = time();
        
        $waitTime = $this->processor->getWaiverWaitTime($dropTime, $currentTime);
        $this->assertEquals('', $waitTime);
    }
    
    public function testGetWaiverWaitTimeCalculatesRemainingTime()
    {
        $currentTime = time();
        $dropTime = $currentTime - 3600; // 1 hour ago
        
        $waitTime = $this->processor->getWaiverWaitTime($dropTime, $currentTime);
        $this->assertStringContainsString('Clears in', $waitTime);
        $this->assertStringContainsString('23 h', $waitTime); // Should be 23 hours remaining
    }
    
    public function testGetWaiverWaitTimeWithMinutes()
    {
        $currentTime = time();
        $dropTime = $currentTime - 82800; // 23 hours ago
        
        $waitTime = $this->processor->getWaiverWaitTime($dropTime, $currentTime);
        $this->assertStringContainsString('Clears in', $waitTime);
        $this->assertStringContainsString('1 h', $waitTime); // Should be 1 hour remaining
    }
    
    public function testDetermineContractDataForNewContract()
    {
        $playerData = [
            'cy1' => 0,
            'exp' => 8
        ];
        
        $contractData = $this->processor->determineContractData($playerData, $this->mockSeasonRegular);
        
        $this->assertFalse($contractData['hasExistingContract']);
        $this->assertEquals(89, $contractData['salary']);
    }
    
    public function testDetermineContractDataForExistingContract()
    {
        $playerData = [
            'cy1' => 500,
            'cy' => 1,
            'cyt' => 3,
            'cy2' => 550,
            'cy3' => 600
        ];
        
        $contractData = $this->processor->determineContractData($playerData, $this->mockSeasonRegular);
        
        $this->assertTrue($contractData['hasExistingContract']);
        $this->assertEquals(500, $contractData['salary']);
    }
    
    public function testDetermineContractDataForMidContract()
    {
        $playerData = [
            'cy1' => 500,
            'cy' => 2,
            'cyt' => 3,
            'cy2' => 550,
            'cy3' => 600
        ];
        
        $contractData = $this->processor->determineContractData($playerData, $this->mockSeasonRegular);
        
        $this->assertTrue($contractData['hasExistingContract']);
        $this->assertEquals(550, $contractData['salary']);
    }
    
    public function testGetPlayerContractDisplayWithMissingExperience()
    {
        $player = $this->createMockPlayer([
            'cy1' => 0,
            'exp' => 0
        ]);
        
        $contract = $this->processor->getPlayerContractDisplay($player, $this->mockSeasonRegular);
        // With rookie experience (0), should return vet min for 0 experience = 35 (first year minimum)
        $this->assertEquals('35', $contract);
    }
    
    public function testGetPlayerContractDisplayWithEmptyContract()
    {
        $player = $this->createMockPlayer([
            'cy1' => 0,
            'cy' => 1,
            'cyt' => 1,
            'exp' => 0
        ]);
        
        $contract = $this->processor->getPlayerContractDisplay($player, $this->mockSeasonRegular);
        $this->assertEquals('35', $contract); // Should use vet min calculation for rookie (first year minimum)
    }
    
    public function testDetermineContractDataForNewContractDuringFreeAgency()
    {
        $playerData = [
            'cy1' => 0,
            'cy2' => 0,
            'exp' => 6
        ];
        
        $contractData = $this->processor->determineContractData($playerData, $this->mockSeasonFreeAgency);
        
        $this->assertFalse($contractData['hasExistingContract']);
        $this->assertEquals(82, $contractData['salary']);
    }
    
    public function testGetPlayerContractDisplayDuringFreeAgency()
    {
        $player = $this->createMockPlayer([
            'cy1' => 0,
            'cy2' => 0,
            'exp' => 4
        ]);
        
        $contract = $this->processor->getPlayerContractDisplay($player, $this->mockSeasonFreeAgency);
        $this->assertEquals('70', $contract);
    }
    
    public function testGetPlayerContractDisplayWithExistingContractDuringFreeAgency()
    {
        $player = $this->createMockPlayer([
            'cy1' => 0,
            'cy2' => 500,
            'cy' => 2,
            'cyt' => 4,
            'cy3' => 550,
            'cy4' => 600
        ]);

        $contract = $this->processor->getPlayerContractDisplay($player, $this->mockSeasonFreeAgency);
        $this->assertEquals('500 550 600', $contract);
    }

    // ── Mutation hardening: null experience paths ────────────

    public function testGetPlayerContractDisplayWithNullExperience(): void
    {
        $player = $this->createMockPlayer([
            'cy1' => 0,
            // 'exp' not set → yearsOfExperience is null → defaults to 0
        ]);

        $contract = $this->processor->getPlayerContractDisplay($player, $this->mockSeasonRegular);
        // Null experience defaults to 0 (rookie) → vet min for rookies
        $this->assertEquals('35', $contract);
    }

    public function testGetPlayerContractDisplayDuringFreeAgencyWithNullExperience(): void
    {
        $player = $this->createMockPlayer([
            'cy1' => 0,
            // 'exp' not set → offseason path: (null ?? 0) + 1 = 1 year experience
        ]);

        $contract = $this->processor->getPlayerContractDisplay($player, $this->mockSeasonFreeAgency);
        // Offseason bumps experience by 1: 0 + 1 = 1 → vet min for 1 year
        $this->assertEquals('35', $contract);
    }

}
