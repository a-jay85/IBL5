<?php

declare(strict_types=1);


namespace Tests\RookieOption;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Player\Player;
use RookieOption\RookieOptionValidator;

/**
 * Tests for RookieOptionValidator
 */
#[AllowMockObjectsWithoutExpectations]
class RookieOptionValidatorTest extends TestCase
{
    private RookieOptionValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new RookieOptionValidator();
    }

    /**
     * Test validating player ownership - success case
     */
    public function testValidatePlayerOwnershipSuccess(): void
    {
        $mockPlayer = $this->createMock(Player::class);
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
    public function testValidatePlayerOwnershipFailure(): void
    {
        $mockPlayer = $this->createMock(Player::class);
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
    public function testValidateEligibilityNotEligible(): void
    {
        $mockPlayer = $this->createMock(Player::class);
        $mockPlayer->position = 'SF';
        $mockPlayer->name = 'Ineligible Player';
        $mockPlayer->method('canRookieOption')
            ->willReturn(false);
        $mockPlayer->method('getFinalYearRookieContractSalary')
            ->willReturn(0);

        $result = $this->validator->validateEligibilityAndGetSalary($mockPlayer, 'Regular Season');

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('not eligible', $result['error']);
    }

    /**
     * Test validating eligibility - first round pick eligible
     */
    public function testValidateEligibilityFirstRoundSuccess(): void
    {
        $mockPlayer = $this->createMock(Player::class);
        $mockPlayer->position = 'PF';
        $mockPlayer->name = 'Eligible Player';
        $mockPlayer->method('canRookieOption')
            ->willReturn(true);
        $mockPlayer->method('getFinalYearRookieContractSalary')
            ->willReturn(150);

        $result = $this->validator->validateEligibilityAndGetSalary($mockPlayer, 'Regular Season');

        $this->assertTrue($result['valid']);
        $this->assertArrayHasKey('finalYearSalary', $result);
        $this->assertSame(150, $result['finalYearSalary']);
    }

    /**
     * Test validating eligibility - second round pick eligible
     */
    public function testValidateEligibilitySecondRoundSuccess(): void
    {
        $mockPlayer = $this->createMock(Player::class);
        $mockPlayer->position = 'C';
        $mockPlayer->name = 'Second Round Player';
        $mockPlayer->method('canRookieOption')
            ->willReturn(true);
        $mockPlayer->method('getFinalYearRookieContractSalary')
            ->willReturn(100);

        $result = $this->validator->validateEligibilityAndGetSalary($mockPlayer, 'Regular Season');

        $this->assertTrue($result['valid']);
        $this->assertArrayHasKey('finalYearSalary', $result);
        $this->assertSame(100, $result['finalYearSalary']);
    }

    /**
     * Test validating eligibility - zero salary returns invalid
     */
    public function testValidateEligibilityZeroSalary(): void
    {
        $mockPlayer = $this->createMock(Player::class);
        $mockPlayer->position = 'PG';
        $mockPlayer->name = 'Zero Salary Player';
        $mockPlayer->method('canRookieOption')
            ->willReturn(true);
        $mockPlayer->method('getFinalYearRookieContractSalary')
            ->willReturn(0);

        $result = $this->validator->validateEligibilityAndGetSalary($mockPlayer, 'Regular Season');

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('error', $result);
    }
}
