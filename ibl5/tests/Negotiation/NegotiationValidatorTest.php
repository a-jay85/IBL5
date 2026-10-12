<?php

declare(strict_types=1);

namespace Tests\Negotiation;

use PHPUnit\Framework\Attributes\DataProvider;
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
    private ?NegotiationValidator $validator;
    private \Season\Season $mockSeason;

    protected function setUp(): void
    {
        $this->mockDb = new MockDatabase();
        $this->mockSeason = self::createStub(\Season\Season::class);
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
    public function testRejectsPlayerNotOnUsersTeam(): void
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
    public function testAcceptsPlayerOnUsersTeamWhenEligible(): void
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
    public function testRejectsPlayerNotEligibleForRenegotiation(): void
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
    public function testAcceptsPlayerInLastContractYear(): void
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
    public function testAcceptsPlayerWithNoNextYearSalary(): void
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
    public function testRejectsRookieOptionedPlayerInOptionYear(): void
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
    public function testRejectsDuringFreeAgency(): void
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
    public function testAcceptsWhenFreeAgencyNotActive(): void
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
    public function testAcceptsWhenFreeAgencyModuleNotFound(): void
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
     * @return array<string, array{array<string, int>, bool}>
     */
    public static function nullContractFieldProvider(): array
    {
        return [
            'contractCurrentYear null reads year 1 salary' => [['getContractYear2Salary' => 500], true],
            'year 1 salary null counts as zero' => [[], true],
            'year 2 salary null counts as zero' => [['getContractCurrentYear' => 1], true],
            'year 3 salary null counts as zero' => [['getContractCurrentYear' => 2], true],
            'year 4 salary null counts as zero' => [['getContractCurrentYear' => 3], true],
            'year 5 salary null counts as zero' => [['getContractCurrentYear' => 4], true],
            'year 6 salary null counts as zero' => [['getContractCurrentYear' => 5], true],
            'draftRound null is not a first-round option year' => [
                ['getContractCurrentYear' => 4, 'getYearsOfExperience' => 4, 'getContractYear3Salary' => 369, 'getContractYear4Salary' => 738],
                true,
            ],
            'yearsOfExperience null is not option-year experience' => [
                ['getContractCurrentYear' => 4, 'getDraftRound' => 1, 'getContractYear3Salary' => 369, 'getContractYear4Salary' => 738],
                true,
            ],
            'control: next-year salary present is ineligible' => [['getContractCurrentYear' => 1, 'getContractYear2Salary' => 500], false],
        ];
    }

    /**
     * @param array<string, int> $getterValues
     */
    #[DataProvider('nullContractFieldProvider')]
    public function testNullContractFieldCoalescesToZero(array $getterValues, bool $expectedValid): void
    {
        $player = self::createStub(Player::class);
        foreach ([
            'getContractCurrentYear',
            'getContractYear1Salary',
            'getContractYear2Salary',
            'getContractYear3Salary',
            'getContractYear4Salary',
            'getContractYear5Salary',
            'getContractYear6Salary',
            'getDraftRound',
            'getYearsOfExperience',
        ] as $getter) {
            $player->method($getter)->willReturn($getterValues[$getter] ?? null);
        }

        $this->assertSame($expectedValid, $this->validator->validateRenegotiationEligibility($player)->isValid());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function nonFreeAgencyPhaseProvider(): array
    {
        return [
            'Preseason' => ['Preseason'],
            'HEAT' => ['HEAT'],
            'Draft' => ['Draft'],
            'Playoffs' => ['Playoffs'],
        ];
    }

    #[DataProvider('nonFreeAgencyPhaseProvider')]
    public function testFreeAgencyCheckPassesInEveryOtherPhase(string $phase): void
    {
        $this->mockSeason->phase = $phase;

        $this->assertTrue($this->validator->validateFreeAgencyNotActive()->isValid());
    }

    public function testIneligibleContractFailureMessageIsExactForBothEntryPoints(): void
    {
        $player = Player::withPlrRow($this->mockDb, TestDataFactory::createPlayer([
            'name' => 'Ineligible Player',
            'teamname' => 'Seattle Supersonics',
            'cy' => 2,
            'salary_yr1' => 0,
            'salary_yr2' => 0,
            'salary_yr3' => 1000,
            'salary_yr4' => 0,
            'salary_yr5' => 0,
            'salary_yr6' => 0,
            'draftround' => 1,
            'exp' => 5,
        ]));
        $expected = 'Sorry, this player is not eligible for a contract extension at this time.';

        $this->assertSame($expected, $this->validator->validateNegotiationEligibility($player, 'Seattle Supersonics')->getError());
        $this->assertSame($expected, $this->validator->validateRenegotiationEligibility($player)->getError());
    }

    public function testFreeAgencyFailureMessageIsExact(): void
    {
        $this->mockSeason->phase = 'Free Agency';

        $this->assertSame(
            'Sorry, the contract extension feature is not available during free agency.',
            $this->validator->validateFreeAgencyNotActive()->getError()
        );
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
