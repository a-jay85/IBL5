<?php

declare(strict_types=1);

namespace Tests\Auth;

use Auth\DevAutoLogin;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DevAutoLoginTest extends TestCase
{
    private string $originalServerName;
    /** @var string|false */
    private string|false $originalEnvVar;

    protected function setUp(): void
    {
        parent::setUp();

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $this->originalServerName = is_string($_SERVER['SERVER_NAME'] ?? null)
            ? $_SERVER['SERVER_NAME']
            : '';
        $this->originalEnvVar = getenv('DEV_AUTO_LOGIN');

        unset($_SESSION['auth_user_id'], $_SESSION['auth_username']);
        putenv('DEV_AUTO_LOGIN');
    }

    protected function tearDown(): void
    {
        unset($_SESSION['auth_user_id'], $_SESSION['auth_username']);

        $_SERVER['SERVER_NAME'] = $this->originalServerName;

        if ($this->originalEnvVar !== false) {
            putenv('DEV_AUTO_LOGIN=' . $this->originalEnvVar);
        } else {
            putenv('DEV_AUTO_LOGIN');
        }

        parent::tearDown();
    }

    public function testDoesNothingWhenAlreadyAuthenticated(): void
    {
        $_SESSION['auth_user_id'] = 42;
        $_SESSION['auth_username'] = 'ExistingUser';
        $_SERVER['SERVER_NAME'] = 'localhost';
        putenv('DEV_AUTO_LOGIN=SomeOtherUser');

        $db = static::createStub(\mysqli::class);
        DevAutoLogin::tryAutoLogin($db);

        // Session should remain unchanged — not overwritten with the env var user
        self::assertSame(42, $_SESSION['auth_user_id']);
        self::assertSame('ExistingUser', $_SESSION['auth_username']);
    }

    #[DataProvider('productionHostProvider')]
    public function testDoesNothingWhenServerNameIsNotLocalhost(string $host): void
    {
        $_SERVER['SERVER_NAME'] = $host;
        putenv('DEV_AUTO_LOGIN=A-Jay');

        $db = static::createStub(\mysqli::class);
        DevAutoLogin::tryAutoLogin($db);

        self::assertArrayNotHasKey('auth_user_id', $_SESSION);
    }

    /**
     * @return array<string, list<string>>
     */
    public static function productionHostProvider(): array
    {
        return [
            'production domain' => ['iblhoops.net'],
            'other domain' => ['example.com'],
            'subdomain spoof' => ['main.localhost.evil.com'],
        ];
    }

    public function testDoesNothingWhenEnvVarNotSet(): void
    {
        $_SERVER['SERVER_NAME'] = 'localhost';
        putenv('DEV_AUTO_LOGIN');

        $db = static::createStub(\mysqli::class);
        DevAutoLogin::tryAutoLogin($db);

        self::assertArrayNotHasKey('auth_user_id', $_SESSION);
    }

    public function testDoesNothingWhenEnvVarIsEmpty(): void
    {
        $_SERVER['SERVER_NAME'] = 'localhost';
        putenv('DEV_AUTO_LOGIN=');

        $db = static::createStub(\mysqli::class);
        DevAutoLogin::tryAutoLogin($db);

        self::assertArrayNotHasKey('auth_user_id', $_SESSION);
    }

    #[DataProvider('allowedHostProvider')]
    public function testSetsSessionWhenAllConditionsMet(string $host): void
    {
        $_SERVER['SERVER_NAME'] = $host;
        putenv('DEV_AUTO_LOGIN=TestUser');

        $result = static::createStub(\mysqli_result::class);
        $result->method('fetch_assoc')->willReturn(['user_id' => 99]);

        $stmt = static::createStub(\mysqli_stmt::class);
        $stmt->method('get_result')->willReturn($result);

        $db = static::createStub(\mysqli::class);
        $db->method('prepare')->willReturn($stmt);

        DevAutoLogin::tryAutoLogin($db);

        self::assertSame(99, $_SESSION['auth_user_id']);
        self::assertSame('TestUser', $_SESSION['auth_username']);
    }

    /**
     * @return array<string, list<string>>
     */
    public static function allowedHostProvider(): array
    {
        return [
            'localhost' => ['localhost'],
            'loopback IP' => ['127.0.0.1'],
            'main.localhost' => ['main.localhost'],
        ];
    }

    public function testDoesNothingWhenUserNotFoundInDatabase(): void
    {
        $_SERVER['SERVER_NAME'] = 'localhost';
        putenv('DEV_AUTO_LOGIN=NonExistentUser');

        $result = static::createStub(\mysqli_result::class);
        $result->method('fetch_assoc')->willReturn(null);

        $stmt = static::createStub(\mysqli_stmt::class);
        $stmt->method('get_result')->willReturn($result);

        $db = static::createStub(\mysqli::class);
        $db->method('prepare')->willReturn($stmt);

        DevAutoLogin::tryAutoLogin($db);

        self::assertArrayNotHasKey('auth_user_id', $_SESSION);
    }

    public function testDoesNothingWhenPrepareFailsReturningFalse(): void
    {
        $_SERVER['SERVER_NAME'] = 'localhost';
        putenv('DEV_AUTO_LOGIN=TestUser');

        $db = static::createStub(\mysqli::class);
        $db->method('prepare')->willReturn(false);

        DevAutoLogin::tryAutoLogin($db);

        self::assertArrayNotHasKey('auth_user_id', $_SESSION);
    }
}
