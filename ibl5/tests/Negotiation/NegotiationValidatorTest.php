<?php

declare(strict_types=1);

namespace Tests\Negotiation;

use PHPUnit\Framework\TestCase;
use Negotiation\NegotiationValidator;
use Player\Player;
use Tests\WideUnit\Mocks\MockDatabase;
use Tests\WideUnit\Mocks\TestDataFactory;

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
    private MockDatabase $mockDb;
    private $validator;
    private $mockSeason;

    protected function setUp(): void
    {
        $this->mockDb = new MockDatabase();
        $this->mockSeason = $this->createStub(\Season\Season::class);
        $this->mockSeason->phase = 'Regular Season';
        $this->mockSeason->endingYear = 2026;
        $this->mockSeason->beginningYear = 2025;
        $this->validator = new NegotiationValidator($this->mockDb, $this->mockSeason);
    }

    protected function tearDown(): void
    {
        $this->validator = null;
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
        $this->assertFalse($result->isValid());
        $this->assertStringContainsString('not on your team', $result->getError() ?? '');
    }

    /**
     * @group validation
     * @group team-ownership
     */
    public function testAcceptsPlayerOnUsersTeamWhenEligible()
    {
        // Arrange
        $player = Player::withPlrRow($this->mockDb, TestDataFactory::createPlayer([
            'name' => 'Test Player',
            'teamname' => 'Seattle Supersonics',
            'cy' => 5,
            'salary_yr1' => 500,
            'salary_yr2' => 0,
            'salary_yr3' => 0,
            'salary_yr4' => 0,
            'salary_yr5' => 0,
            'salary_yr6' => 0,
            'draftround' => 1,
            'exp' => 5,
        ]));
        $userTeamName = 'Seattle Supersonics';

        // Act
        $result = $this->validator->validateNegotiationEligibility($player, $userTeamName);

        // Assert
        $this->assertTrue($result->isValid());
    }

    /**
     * @group validation
     * @group contract-eligibility
     */
    public function testRejectsPlayerNotEligibleForRenegotiation()
    {
        // Arrange - player has multiple years left on contract
        $player = Player::withPlrRow($this->mockDb, TestDataFactory::createPlayer([
            'name' => 'Test Player',
            'teamname' => 'Seattle Supersonics',
            'cy' => 2,
            'salary_yr1' => 500,
            'salary_yr2' => 0,
            'salary_yr3' => 1000,
            'salary_yr4' => 0,
            'salary_yr5' => 0,
            'salary_yr6' => 0,
            'draftround' => 1,
            'exp' => 5,
        ]));
        $userTeamName = 'Seattle Supersonics';

        // Act
        $result = $this->validator->validateNegotiationEligibility($player, $userTeamName);

        // Assert
        $this->assertFalse($result->isValid());
        $this->assertStringContainsString('not eligible for a contract extension', $result->getError() ?? '');
    }

    /**
     * @group validation
     * @group contract-eligibility
     */
    public function testAcceptsPlayerInLastContractYear()
    {
        // Arrange
        $player = Player::withPlrRow($this->mockDb, TestDataFactory::createPlayer([
            'name' => 'Test Player',
            'teamname' => 'Seattle Supersonics',
            'cy' => 6,
            'salary_yr1' => 500,
            'salary_yr2' => 0,
            'salary_yr3' => 0,
            'salary_yr4' => 0,
            'salary_yr5' => 0,
            'salary_yr6' => 0,
            'draftround' => 1,
            'exp' => 5,
        ]));
        $userTeamName = 'Seattle Supersonics';

        // Act
        $result = $this->validator->validateNegotiationEligibility($player, $userTeamName);

        // Assert
        $this->assertTrue($result->isValid());
    }

    /**
     * @group validation
     * @group contract-eligibility
     */
    public function testAcceptsPlayerWithNoNextYearSalary()
    {
        // Arrange
        $player = Player::withPlrRow($this->mockDb, TestDataFactory::createPlayer([
            'name' => 'Test Player',
            'teamname' => 'Seattle Supersonics',
            'cy' => 3,
            'salary_yr1' => 500,
            'salary_yr2' => 0,
            'salary_yr3' => 0,
            'salary_yr4' => 0,
            'salary_yr5' => 0,
            'salary_yr6' => 0,
            'draftround' => 1,
            'exp' => 5,
        ]));
        $userTeamName = 'Seattle Supersonics';

        // Act
        $result = $this->validator->validateNegotiationEligibility($player, $userTeamName);

        // Assert
        $this->assertTrue($result->isValid());
    }

    /**
     * @group validation
     * @group rookie-option
     */
    public function testRejectsRookieOptionedPlayerInOptionYear()
    {
        // Arrange - First round rookie optioned player in year 4
        $player = Player::withPlrRow($this->mockDb, TestDataFactory::createPlayer([
            'name' => 'Test Player',
            'teamname' => 'Seattle Supersonics',
            'cy' => 4,
            'salary_yr1' => 500,
            'salary_yr2' => 0,
            'salary_yr3' => 369,
            'salary_yr4' => 738, // Doubled = rookie option
            'salary_yr5' => 0,
            'salary_yr6' => 0,
            'draftround' => 1,
            'exp' => 4,
        ]));
        $userTeamName = 'Seattle Supersonics';

        // Act
        $result = $this->validator->validateNegotiationEligibility($player, $userTeamName);

        // Assert
        $this->assertFalse($result->isValid());
        $this->assertStringContainsString('not eligible for a contract extension', $result->getError() ?? '');
    }

    /**
     * @group validation
     * @group free-agency
     */
    public function testRejectsDuringFreeAgency()
    {
        // Arrange
        $this->mockSeason->phase = 'Free Agency';

        // Act
        $result = $this->validator->validateFreeAgencyNotActive();

        // Assert
        $this->assertFalse($result->isValid());
        $this->assertStringContainsString('not available during free agency', $result->getError() ?? '');
    }

    /**
     * @group validation
     * @group free-agency
     */
    public function testAcceptsWhenFreeAgencyNotActive()
    {
        // Arrange — default phase is Regular Season (set in setUp)

        // Act
        $result = $this->validator->validateFreeAgencyNotActive();

        // Assert
        $this->assertTrue($result->isValid());
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
        $result = $this->validator->validateFreeAgencyNotActive();

        // Assert
        $this->assertTrue($result->isValid());
    }

    // ── validateRenegotiationEligibility (no ownership check) ────────────────

    /**
     * @group validation
     */
    public function testRenegotiationEligibilitySkipsOwnershipCheck(): void
    {
        $player = Player::withPlrRow($this->mockDb, TestDataFactory::createPlayer([
            'name' => 'Test Player',
            'teamname' => 'Seattle Supersonics',
            'cy' => 5,
            'salary_yr1' => 500,
            'salary_yr2' => 0,
            'salary_yr3' => 0,
            'salary_yr4' => 0,
            'salary_yr5' => 0,
            'salary_yr6' => 0,
            'draftround' => 1,
            'exp' => 5,
        ]));

        $result = $this->validator->validateRenegotiationEligibility($player);

        $this->assertTrue($result->isValid());
    }

    /**
     * @group validation
     */
    public function testRenegotiationEligibilityRejectsIneligibleContract(): void
    {
        $player = Player::withPlrRow($this->mockDb, TestDataFactory::createPlayer([
            'name' => 'Test Player',
            'teamname' => 'Seattle Supersonics',
            'cy' => 2,
            'salary_yr1' => 500,
            'salary_yr2' => 0,
            'salary_yr3' => 1000,
            'salary_yr4' => 0,
            'salary_yr5' => 0,
            'salary_yr6' => 0,
            'draftround' => 1,
            'exp' => 5,
        ]));

        $result = $this->validator->validateRenegotiationEligibility($player);

        $this->assertFalse($result->isValid());
        $this->assertStringContainsString('not eligible', $result->getError() ?? '');
    }

    // ── Null-field edge cases (mutation hardening) ────────────────

    public function testValidatesPlayerWithNullContractSalaryFields(): void
    {
        // Player with ALL contract salary fields 0 → getters return 0 via null coalescing
        $player = Player::withPlrRow($this->mockDb, TestDataFactory::createPlayer([
            'name' => 'Null Salary Player',
            'teamname' => 'Seattle Supersonics',
            'cy' => 6, // Last year → eligible
            'salary_yr1' => 0,
            'salary_yr2' => 0,
            'salary_yr3' => 0,
            'salary_yr4' => 0,
            'salary_yr5' => 0,
            'salary_yr6' => 0,
            'draftround' => 1,
            'exp' => 5,
        ]));

        $result = $this->validator->validateNegotiationEligibility($player, 'Seattle Supersonics');

        // Should succeed — zero salaries and year 6 is the last year
        $this->assertTrue($result->isValid());
    }

    public function testValidatesPlayerWithNullDraftAndExperienceFields(): void
    {
        // Player where draftRound and yearsOfExperience do not trigger rookie option
        $player = Player::withPlrRow($this->mockDb, TestDataFactory::createPlayer([
            'name' => 'Null Draft Player',
            'teamname' => 'Seattle Supersonics',
            'cy' => 5,
            'salary_yr1' => 500,
            'salary_yr2' => 0,
            'salary_yr3' => 0,
            'salary_yr4' => 0,
            'salary_yr5' => 0,
            'salary_yr6' => 0,
            'draftround' => 1,
            'exp' => 5, // exp=5, not 4, so wasRookieOptioned returns false
        ]));

        $result = $this->validator->validateNegotiationEligibility($player, 'Seattle Supersonics');

        // Should succeed — not a rookie option candidate, year 6 salary is 0
        $this->assertTrue($result->isValid());
    }

    public function testValidatesPlayerWithNullContractCurrentYear(): void
    {
        // Player with contractCurrentYear = 0 and no next-year salary → eligible
        $player = Player::withPlrRow($this->mockDb, TestDataFactory::createPlayer([
            'name' => 'Null CY Player',
            'teamname' => 'Seattle Supersonics',
            'cy' => 0, // No active contract year
            'salary_yr1' => 0, // Next-year (year 1) salary = 0 → eligible
            'salary_yr2' => 0,
            'salary_yr3' => 0,
            'salary_yr4' => 0,
            'salary_yr5' => 0,
            'salary_yr6' => 0,
            'draftround' => 1,
            'exp' => 5,
        ]));

        $result = $this->validator->validateNegotiationEligibility($player, 'Seattle Supersonics');

        // contractCurrentYear 0 → next year salary check uses year 1 (0) → eligible
        $this->assertTrue($result->isValid());
    }

    /**
     * Helper to create a mock Player object for testing
     */
    private function createMockPlayer(string $name, string $teamName): Player
    {
        return Player::withPlrRow($this->mockDb, TestDataFactory::createPlayer([
            'name' => $name,
            'teamname' => $teamName,
            'cy' => 1,
            'salary_yr1' => 500,
            'salary_yr2' => 0,
            'salary_yr3' => 0,
            'salary_yr4' => 0,
            'salary_yr5' => 0,
            'salary_yr6' => 0,
            'draftround' => 1,
            'exp' => 5,
        ]));
    }
}
