<?php

declare(strict_types=1);

namespace Tests\Auth;

use Auth\AuthService;
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
        unset($_SESSION['auth_user_id'], $_SESSION['auth_username']);
    }

    protected function tearDown(): void
    {
        // Clean up session
        unset($_SESSION['auth_user_id'], $_SESSION['auth_username']);
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

        self::assertTrue($this->authService->isAuthenticated());

        $this->authService->logout();

        self::assertFalse($this->authService->isAuthenticated());
        self::assertArrayNotHasKey('auth_user_id', $_SESSION);
        self::assertArrayNotHasKey('auth_username', $_SESSION);
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
}
