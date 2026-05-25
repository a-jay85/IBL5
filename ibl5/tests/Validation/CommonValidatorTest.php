<?php

declare(strict_types=1);

namespace Tests\Validation;

use PHPUnit\Framework\TestCase;
use Player\Player;
use Validation\CommonValidator;

/**
 * CommonValidatorTest - Tests for CommonValidator static methods
 */
class CommonValidatorTest extends TestCase
{
    // ============================================
    // VALIDATE PLAYER OWNERSHIP TESTS
    // ============================================

    private function createPlayerStub(?string $teamName, ?string $position = null, ?string $name = null): Player
    {
        $player = $this->createStub(Player::class);
        $player->method('getTeamName')->willReturn($teamName);
        $player->method('getPosition')->willReturn($position);
        $player->method('getName')->willReturn($name);
        return $player;
    }

    public function testValidatePlayerOwnershipReturnsValidWhenPlayerOnTeam(): void
    {
        $player = $this->createPlayerStub('Test Team', 'PG', 'John Doe');

        $result = CommonValidator::validatePlayerOwnership($player, 'Test Team');

        $this->assertTrue($result->isValid());
    }

    public function testValidatePlayerOwnershipReturnsInvalidWhenPlayerOnDifferentTeam(): void
    {
        $player = $this->createPlayerStub('Other Team', 'SG', 'Jane Doe');

        $result = CommonValidator::validatePlayerOwnership($player, 'Test Team');

        $this->assertFalse($result->isValid());
        $this->assertNotNull($result->getError());
    }

    public function testValidatePlayerOwnershipErrorIncludesPlayerInfo(): void
    {
        $player = $this->createPlayerStub('Other Team', 'C', 'Big Center');

        $result = CommonValidator::validatePlayerOwnership($player, 'Test Team');

        $this->assertStringContainsString('C Big Center', $result->getError() ?? '');
        $this->assertStringContainsString('not on your team', $result->getError() ?? '');
    }

    public function testValidatePlayerOwnershipHandlesMissingPositionAndName(): void
    {
        $player = $this->createPlayerStub('Other Team');

        $result = CommonValidator::validatePlayerOwnership($player, 'Test Team');

        $this->assertFalse($result->isValid());
        $this->assertStringContainsString('This player', $result->getError() ?? '');
    }

    public function testValidatePlayerOwnershipIsCaseSensitive(): void
    {
        $player = $this->createPlayerStub('Test Team', 'PG', 'John Doe');

        $result = CommonValidator::validatePlayerOwnership($player, 'test team');

        $this->assertFalse($result->isValid());
    }

    public function testValidatePlayerOwnershipWithEmptyTeamName(): void
    {
        $player = $this->createPlayerStub('', 'PG', 'John Doe');

        $result = CommonValidator::validatePlayerOwnership($player, '');

        $this->assertTrue($result->isValid());
    }
}
