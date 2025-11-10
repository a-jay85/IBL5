<?php

use PHPUnit\Framework\TestCase;
use Negotiation\NegotiationValidator;
use Player\Player;

/**
 * Tests for NegotiationValidator
 * 
 * Validates contract negotiation eligibility rules:
 * - Player must be on user's team
 * - Player must be eligible for contract renegotiation (delegates to PlayerContractValidator)
 * - Free agency module must not be active
 */
class NegotiationValidatorTest extends TestCase
{
    private $mockDb;
    private $validator;

    protected function setUp(): void
    {
        $this->mockDb = new MockDatabase();
        $this->validator = new NegotiationValidator($this->mockDb);
    }

    protected function tearDown(): void
    {
        $this->validator = null;
        $this->mockDb = null;
    }

    /**
     * @group validation
     * @group team-ownership
     */
    public function testRejectsPlayerNotOnUsersTeam()
    {
        // Arrange
        $player = $this->createMockPlayer('Test Player', 'Seattle Supersonics');
        $userTeamName = 'Portland Trail Blazers';

        // Act
        $result = $this->validator->validateNegotiationEligibility($player, $userTeamName);

        // Assert
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('not on your team', $result['error']);
    }

    /**
     * @group validation
     * @group team-ownership
     */
    public function testAcceptsPlayerOnUsersTeamWhenEligible()
    {
        // Arrange
        $player = $this->createMockPlayer('Test Player', 'Seattle Supersonics');
        $player->contractCurrentYear = 5;
        $player->contractYear6Salary = 0; // Can renegotiate - in last year
        $userTeamName = 'Seattle Supersonics';

        // Act
        $result = $this->validator->validateNegotiationEligibility($player, $userTeamName);

        // Assert
        $this->assertTrue($result['valid']);
    }

    /**
     * @group validation
     * @group contract-eligibility
     */
    public function testRejectsPlayerNotEligibleForRenegotiation()
    {
        // Arrange - player has multiple years left on contract
        $player = $this->createMockPlayer('Test Player', 'Seattle Supersonics');
        $player->contractCurrentYear = 2;
        $player->contractYear3Salary = 1000; // Has next year salary, cannot renegotiate
        $userTeamName = 'Seattle Supersonics';

        // Act
        $result = $this->validator->validateNegotiationEligibility($player, $userTeamName);

        // Assert
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('not eligible for a contract extension', $result['error']);
    }

    /**
     * @group validation
     * @group contract-eligibility
     */
    public function testAcceptsPlayerInLastContractYear()
    {
        // Arrange
        $player = $this->createMockPlayer('Test Player', 'Seattle Supersonics');
        $player->contractCurrentYear = 6; // Last possible year
        $userTeamName = 'Seattle Supersonics';

        // Act
        $result = $this->validator->validateNegotiationEligibility($player, $userTeamName);

        // Assert
        $this->assertTrue($result['valid']);
    }

    /**
     * @group validation
     * @group contract-eligibility
     */
    public function testAcceptsPlayerWithNoNextYearSalary()
    {
        // Arrange
        $player = $this->createMockPlayer('Test Player', 'Seattle Supersonics');
        $player->contractCurrentYear = 3;
        $player->contractYear4Salary = 0; // No next year, can renegotiate
        $userTeamName = 'Seattle Supersonics';

        // Act
        $result = $this->validator->validateNegotiationEligibility($player, $userTeamName);

        // Assert
        $this->assertTrue($result['valid']);
    }

    /**
     * @group validation
     * @group rookie-option
     */
    public function testRejectsRookieOptionedPlayerInOptionYear()
    {
        // Arrange - First round rookie optioned player in year 4
        $player = $this->createMockPlayer('Test Player', 'Seattle Supersonics');
        $player->draftRound = 1;
        $player->yearsOfExperience = 4;
        $player->contractCurrentYear = 4;
        $player->contractYear3Salary = 369;
        $player->contractYear4Salary = 738; // Doubled = rookie option
        $player->contractYear5Salary = 0;
        $userTeamName = 'Seattle Supersonics';

        // Act
        $result = $this->validator->validateNegotiationEligibility($player, $userTeamName);

        // Assert
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('not eligible for a contract extension', $result['error']);
    }

    /**
     * @group validation
     * @group free-agency
     */
    public function testRejectsDuringFreeAgency()
    {
        // Arrange
        $this->mockDb->setMockData([
            ['active' => 1]
        ]);

        // Act
        $result = $this->validator->validateFreeAgencyNotActive('nuke');

        // Assert
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('not available during free agency', $result['error']);
    }

    /**
     * @group validation
     * @group free-agency
     */
    public function testAcceptsWhenFreeAgencyNotActive()
    {
        // Arrange
        $this->mockDb->setMockData([
            ['active' => 0]
        ]);

        // Act
        $result = $this->validator->validateFreeAgencyNotActive('nuke');

        // Assert
        $this->assertTrue($result['valid']);
    }

    /**
     * @group validation
     * @group free-agency
     */
    public function testAcceptsWhenFreeAgencyModuleNotFound()
    {
        // Arrange - no data returned (module doesn't exist)
        $this->mockDb->setMockData([]);

        // Act
        $result = $this->validator->validateFreeAgencyNotActive('nuke');

        // Assert
        $this->assertTrue($result['valid']);
    }

    /**
     * Helper to create a mock Player object for testing
     */
    private function createMockPlayer(string $name, string $teamName): Player
    {
        $player = new Player();
        $player->name = $name;
        $player->teamName = $teamName;
        $player->contractCurrentYear = 1;
        $player->contractYear1Salary = 500;
        $player->contractYear2Salary = 0;
        $player->contractYear3Salary = 0;
        $player->contractYear4Salary = 0;
        $player->contractYear5Salary = 0;
        $player->contractYear6Salary = 0;
        $player->draftRound = 1;
        $player->yearsOfExperience = 5;
        
        return $player;
    }
}
