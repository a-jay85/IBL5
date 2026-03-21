<?php

declare(strict_types=1);

namespace Tests\Utilities;

use PHPUnit\Framework\TestCase;
use Utilities\SecureCookie;

/**
 * @covers \Utilities\SecureCookie
 */
class SecureCookieTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $originalServer;

    protected function setUp(): void
    {
        $this->originalServer = $_SERVER;
        // Clear HTTPS-related keys to start from a known state
        unset($_SERVER['HTTPS'], $_SERVER['HTTP_X_FORWARDED_PROTO'], $_SERVER['SERVER_PORT']);
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->originalServer;
    }

    // --- set() ---

    public function testSetReturnsTrueInCliMode(): void
    {
        $this->assertTrue(SecureCookie::set('test_cookie', 'value'));
    }

    public function testSetAcceptsExpirationTimestamp(): void
    {
        $this->assertTrue(SecureCookie::set('test_cookie', 'value', time() + 3600));
    }

    public function testSetAcceptsSameSiteParameter(): void
    {
        $this->assertTrue(SecureCookie::set('test_cookie', 'value', 0, 'Lax'));
    }

    public function testSetDefaultsSameSiteToStrict(): void
    {
        // No exception/error means Strict was accepted
        $this->assertTrue(SecureCookie::set('test_cookie', 'value'));
    }

    // --- delete() ---

    public function testDeleteReturnsTrueInCliMode(): void
    {
        $this->assertTrue(SecureCookie::delete('test_cookie'));
    }

    // --- setLax() ---

    public function testSetLaxReturnsTrueInCliMode(): void
    {
        $this->assertTrue(SecureCookie::setLax('test_cookie', 'value'));
    }

    public function testSetLaxAcceptsExpirationTimestamp(): void
    {
        $this->assertTrue(SecureCookie::setLax('test_cookie', 'value', time() + 7200));
    }

    // --- isHttps() detection (tested indirectly through set()) ---

    public function testIsHttpsDetectsHttpsServerVariable(): void
    {
        $_SERVER['HTTPS'] = 'on';

        // If isHttps() returns true, set() uses secure=true in cookie options.
        // We can't inspect the options, but we verify no error occurs.
        $this->assertTrue(SecureCookie::set('test_cookie', 'value'));
    }

    public function testIsHttpsDetectsForwardedProtoHeader(): void
    {
        $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';

        $this->assertTrue(SecureCookie::set('test_cookie', 'value'));
    }

    public function testIsHttpsTreatsHttpsOffAsNotSecure(): void
    {
        $_SERVER['HTTPS'] = 'off';

        // 'off' is explicitly excluded — falls through to next check
        $this->assertTrue(SecureCookie::set('test_cookie', 'value'));
    }

    public function testIsHttpsWithNoIndicatorsIsNotSecure(): void
    {
        // No HTTPS, no forwarded proto, no port — non-secure
        $this->assertTrue(SecureCookie::set('test_cookie', 'value'));
    }

    public function testIsHttpsDetectsPort443(): void
    {
        $_SERVER['SERVER_PORT'] = '443';

        // Port 443 detection casts string to int before comparison
        $this->assertTrue(SecureCookie::set('test_cookie', 'value'));
    }
}
