<?php

declare(strict_types=1);

namespace Tests\Auth;

use PHPUnit\Framework\TestCase;
use Auth\LaravelAuthBridge;
use Auth\User;

class LaravelAuthBridgeTest extends TestCase
{
    /** @var \mysqli&\PHPUnit\Framework\MockObject\MockObject */
    private \mysqli $mockDb;

    protected function setUp(): void
    {
        $this->mockDb = $this->createMock(\mysqli::class);
    }

    public function testIsAdminReturnsFalseWhenNotAuthenticated(): void
    {
        $bridge = new LaravelAuthBridge($this->mockDb);

        // No user in session
        $this->assertFalse($bridge->isAdmin());
    }

    public function testIsUserReturnsFalseWhenNotAuthenticated(): void
    {
        $bridge = new LaravelAuthBridge($this->mockDb);

        $this->assertFalse($bridge->isUser());
    }

    public function testGetUserReturnsNullWhenNotAuthenticated(): void
    {
        $bridge = new LaravelAuthBridge($this->mockDb);

        $this->assertNull($bridge->getUser());
    }

    public function testGetUserInfoReturnsEmptyArrayWhenNotAuthenticated(): void
    {
        $bridge = new LaravelAuthBridge($this->mockDb);

        $this->assertEquals([], $bridge->getUserInfo());
    }

    public function testHasRoleReturnsFalseWhenNotAuthenticated(): void
    {
        $bridge = new LaravelAuthBridge($this->mockDb);

        $this->assertFalse($bridge->hasRole(User::ROLE_OWNER));
    }

    public function testOwnsTeamReturnsFalseWhenNotAuthenticated(): void
    {
        $bridge = new LaravelAuthBridge($this->mockDb);

        $this->assertFalse($bridge->ownsTeam('CHI'));
    }

    public function testGetOwnedTeamsReturnsEmptyArrayWhenNotAuthenticated(): void
    {
        $bridge = new LaravelAuthBridge($this->mockDb);

        $this->assertEquals([], $bridge->getOwnedTeams());
    }

    public function testAuthenticateReturnsFalseForNonexistentUser(): void
    {
        // Mock statement
        $mockStmt = $this->createMock(\mysqli_stmt::class);
        $mockResult = $this->createMock(\mysqli_result::class);

        $mockResult->method('fetch_assoc')->willReturn(null);
        $mockStmt->method('execute')->willReturn(true);
        $mockStmt->method('get_result')->willReturn($mockResult);
        $mockStmt->method('bind_param')->willReturn(true);
        $mockStmt->method('close')->willReturn(true);

        $this->mockDb->method('prepare')->willReturn($mockStmt);

        $bridge = new LaravelAuthBridge($this->mockDb);

        $this->assertFalse($bridge->authenticate('nonexistent', 'password'));
    }

    public function testAuthenticateReturnsFalseForWrongPassword(): void
    {
        // Create a user row with a bcrypt password
        $userData = [
            'id' => 1,
            'name' => 'testuser',
            'email' => 'test@example.com',
            'password' => password_hash('correct_password', PASSWORD_BCRYPT),
            'legacy_password' => null,
            'role' => User::ROLE_OWNER,
            'teams_owned' => '[]',
        ];

        $mockStmt = $this->createMock(\mysqli_stmt::class);
        $mockResult = $this->createMock(\mysqli_result::class);

        $mockResult->method('fetch_assoc')->willReturn($userData);
        $mockStmt->method('execute')->willReturn(true);
        $mockStmt->method('get_result')->willReturn($mockResult);
        $mockStmt->method('bind_param')->willReturn(true);
        $mockStmt->method('close')->willReturn(true);

        $this->mockDb->method('prepare')->willReturn($mockStmt);

        $bridge = new LaravelAuthBridge($this->mockDb);

        $this->assertFalse($bridge->authenticate('testuser', 'wrong_password'));
    }

    /**
     * Test that MD5 legacy passwords are detected
     */
    public function testDetectsLegacyMd5Password(): void
    {
        $password = 'testpassword';
        $md5Hash = md5($password);

        $userData = [
            'id' => 1,
            'name' => 'legacyuser',
            'email' => 'legacy@example.com',
            'password' => '', // Empty bcrypt password
            'legacy_password' => $md5Hash, // MD5 hash
            'role' => User::ROLE_OWNER,
            'teams_owned' => '[]',
            'nuke_user_id' => 100,
            'migrated_at' => null,
        ];

        $user = new User($userData);

        $this->assertTrue($user->hasLegacyPassword());
        $this->assertEquals($md5Hash, $user->getLegacyPassword());
    }

    /**
     * Test that logout clears authentication state
     */
    public function testLogoutClearsState(): void
    {
        $mockStmt = $this->createMock(\mysqli_stmt::class);
        $mockStmt->method('execute')->willReturn(true);
        $mockStmt->method('bind_param')->willReturn(true);
        $mockStmt->method('close')->willReturn(true);

        $this->mockDb->method('prepare')->willReturn($mockStmt);

        $bridge = new LaravelAuthBridge($this->mockDb);
        $bridge->logout();

        // After logout, user should not be authenticated
        $this->assertFalse($bridge->isUser());
        $this->assertNull($bridge->getUser());
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('roleDataProvider')]
    public function testHasRoleWithDifferentRoles(string $userRole, string $checkRole, bool $expected): void
    {
        $user = new User(['role' => $userRole]);
        $this->assertEquals($expected, $user->hasRole($checkRole));
    }

    /**
     * @return array<string, array{string, string, bool}>
     */
    public static function roleDataProvider(): array
    {
        return [
            'commissioner has commissioner' => [User::ROLE_COMMISSIONER, User::ROLE_COMMISSIONER, true],
            'commissioner has owner' => [User::ROLE_COMMISSIONER, User::ROLE_OWNER, true],
            'commissioner has spectator' => [User::ROLE_COMMISSIONER, User::ROLE_SPECTATOR, true],
            'owner has commissioner' => [User::ROLE_OWNER, User::ROLE_COMMISSIONER, false],
            'owner has owner' => [User::ROLE_OWNER, User::ROLE_OWNER, true],
            'owner has spectator' => [User::ROLE_OWNER, User::ROLE_SPECTATOR, true],
            'spectator has commissioner' => [User::ROLE_SPECTATOR, User::ROLE_COMMISSIONER, false],
            'spectator has owner' => [User::ROLE_SPECTATOR, User::ROLE_OWNER, false],
            'spectator has spectator' => [User::ROLE_SPECTATOR, User::ROLE_SPECTATOR, true],
        ];
    }
}
