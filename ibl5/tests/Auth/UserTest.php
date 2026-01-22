<?php

declare(strict_types=1);

namespace Tests\Auth;

use PHPUnit\Framework\TestCase;
use Auth\User;

class UserTest extends TestCase
{
    public function testConstructorWithValidData(): void
    {
        $data = [
            'id' => 1,
            'name' => 'testuser',
            'email' => 'test@example.com',
            'password' => '$2y$10$hashedpassword',
            'role' => User::ROLE_OWNER,
            'teams_owned' => '["CHI", "LAL"]',
        ];

        $user = new User($data);

        $this->assertEquals(1, $user->getId());
        $this->assertEquals('testuser', $user->getName());
        $this->assertEquals('test@example.com', $user->getEmail());
        $this->assertEquals(User::ROLE_OWNER, $user->getRole());
        $this->assertEquals(['CHI', 'LAL'], $user->getTeamsOwned());
    }

    public function testConstructorWithEmptyData(): void
    {
        $user = new User([]);

        $this->assertEquals(0, $user->getId());
        $this->assertEquals('', $user->getName());
        $this->assertEquals('', $user->getEmail());
        $this->assertEquals(User::ROLE_SPECTATOR, $user->getRole());
        $this->assertEquals([], $user->getTeamsOwned());
    }

    public function testIsAdminReturnsTrueForCommissioner(): void
    {
        $user = new User(['role' => User::ROLE_COMMISSIONER]);
        $this->assertTrue($user->isAdmin());
    }

    public function testIsAdminReturnsFalseForOwner(): void
    {
        $user = new User(['role' => User::ROLE_OWNER]);
        $this->assertFalse($user->isAdmin());
    }

    public function testIsAdminReturnsFalseForSpectator(): void
    {
        $user = new User(['role' => User::ROLE_SPECTATOR]);
        $this->assertFalse($user->isAdmin());
    }

    public function testIsOwnerReturnsTrueForOwner(): void
    {
        $user = new User(['role' => User::ROLE_OWNER]);
        $this->assertTrue($user->isOwner());
    }

    public function testIsOwnerReturnsTrueForCommissioner(): void
    {
        $user = new User(['role' => User::ROLE_COMMISSIONER]);
        $this->assertTrue($user->isOwner());
    }

    public function testIsOwnerReturnsFalseForSpectator(): void
    {
        $user = new User(['role' => User::ROLE_SPECTATOR]);
        $this->assertFalse($user->isOwner());
    }

    public function testHasRoleCommissionerHasAllRoles(): void
    {
        $user = new User(['role' => User::ROLE_COMMISSIONER]);

        $this->assertTrue($user->hasRole(User::ROLE_COMMISSIONER));
        $this->assertTrue($user->hasRole(User::ROLE_OWNER));
        $this->assertTrue($user->hasRole(User::ROLE_SPECTATOR));
    }

    public function testHasRoleOwnerHasOwnerAndSpectator(): void
    {
        $user = new User(['role' => User::ROLE_OWNER]);

        $this->assertFalse($user->hasRole(User::ROLE_COMMISSIONER));
        $this->assertTrue($user->hasRole(User::ROLE_OWNER));
        $this->assertTrue($user->hasRole(User::ROLE_SPECTATOR));
    }

    public function testHasRoleSpectatorOnlyHasSpectator(): void
    {
        $user = new User(['role' => User::ROLE_SPECTATOR]);

        $this->assertFalse($user->hasRole(User::ROLE_COMMISSIONER));
        $this->assertFalse($user->hasRole(User::ROLE_OWNER));
        $this->assertTrue($user->hasRole(User::ROLE_SPECTATOR));
    }

    public function testOwnsTeamWithOwnedTeam(): void
    {
        $user = new User([
            'role' => User::ROLE_OWNER,
            'teams_owned' => '["CHI", "LAL"]',
        ]);

        $this->assertTrue($user->ownsTeam('CHI'));
        $this->assertTrue($user->ownsTeam('LAL'));
        $this->assertFalse($user->ownsTeam('BOS'));
    }

    public function testOwnsTeamCommissionerOwnsAllTeams(): void
    {
        $user = new User(['role' => User::ROLE_COMMISSIONER]);

        $this->assertTrue($user->ownsTeam('CHI'));
        $this->assertTrue($user->ownsTeam('BOS'));
        $this->assertTrue($user->ownsTeam(123));
    }

    public function testOwnsTeamWithIntegerId(): void
    {
        $user = new User([
            'role' => User::ROLE_OWNER,
            'teams_owned' => '[1, 2, 3]',
        ]);

        $this->assertTrue($user->ownsTeam(1));
        $this->assertTrue($user->ownsTeam('1'));
        $this->assertFalse($user->ownsTeam(4));
    }

    public function testHasLegacyPassword(): void
    {
        $userWithLegacy = new User(['legacy_password' => 'abc123md5hash']);
        $userWithoutLegacy = new User(['legacy_password' => null]);
        $userWithEmpty = new User(['legacy_password' => '']);

        $this->assertTrue($userWithLegacy->hasLegacyPassword());
        $this->assertFalse($userWithoutLegacy->hasLegacyPassword());
        $this->assertFalse($userWithEmpty->hasLegacyPassword());
    }

    public function testIsMigrated(): void
    {
        $migratedUser = new User(['migrated_at' => '2026-01-20 12:00:00']);
        $notMigratedUser = new User(['migrated_at' => null]);

        $this->assertTrue($migratedUser->isMigrated());
        $this->assertFalse($notMigratedUser->isMigrated());
    }

    public function testParseTeamsOwnedFromJsonString(): void
    {
        $user = new User(['teams_owned' => '["CHI", "LAL", "BOS"]']);
        $this->assertEquals(['CHI', 'LAL', 'BOS'], $user->getTeamsOwned());
    }

    public function testParseTeamsOwnedFromArray(): void
    {
        $user = new User(['teams_owned' => ['CHI', 'LAL']]);
        $this->assertEquals(['CHI', 'LAL'], $user->getTeamsOwned());
    }

    public function testParseTeamsOwnedFromCommaSeparatedString(): void
    {
        $user = new User(['teams_owned' => 'CHI, LAL, BOS']);
        $this->assertEquals(['CHI', 'LAL', 'BOS'], $user->getTeamsOwned());
    }

    public function testParseTeamsOwnedFromSingleTeam(): void
    {
        $user = new User(['teams_owned' => 'CHI']);
        $this->assertEquals(['CHI'], $user->getTeamsOwned());
    }

    public function testToArray(): void
    {
        $data = [
            'id' => 1,
            'name' => 'testuser',
            'email' => 'test@example.com',
            'role' => User::ROLE_OWNER,
            'teams_owned' => '["CHI"]',
        ];

        $user = new User($data);
        $array = $user->toArray();

        $this->assertEquals(1, $array['id']);
        $this->assertEquals('testuser', $array['name']);
        $this->assertEquals('test@example.com', $array['email']);
        $this->assertEquals(User::ROLE_OWNER, $array['role']);
        $this->assertEquals('["CHI"]', $array['teams_owned']);
    }
}
