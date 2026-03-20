<?php

declare(strict_types=1);

namespace Tests\Auth;

use Auth\AuthService;
use Delight\Auth\Role;
use PHPUnit\Framework\TestCase;

/**
 * AuthServiceTest - Unit tests for session-based authentication
 *
 * Tests that do not require a real database connection.
 * For attempt/upgrade tests, use a real DB or integration test.
 */
class AuthServiceTest extends TestCase
{
    private AuthService $authService;

    protected function setUp(): void
    {
        parent::setUp();

        // Create AuthService with a mock mysqli (stub for non-DB tests)
        $mockMysqli = static::createStub(\mysqli::class);
        $this->authService = new AuthService($mockMysqli);

        // Start a test session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        // Clear session auth keys before each test
        unset($_SESSION['auth_user_id'], $_SESSION['auth_username'], $_SESSION['auth_roles']);
    }

    protected function tearDown(): void
    {
        // Clean up session
        unset($_SESSION['auth_user_id'], $_SESSION['auth_username'], $_SESSION['auth_roles']);
        parent::tearDown();
    }

    public function testHashPasswordProducesBcryptHash(): void
    {
        $hash = $this->authService->hashPassword('testpassword');

        self::assertStringStartsWith('$2y$', $hash);
        self::assertGreaterThanOrEqual(60, strlen($hash));
    }

    public function testHashPasswordProducesVerifiableHash(): void
    {
        $password = 'my-secure-password';
        $hash = $this->authService->hashPassword($password);

        self::assertTrue(password_verify($password, $hash));
    }

    public function testHashPasswordWithDifferentPasswordsFails(): void
    {
        $hash = $this->authService->hashPassword('correct-password');

        self::assertFalse(password_verify('wrong-password', $hash));
    }

    public function testHashPasswordUsesCost12(): void
    {
        $hash = $this->authService->hashPassword('test');

        // bcrypt hash format: $2y$12$...
        self::assertStringStartsWith('$2y$12$', $hash);
    }

    public function testIsAuthenticatedReturnsFalseByDefault(): void
    {
        self::assertFalse($this->authService->isAuthenticated());
    }

    public function testIsAuthenticatedReturnsTrueWithSession(): void
    {
        $_SESSION['auth_user_id'] = 42;
        $_SESSION['auth_username'] = 'testuser';

        self::assertTrue($this->authService->isAuthenticated());
    }

    public function testIsAuthenticatedReturnsFalseWithZeroUserId(): void
    {
        $_SESSION['auth_user_id'] = 0;
        $_SESSION['auth_username'] = 'testuser';

        self::assertFalse($this->authService->isAuthenticated());
    }

    public function testIsAuthenticatedReturnsFalseWithNonIntUserId(): void
    {
        $_SESSION['auth_user_id'] = 'not-an-int';
        $_SESSION['auth_username'] = 'testuser';

        self::assertFalse($this->authService->isAuthenticated());
    }

    public function testGetUserIdReturnsNullWhenNotAuthenticated(): void
    {
        self::assertNull($this->authService->getUserId());
    }

    public function testGetUserIdReturnsIdWhenAuthenticated(): void
    {
        $_SESSION['auth_user_id'] = 42;
        $_SESSION['auth_username'] = 'testuser';

        self::assertSame(42, $this->authService->getUserId());
    }

    public function testGetUsernameReturnsNullWhenNotAuthenticated(): void
    {
        self::assertNull($this->authService->getUsername());
    }

    public function testGetUsernameReturnsUsernameWhenAuthenticated(): void
    {
        $_SESSION['auth_user_id'] = 42;
        $_SESSION['auth_username'] = 'testuser';

        self::assertSame('testuser', $this->authService->getUsername());
    }

    public function testLogoutClearsSession(): void
    {
        $_SESSION['auth_user_id'] = 42;
        $_SESSION['auth_username'] = 'testuser';
        $_SESSION['auth_roles'] = 1; // ADMIN role cached from login

        self::assertTrue($this->authService->isAuthenticated());

        $this->authService->logout();

        self::assertFalse($this->authService->isAuthenticated());
        self::assertArrayNotHasKey('auth_user_id', $_SESSION);
        self::assertArrayNotHasKey('auth_username', $_SESSION);
        self::assertArrayNotHasKey('auth_roles', $_SESSION);
    }

    public function testGetUserInfoReturnsNullWhenNotAuthenticated(): void
    {
        self::assertNull($this->authService->getUserInfo());
    }

    public function testGetCookieArrayReturnsNullWhenNotAuthenticated(): void
    {
        self::assertNull($this->authService->getCookieArray());
    }

    public function testHashPasswordProducesUniqueHashes(): void
    {
        $hash1 = $this->authService->hashPassword('same-password');
        $hash2 = $this->authService->hashPassword('same-password');

        // bcrypt includes a random salt, so two hashes of the same password differ
        self::assertNotSame($hash1, $hash2);
        // But both verify against the original password
        self::assertTrue(password_verify('same-password', $hash1));
        self::assertTrue(password_verify('same-password', $hash2));
    }

    // --- Merged from AuthServiceRegistrationTest ---

    public function testGetLastErrorReturnsNullByDefault(): void
    {
        self::assertNull($this->authService->getLastError());
    }

    public function testImplementsAuthServiceInterface(): void
    {
        self::assertInstanceOf(\Auth\Contracts\AuthServiceInterface::class, $this->authService);
    }

    public function testHashPasswordStillWorks(): void
    {
        $hash = $this->authService->hashPassword('test-password');

        self::assertStringStartsWith('$2y$12$', $hash);
        self::assertTrue(password_verify('test-password', $hash));
    }

    public function testRegisterMethodSignatureAcceptsCallable(): void
    {
        $reflection = new \ReflectionMethod(AuthService::class, 'register');
        $params = $reflection->getParameters();

        self::assertCount(4, $params);
        self::assertSame('email', $params[0]->getName());
        self::assertSame('password', $params[1]->getName());
        self::assertSame('username', $params[2]->getName());
        self::assertSame('emailCallback', $params[3]->getName());
        self::assertTrue($params[3]->allowsNull());
    }

    public function testConfirmEmailMethodSignature(): void
    {
        $reflection = new \ReflectionMethod(AuthService::class, 'confirmEmail');
        $params = $reflection->getParameters();

        self::assertCount(2, $params);
        self::assertSame('selector', $params[0]->getName());
        self::assertSame('token', $params[1]->getName());
    }

    public function testResetPasswordMethodSignature(): void
    {
        $reflection = new \ReflectionMethod(AuthService::class, 'resetPassword');
        $params = $reflection->getParameters();

        self::assertCount(3, $params);
        self::assertSame('selector', $params[0]->getName());
        self::assertSame('token', $params[1]->getName());
        self::assertSame('newPassword', $params[2]->getName());
    }

    public function testForgotPasswordMethodSignature(): void
    {
        $reflection = new \ReflectionMethod(AuthService::class, 'forgotPassword');
        $params = $reflection->getParameters();

        self::assertCount(2, $params);
        self::assertSame('email', $params[0]->getName());
        self::assertSame('callback', $params[1]->getName());
    }

    public function testGetLastErrorReturnType(): void
    {
        $reflection = new \ReflectionMethod(AuthService::class, 'getLastError');
        $returnType = $reflection->getReturnType();

        self::assertNotNull($returnType);
        self::assertInstanceOf(\ReflectionNamedType::class, $returnType);
        self::assertTrue($returnType->allowsNull());
        self::assertSame('string', $returnType->getName());
    }

    // --- Merged from AuthServiceAdminTest ---

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
