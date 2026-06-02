<?php

declare(strict_types=1);

namespace Tests\PageLayout;

use Auth\Contracts\AuthServiceInterface;
use PHPUnit\Framework\TestCase;

final class PageLayoutHeaderSideEffectTest extends TestCase
{
    protected function setUp(): void
    {
        require_once __DIR__ . '/../../classes/Bootstrap/LegacyFunctions.php';
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['authService'], $GLOBALS['cookie'], $GLOBALS['user']);
    }

    public function testHeaderPopulatesGlobalCookieFromAuthService(): void
    {
        $expectedCookie = [1, 'testuser', '0', 'email@test.com'];

        $mockAuth = self::createStub(AuthServiceInterface::class);
        $mockAuth->method('getCookieArray')->willReturn($expectedCookie);
        $mockAuth->method('isAuthenticated')->willReturn(true);
        $GLOBALS['authService'] = $mockAuth;
        $GLOBALS['user'] = base64_encode('1:testuser:0:email@test.com');

        cookiedecode($GLOBALS['user']);

        self::assertSame($expectedCookie, $GLOBALS['cookie']);
    }

    public function testCookieDecodeReturnsNullForUnauthenticatedUser(): void
    {
        $mockAuth = self::createStub(AuthServiceInterface::class);
        $mockAuth->method('getCookieArray')->willReturn(null);
        $mockAuth->method('isAuthenticated')->willReturn(false);
        $GLOBALS['authService'] = $mockAuth;

        $result = cookiedecode('');

        self::assertNull($result);
    }
}
