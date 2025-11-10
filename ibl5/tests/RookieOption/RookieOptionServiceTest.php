<?php

use PHPUnit\Framework\TestCase;
use RookieOption\RookieOptionService;

/**
 * Tests for RookieOptionService
 */
class RookieOptionServiceTest extends TestCase
{
    private $service;
    private $mockDb;
    
    protected function setUp(): void
    {
        $this->mockDb = new MockDatabase();
        $this->service = new RookieOptionService($this->mockDb);
    }
    
    /**
     * Test getting team name from username
     */
    public function testGetTeamNameFromUsername()
    {
        // Set up mock data for the user query
        $this->mockDb->setMockData([
            ['user_ibl_team' => 'Test Team']
        ]);
        
        $teamName = $this->service->getTeamNameFromUsername('testuser');
        
        $this->assertEquals('Test Team', $teamName);
    }
    
    /**
     * Test validating player ownership - success case
     */
    public function testValidatePlayerOwnershipSuccess()
    {
        // Set up mock data that includes both the team name and team ID
        // The mock will return this same data for both queries
        $this->mockDb->setMockData([
            [
                'user_ibl_team' => 'Test Team',
                'teamid' => 5
            ]
        ]);
        
        // Create a mock player
        $mockPlayer = new stdClass();
        $mockPlayer->teamID = 5;
        
        $result = $this->service->validatePlayerOwnership('testuser', $mockPlayer);
        
        $this->assertTrue($result);
    }
    
    /**
     * Test validating player ownership - failure case
     */
    public function testValidatePlayerOwnershipFailure()
    {
        // Set up mock data that includes both the team name and team ID
        $this->mockDb->setMockData([
            [
                'user_ibl_team' => 'Test Team',
                'teamid' => 5
            ]
        ]);
        
        // Create a mock player on a different team
        $mockPlayer = new stdClass();
        $mockPlayer->teamID = 7;
        
        $result = $this->service->validatePlayerOwnership('testuser', $mockPlayer);
        
        $this->assertFalse($result);
    }
    
    /**
     * Test calculating rookie option value
     */
    public function testCalculateRookieOptionValue()
    {
        // Rookie option should be 2x the final year salary
        $this->assertEquals(200, $this->service->calculateRookieOptionValue(100));
        $this->assertEquals(400, $this->service->calculateRookieOptionValue(200));
        $this->assertEquals(1000, $this->service->calculateRookieOptionValue(500));
    }
    
    /**
     * Test checking eligibility for ineligible player
     */
    public function testCheckEligibilityAndGetSalaryNotEligible()
    {
        // Create a player that is not eligible (canRookieOption returns false)
        $mockPlayer = $this->createMockPlayer(false, 1, 100, 150);
        
        $result = $this->service->checkEligibilityAndGetSalary($mockPlayer, 'Regular Season');
        
        $this->assertNull($result);
    }
    
    /**
     * Helper method to create a mock player
     */
    private function createMockPlayer(bool $canRookieOption, int $draftRound, int $cy2Salary, int $cy3Salary)
    {
        $mockPlayer = $this->getMockBuilder(\stdClass::class)
            ->addMethods(['canRookieOption'])
            ->getMock();
        
        $mockPlayer->method('canRookieOption')
            ->willReturn($canRookieOption);
        
        $mockPlayer->draftRound = $draftRound;
        $mockPlayer->contractYear2Salary = $cy2Salary;
        $mockPlayer->contractYear3Salary = $cy3Salary;
        
        return $mockPlayer;
    }
}
