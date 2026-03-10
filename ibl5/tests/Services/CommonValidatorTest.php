<?php

declare(strict_types=1);

namespace Tests\Services;

use PHPUnit\Framework\TestCase;
use Services\CommonValidator;

/**
 * CommonValidatorTest - Tests for CommonValidator static methods
 */
class CommonValidatorTest extends TestCase
{
    // ============================================
    // VALIDATE PLAYER OWNERSHIP TESTS
    // ============================================

    public function testValidatePlayerOwnershipReturnsValidWhenPlayerOnTeam(): void
    {
        $player = (object) [
            'teamName' => 'Test Team',
            'position' => 'PG',
            'name' => 'John Doe',
        ];

        $result = CommonValidator::validatePlayerOwnership($player, 'Test Team');

        $this->assertTrue($result->isValid());
    }

    public function testValidatePlayerOwnershipReturnsInvalidWhenPlayerOnDifferentTeam(): void
    {
        $player = (object) [
            'teamName' => 'Other Team',
            'position' => 'SG',
            'name' => 'Jane Doe',
        ];

        $result = CommonValidator::validatePlayerOwnership($player, 'Test Team');

        $this->assertFalse($result->isValid());
        $this->assertNotNull($result->getError());
    }

    public function testValidatePlayerOwnershipErrorIncludesPlayerInfo(): void
    {
        $player = (object) [
            'teamName' => 'Other Team',
            'position' => 'C',
            'name' => 'Big Center',
        ];

        $result = CommonValidator::validatePlayerOwnership($player, 'Test Team');

        $this->assertStringContainsString('C Big Center', $result->getError() ?? '');
        $this->assertStringContainsString('not on your team', $result->getError() ?? '');
    }

    public function testValidatePlayerOwnershipHandlesMissingPositionAndName(): void
    {
        $player = (object) [
            'teamName' => 'Other Team',
        ];

        $result = CommonValidator::validatePlayerOwnership($player, 'Test Team');

        $this->assertFalse($result->isValid());
        $this->assertStringContainsString('This player', $result->getError() ?? '');
    }

    public function testValidatePlayerOwnershipIsCaseSensitive(): void
    {
        $player = (object) [
            'teamName' => 'Test Team',
            'position' => 'PG',
            'name' => 'John Doe',
        ];

        // Different case - should fail
        $result = CommonValidator::validatePlayerOwnership($player, 'test team');

        $this->assertFalse($result->isValid());
    }

    public function testValidatePlayerOwnershipWithEmptyTeamName(): void
    {
        $player = (object) [
            'teamName' => '',
            'position' => 'PG',
            'name' => 'John Doe',
        ];

        $result = CommonValidator::validatePlayerOwnership($player, '');

        // Empty strings should match
        $this->assertTrue($result->isValid());
    }
}
