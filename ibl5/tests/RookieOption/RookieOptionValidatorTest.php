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
        $mockPlayer = self::createStub(Player::class);
        $mockPlayer->method('getTeamName')->willReturn('Test Team');
        $mockPlayer->method('getPosition')->willReturn('PG');
        $mockPlayer->method('getName')->willReturn('Test Player');

        $result = $this->validator->validatePlayerOwnership($mockPlayer, 'Test Team');

        $this->assertTrue($result->isValid());
        $this->assertNull($result->getError());
    }

    /**
     * Test validating player ownership - failure case
     */
    public function testValidatePlayerOwnershipFailure(): void
    {
        $mockPlayer = self::createStub(Player::class);
        $mockPlayer->method('getTeamName')->willReturn('Other Team');
        $mockPlayer->method('getPosition')->willReturn('SG');
        $mockPlayer->method('getName')->willReturn('Other Player');

        $result = $this->validator->validatePlayerOwnership($mockPlayer, 'Test Team');

        $this->assertFalse($result->isValid());
        $this->assertNotNull($result->getError());
        $this->assertStringContainsString('SG Other Player', $result->getError());
        $this->assertStringContainsString('not on your team', $result->getError());
    }

    /**
     * Test validating eligibility - player not eligible
     */
    public function testValidateEligibilityNotEligible(): void
    {
        $mockPlayer = self::createStub(Player::class);
        $mockPlayer->method('getPosition')->willReturn('SF');
        $mockPlayer->method('getName')->willReturn('Ineligible Player');
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
        $mockPlayer = self::createStub(Player::class);
        $mockPlayer->method('getPosition')->willReturn('PF');
        $mockPlayer->method('getName')->willReturn('Eligible Player');
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
        $mockPlayer = self::createStub(Player::class);
        $mockPlayer->method('getPosition')->willReturn('C');
        $mockPlayer->method('getName')->willReturn('Second Round Player');
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
