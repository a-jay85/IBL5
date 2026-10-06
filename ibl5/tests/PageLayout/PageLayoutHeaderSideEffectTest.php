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

        $_SERVER['HTTP_HX_BOOSTED'] = 'true';
        $GLOBALS['sitename'] = 'IBL';
        $GLOBALS['pagetitle'] = 'Test Page';
        unset($_SESSION['flash_success']);
    }

    protected function tearDown(): void
    {
        unset(
            $GLOBALS['authService'],
            $GLOBALS['cookie'],
            $GLOBALS['user'],
            $GLOBALS['sitename'],
            $GLOBALS['pagetitle'],
            $_SERVER['HTTP_HX_BOOSTED'],
        );
    }

    public function testCookieDecodePopulatesGlobalCookieFromAuthService(): void
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

    public function testBoostedHeaderNeverResolvesIdentity(): void
    {
        // header() must not reach AuthServiceInterface::getCookieArray() via cookiedecode().
        $auth = $this->createMock(AuthServiceInterface::class);
        $auth->expects(self::never())->method('getCookieArray');
        $GLOBALS['authService'] = $auth;
        $GLOBALS['user'] = base64_encode('1:testuser:0:email@test.com');
        unset($GLOBALS['cookie']);

        ob_start();
        \PageLayout\PageLayout::header();
        ob_end_clean();

        self::assertArrayNotHasKey('cookie', $GLOBALS);
    }

    public function testBoostedHeaderLeavesPreexistingCookieGlobalUntouched(): void
    {
        $auth = self::createStub(AuthServiceInterface::class);
        $auth->method('getCookieArray')->willReturn([2, 'otheruser', '0', 'x@test.com']);
        $auth->method('isAuthenticated')->willReturn(true);
        $GLOBALS['authService'] = $auth;
        $GLOBALS['user'] = base64_encode('2:otheruser:0:x@test.com');
        $GLOBALS['cookie'] = ['sentinel'];

        ob_start();
        \PageLayout\PageLayout::header();
        ob_end_clean();

        self::assertSame(['sentinel'], $GLOBALS['cookie']);
    }

    public function testBoostedHeaderStillRendersTitle(): void
    {
        $auth = self::createStub(AuthServiceInterface::class);
        $auth->method('getCookieArray')->willReturn([2, 'otheruser', '0', 'x@test.com']);
        $auth->method('isAuthenticated')->willReturn(true);
        $GLOBALS['authService'] = $auth;
        $GLOBALS['user'] = base64_encode('2:otheruser:0:x@test.com');

        ob_start();
        \PageLayout\PageLayout::header();
        $html = (string) ob_get_clean();

        self::assertStringContainsString('<title>IBL Test Page</title>', $html);
    }
}
