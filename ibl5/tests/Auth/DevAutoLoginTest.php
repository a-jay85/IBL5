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
    /** @var array<string, string> */
    private array $originalCookies;

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
        $this->originalCookies = $_COOKIE;

        unset($_SESSION['auth_user_id'], $_SESSION['auth_username']);
        unset($_COOKIE['_auto_login']);
        putenv('DEV_AUTO_LOGIN');
    }

    protected function tearDown(): void
    {
        unset($_SESSION['auth_user_id'], $_SESSION['auth_username']);

        $_SERVER['SERVER_NAME'] = $this->originalServerName;
        $_COOKIE = $this->originalCookies;

        if ($this->originalEnvVar !== false) {
            putenv('DEV_AUTO_LOGIN=' . $this->originalEnvVar);
        } else {
            putenv('DEV_AUTO_LOGIN');
        }

        parent::tearDown();
    }

    public function testDoesNothingWhenAlreadyAuthenticated(): void
    {
        $originalUserId = 42;
        $_SESSION['auth_user_id'] = $originalUserId;
        $_SESSION['auth_username'] = 'ExistingUser';
        $_SERVER['SERVER_NAME'] = 'localhost';
        putenv('DEV_AUTO_LOGIN=SomeOtherUser');

        $db = static::createStub(\mysqli::class);
        (new DevAutoLogin())->tryAutoLogin($db);

        // Session should remain unchanged — not overwritten with the env var user.
        // Both halves of the contract are asserted: tryAutoLogin() must leave the
        // existing user id AND username untouched. PHPStan narrows the session slot to
        // the literal 42 from the assignment above and cannot see that tryAutoLogin()
        // might overwrite it, so the id assertion looks statically tautological — but it
        // is runtime-meaningful (it fails if the call mutates auth_user_id).
        self::assertSame($originalUserId, $_SESSION['auth_user_id']); /** @phpstan-ignore staticMethod.alreadyNarrowedType (PHPStan narrows the session slot to 42; tryAutoLogin() could overwrite it at runtime) */
        self::assertSame('ExistingUser', $_SESSION['auth_username']);
    }

    #[DataProvider('productionHostProvider')]
    public function testDoesNothingWhenServerNameIsNotLocalhost(string $host): void
    {
        $_SERVER['SERVER_NAME'] = $host;
        putenv('DEV_AUTO_LOGIN=A-Jay');

        $db = static::createStub(\mysqli::class);
        (new DevAutoLogin())->tryAutoLogin($db);

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
        (new DevAutoLogin())->tryAutoLogin($db);

        self::assertArrayNotHasKey('auth_user_id', $_SESSION);
    }

    public function testDoesNothingWhenEnvVarIsEmpty(): void
    {
        $_SERVER['SERVER_NAME'] = 'localhost';
        putenv('DEV_AUTO_LOGIN=');

        $db = static::createStub(\mysqli::class);
        (new DevAutoLogin())->tryAutoLogin($db);

        self::assertArrayNotHasKey('auth_user_id', $_SESSION);
    }

    public function testDoesNothingWhenOptInCookieAbsentOnLocalhost(): void
    {
        // All other guards satisfied (localhost host, env user set, user would resolve),
        // but the _auto_login opt-in cookie is absent — the default manual-browsing state.
        $_SERVER['SERVER_NAME'] = 'localhost';
        putenv('DEV_AUTO_LOGIN=TestUser');
        // setUp() already unset $_COOKIE['_auto_login'].

        $db = static::createStub(\mysqli::class);
        (new DevAutoLogin())->tryAutoLogin($db);

        self::assertArrayNotHasKey('auth_user_id', $_SESSION);
    }

    public function testSetsSessionWhenOptInCookiePresentOnLocalhost(): void
    {
        $_SERVER['SERVER_NAME'] = 'localhost';
        $_COOKIE['_auto_login'] = '1';
        putenv('DEV_AUTO_LOGIN=TestUser');

        $result = static::createStub(\mysqli_result::class);
        $result->method('fetch_assoc')->willReturn(['user_id' => 7]);

        $stmt = static::createStub(\mysqli_stmt::class);
        $stmt->method('get_result')->willReturn($result);

        $db = static::createStub(\mysqli::class);
        $db->method('prepare')->willReturn($stmt);

        (new DevAutoLogin())->tryAutoLogin($db);

        self::assertSame(7, $_SESSION['auth_user_id']);
        self::assertSame('TestUser', $_SESSION['auth_username']);
    }

    #[DataProvider('allowedHostProvider')]
    public function testSetsSessionWhenAllConditionsMet(string $host): void
    {
        $_SERVER['SERVER_NAME'] = $host;
        $_COOKIE['_auto_login'] = '1';
        putenv('DEV_AUTO_LOGIN=TestUser');

        $result = static::createStub(\mysqli_result::class);
        $result->method('fetch_assoc')->willReturn(['user_id' => 99]);

        $stmt = static::createStub(\mysqli_stmt::class);
        $stmt->method('get_result')->willReturn($result);

        $db = static::createStub(\mysqli::class);
        $db->method('prepare')->willReturn($stmt);

        (new DevAutoLogin())->tryAutoLogin($db);

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
            'worktree subdomain' => ['dev-auto-login.localhost'],
        ];
    }

    public function testDoesNothingWhenUserNotFoundInDatabase(): void
    {
        $_SERVER['SERVER_NAME'] = 'localhost';
        $_COOKIE['_auto_login'] = '1';
        putenv('DEV_AUTO_LOGIN=NonExistentUser');

        $result = static::createStub(\mysqli_result::class);
        $result->method('fetch_assoc')->willReturn(null);

        $stmt = static::createStub(\mysqli_stmt::class);
        $stmt->method('get_result')->willReturn($result);

        $db = static::createStub(\mysqli::class);
        $db->method('prepare')->willReturn($stmt);

        (new DevAutoLogin())->tryAutoLogin($db);

        self::assertArrayNotHasKey('auth_user_id', $_SESSION);
    }

    public function testDoesNothingWhenPrepareFailsReturningFalse(): void
    {
        $_SERVER['SERVER_NAME'] = 'localhost';
        $_COOKIE['_auto_login'] = '1';
        putenv('DEV_AUTO_LOGIN=TestUser');

        $db = static::createStub(\mysqli::class);
        $db->method('prepare')->willReturn(false);

        (new DevAutoLogin())->tryAutoLogin($db);

        self::assertArrayNotHasKey('auth_user_id', $_SESSION);
    }

    public function testInjectedLoggerReceivesActivationLog(): void
    {
        $_SERVER['SERVER_NAME'] = 'localhost';
        $_COOKIE['_auto_login'] = '1';
        putenv('DEV_AUTO_LOGIN=TestUser');

        $result = static::createStub(\mysqli_result::class);
        $result->method('fetch_assoc')->willReturn(['user_id' => 99]);

        $stmt = static::createStub(\mysqli_stmt::class);
        $stmt->method('get_result')->willReturn($result);

        $db = static::createStub(\mysqli::class);
        $db->method('prepare')->willReturn($stmt);

        $spy = $this->createMock(\Psr\Log\LoggerInterface::class);
        $spy->expects(self::once())
            ->method('debug')
            ->with('Dev auto-login activated', ['username' => 'TestUser']);

        (new DevAutoLogin($spy))->tryAutoLogin($db);

        // Seam proof: the injected logger received the activation log in place of
        // the static getChannel('auth') factory (and the session was still set).
        self::assertSame(99, $_SESSION['auth_user_id']);
        self::assertSame('TestUser', $_SESSION['auth_username']);
    }

    public function testNoArgConstructorFallsBackToAuthChannelAndStillActivates(): void
    {
        $_SERVER['SERVER_NAME'] = 'localhost';
        $_COOKIE['_auto_login'] = '1';
        putenv('DEV_AUTO_LOGIN=TestUser');

        $result = static::createStub(\mysqli_result::class);
        $result->method('fetch_assoc')->willReturn(['user_id' => 99]);

        $stmt = static::createStub(\mysqli_stmt::class);
        $stmt->method('get_result')->willReturn($result);

        $db = static::createStub(\mysqli::class);
        $db->method('prepare')->willReturn($stmt);

        // No logger arg — the ?? getChannel('auth') fallback must fire (the production
        // AuthBootstrap form) without a TypeError, and the session writes are unchanged.
        (new DevAutoLogin())->tryAutoLogin($db);

        self::assertSame(99, $_SESSION['auth_user_id']);
        self::assertSame('TestUser', $_SESSION['auth_username']);
    }
}
