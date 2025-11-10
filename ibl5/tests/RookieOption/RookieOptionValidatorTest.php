<?php

use PHPUnit\Framework\TestCase;
use RookieOption\RookieOptionValidator;

/**
 * Mock player class for testing
 */
class MockPlayerClass
{
    public int $draftRound;
    public int $contractYear2Salary;
    public int $contractYear3Salary;
    public string $teamName = '';
    public string $position = '';
    public string $name = '';
    
    public function canRookieOption(string $seasonPhase): bool
    {
        return false;
    }
    
    public function getFinalYearRookieContractSalary(): int
    {
        // First round picks have a 3-year contract (cy3 is final year)
        // Second round picks have a 2-year contract (cy2 is final year)
        return ($this->draftRound == 1) ? $this->contractYear3Salary : $this->contractYear2Salary;
    }
}

/**
 * Tests for RookieOptionValidator
 */
class RookieOptionValidatorTest extends TestCase
{
    private $validator;
    
    protected function setUp(): void
    {
        $this->validator = new RookieOptionValidator();
    }
    
    /**
     * Test validating player ownership - success case
     */
    public function testValidatePlayerOwnershipSuccess()
    {
        $mockPlayer = new stdClass();
        $mockPlayer->teamName = 'Test Team';
        $mockPlayer->position = 'PG';
        $mockPlayer->name = 'Test Player';
        
        $result = $this->validator->validatePlayerOwnership($mockPlayer, 'Test Team');
        
        $this->assertTrue($result['valid']);
        $this->assertArrayNotHasKey('error', $result);
    }
    
    /**
     * Test validating player ownership - failure case
     */
    public function testValidatePlayerOwnershipFailure()
    {
        $mockPlayer = new stdClass();
        $mockPlayer->teamName = 'Other Team';
        $mockPlayer->position = 'SG';
        $mockPlayer->name = 'Other Player';
        
        $result = $this->validator->validatePlayerOwnership($mockPlayer, 'Test Team');
        
        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('SG Other Player', $result['error']);
        $this->assertStringContainsString('not on your team', $result['error']);
    }
    
    /**
     * Test validating eligibility - player not eligible
     */
    public function testValidateEligibilityNotEligible()
    {
        $mockPlayer = $this->createMockPlayer(false, 1, 100, 150);
        $mockPlayer->position = 'SF';
        $mockPlayer->name = 'Ineligible Player';
        
        $result = $this->validator->validateEligibilityAndGetSalary($mockPlayer, 'Regular Season');
        
        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('not eligible', $result['error']);
    }
    
    /**
     * Test validating eligibility - first round pick eligible
     */
    public function testValidateEligibilityFirstRoundSuccess()
    {
        $mockPlayer = $this->createMockPlayer(true, 1, 100, 150);
        $mockPlayer->position = 'PF';
        $mockPlayer->name = 'Eligible Player';
        
        $result = $this->validator->validateEligibilityAndGetSalary($mockPlayer, 'Regular Season');
        
        $this->assertTrue($result['valid']);
        $this->assertArrayHasKey('finalYearSalary', $result);
        $this->assertEquals(150, $result['finalYearSalary']); // First round uses cy3
    }
    
    /**
     * Test validating eligibility - second round pick eligible
     */
    public function testValidateEligibilitySecondRoundSuccess()
    {
        $mockPlayer = $this->createMockPlayer(true, 2, 100, 150);
        $mockPlayer->position = 'C';
        $mockPlayer->name = 'Second Round Player';
        
        $result = $this->validator->validateEligibilityAndGetSalary($mockPlayer, 'Regular Season');
        
        $this->assertTrue($result['valid']);
        $this->assertArrayHasKey('finalYearSalary', $result);
        $this->assertEquals(100, $result['finalYearSalary']); // Second round uses cy2
    }
    
    /**
     * Test validating eligibility - zero salary returns invalid
     */
    public function testValidateEligibilityZeroSalary()
    {
        $mockPlayer = $this->createMockPlayer(true, 1, 0, 0);
        $mockPlayer->position = 'PG';
        $mockPlayer->name = 'Zero Salary Player';
        
        $result = $this->validator->validateEligibilityAndGetSalary($mockPlayer, 'Regular Season');
        
        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('error', $result);
    }
    
    /**
     * Helper method to create a mock player
     */
    private function createMockPlayer(bool $canRookieOption, int $draftRound, int $cy2Salary, int $cy3Salary)
    {
        $mockPlayer = $this->getMockBuilder(MockPlayerClass::class)
            ->onlyMethods(['canRookieOption'])
            ->getMock();
        
        $mockPlayer->method('canRookieOption')
            ->willReturn($canRookieOption);
        
        $mockPlayer->draftRound = $draftRound;
        $mockPlayer->contractYear2Salary = $cy2Salary;
        $mockPlayer->contractYear3Salary = $cy3Salary;
        
        return $mockPlayer;
    }
}
