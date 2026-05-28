<?php

declare(strict_types=1);

namespace Tests\Bootstrap;

use Auth\Contracts\AuthServiceInterface;
use PHPUnit\Framework\TestCase;

final class CookieDecodeTest extends TestCase
{
    protected function setUp(): void
    {
        require_once __DIR__ . '/../../classes/Bootstrap/LegacyFunctions.php';
    }

    public function testCookieDecodeReturnsArrayWhenAuthenticated(): void
    {
        $mockAuth = $this->createStub(AuthServiceInterface::class);
        $mockAuth->method('getCookieArray')->willReturn([1, 'testuser', '0', 'email@test.com']);

        $GLOBALS['authService'] = $mockAuth;

        $result = cookiedecode('ignored');
        self::assertIsArray($result);
        self::assertSame(1, $result[0]);
        self::assertSame('testuser', $result[1]);

        unset($GLOBALS['authService']);
    }

    public function testCookieDecodeReturnsNullWhenNotAuthenticated(): void
    {
        $mockAuth = $this->createStub(AuthServiceInterface::class);
        $mockAuth->method('getCookieArray')->willReturn(null);

        $GLOBALS['authService'] = $mockAuth;

        $result = cookiedecode('ignored');
        self::assertNull($result);

        unset($GLOBALS['authService']);
    }

    public function testCookieDecodeSetsGlobalCookieArray(): void
    {
        $expected = [1, 'testuser', '0', 'email@test.com'];
        $mockAuth = $this->createStub(AuthServiceInterface::class);
        $mockAuth->method('getCookieArray')->willReturn($expected);

        $GLOBALS['authService'] = $mockAuth;

        cookiedecode('ignored');
        self::assertSame($expected, $GLOBALS['cookie']);

        unset($GLOBALS['authService'], $GLOBALS['cookie']);
    }
}
