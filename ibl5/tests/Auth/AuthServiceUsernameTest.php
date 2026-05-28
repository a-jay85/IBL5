<?php

declare(strict_types=1);

namespace Tests\Auth;

use Auth\AuthService;
use Auth\Contracts\AuthRepositoryInterface;
use PHPUnit\Framework\TestCase;

final class AuthServiceUsernameTest extends TestCase
{
    private AuthService $authService;

    protected function setUp(): void
    {
        $stubRepo = $this->createStub(AuthRepositoryInterface::class);
        $this->authService = new AuthService($stubRepo);

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        unset($_SESSION['auth_user_id'], $_SESSION['auth_username'], $_SESSION['auth_roles']);

        require_once __DIR__ . '/../../classes/Bootstrap/LegacyFunctions.php';
    }

    protected function tearDown(): void
    {
        unset(
            $_SESSION['auth_user_id'],
            $_SESSION['auth_username'],
            $_SESSION['auth_roles'],
            $GLOBALS['authService'],
            $GLOBALS['cookie'],
        );
    }

    public function testGetUsernameMatchesCookieArrayIndex1WhenAuthenticated(): void
    {
        $_SESSION['auth_user_id'] = 42;
        $_SESSION['auth_username'] = 'testuser';
        $_SESSION['auth_roles'] = 0;

        $username = $this->authService->getUsername();
        self::assertSame('testuser', $username);

        $GLOBALS['authService'] = $this->authService;
        $cookieArray = cookiedecode('ignored');

        self::assertIsArray($cookieArray);
        self::assertSame($username, $cookieArray[1]);
    }

    public function testGetUsernameReturnsNullWhenNotAuthenticated(): void
    {
        self::assertNull($this->authService->getUsername());
    }
}
