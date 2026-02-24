<?php

declare(strict_types=1);

namespace Tests\Auth;

use Auth\AuthService;
use Delight\Auth\Role;
use PHPUnit\Framework\TestCase;

/**
 * Tests for isAdmin() and hasRole() on AuthService.
 *
 * These test the session-cached path. The database fallback path is tested
 * implicitly via integration tests that exercise a real database.
 */
class AuthServiceAdminTest extends TestCase
{
    private AuthService $authService;

    protected function setUp(): void
    {
        parent::setUp();

        $mockMysqli = static::createStub(\mysqli::class);
        $this->authService = new AuthService($mockMysqli);

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        unset(
            $_SESSION['auth_user_id'],
            $_SESSION['auth_username'],
            $_SESSION['auth_roles'],
        );
    }

    protected function tearDown(): void
    {
        unset(
            $_SESSION['auth_user_id'],
            $_SESSION['auth_username'],
            $_SESSION['auth_roles'],
        );
        parent::tearDown();
    }

    public function testIsAdminReturnsFalseWhenNotAuthenticated(): void
    {
        self::assertFalse($this->authService->isAdmin());
    }

    public function testIsAdminReturnsTrueWhenSessionHasAdminRole(): void
    {
        $_SESSION['auth_user_id'] = 1;
        $_SESSION['auth_username'] = 'admin';
        $_SESSION['auth_roles'] = Role::ADMIN;

        self::assertTrue($this->authService->isAdmin());
    }

    public function testIsAdminReturnsFalseWhenSessionHasNoRoles(): void
    {
        $_SESSION['auth_user_id'] = 1;
        $_SESSION['auth_username'] = 'regular_user';
        $_SESSION['auth_roles'] = 0;

        self::assertFalse($this->authService->isAdmin());
    }

    public function testIsAdminReturnsTrueWhenSessionHasMultipleRolesIncludingAdmin(): void
    {
        $_SESSION['auth_user_id'] = 1;
        $_SESSION['auth_username'] = 'super_admin';
        // Admin (1) | Moderator (2) | Editor (4) = 7
        $_SESSION['auth_roles'] = Role::ADMIN | Role::MODERATOR | Role::EDITOR;

        self::assertTrue($this->authService->isAdmin());
    }

    public function testHasRoleReturnsFalseWhenNotAuthenticated(): void
    {
        self::assertFalse($this->authService->hasRole(Role::ADMIN));
    }

    public function testHasRoleChecksSpecificBit(): void
    {
        $_SESSION['auth_user_id'] = 1;
        $_SESSION['auth_username'] = 'editor';
        $_SESSION['auth_roles'] = Role::EDITOR; // 4

        self::assertTrue($this->authService->hasRole(Role::EDITOR));
        self::assertFalse($this->authService->hasRole(Role::ADMIN));
        self::assertFalse($this->authService->hasRole(Role::MODERATOR));
    }

    public function testHasRoleWithMultipleRoles(): void
    {
        $_SESSION['auth_user_id'] = 1;
        $_SESSION['auth_username'] = 'mod_editor';
        $_SESSION['auth_roles'] = Role::MODERATOR | Role::EDITOR; // 6

        self::assertTrue($this->authService->hasRole(Role::MODERATOR));
        self::assertTrue($this->authService->hasRole(Role::EDITOR));
        self::assertFalse($this->authService->hasRole(Role::ADMIN));
    }

    public function testHasRoleFallsBackToDbWhenSessionNotSet(): void
    {
        // Authenticate the user but don't set auth_roles in session
        $_SESSION['auth_user_id'] = 1;
        $_SESSION['auth_username'] = 'testuser';
        // auth_roles is NOT set — so hasRole() should query the DB

        // Since we're using a stub mysqli that won't prepare anything,
        // the prepare() will return false and the method returns false
        self::assertFalse($this->authService->hasRole(Role::ADMIN));
    }

    public function testIsAdminReturnsFalseWhenAuthRolesIsNotInt(): void
    {
        $_SESSION['auth_user_id'] = 1;
        $_SESSION['auth_username'] = 'testuser';
        $_SESSION['auth_roles'] = 'not-an-int';

        // Non-int auth_roles should be ignored, falling back to DB path
        // which returns false because stub mysqli can't prepare
        self::assertFalse($this->authService->isAdmin());
    }
}
