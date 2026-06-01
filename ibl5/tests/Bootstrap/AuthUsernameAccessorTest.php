<?php

declare(strict_types=1);

namespace Tests\Bootstrap;

use Auth\Contracts\AuthServiceInterface;
use Bootstrap\Container;
use PHPUnit\Framework\TestCase;

final class AuthUsernameAccessorTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($GLOBALS['authService']);
    }

    public function testAuthUsernameResolvesToUsernameWhenAuthenticated(): void
    {
        $mockAuth = self::createStub(AuthServiceInterface::class);
        $mockAuth->method('getUsername')->willReturn('testuser');

        $container = new Container();
        $container->set('authService', $mockAuth);
        $container->set('auth.username', static fn (): string => $mockAuth->getUsername() ?? '');

        self::assertSame('testuser', $container->get('auth.username'));
    }

    public function testAuthUsernameResolvesToEmptyStringWhenNotAuthenticated(): void
    {
        $mockAuth = self::createStub(AuthServiceInterface::class);
        $mockAuth->method('getUsername')->willReturn(null);

        $container = new Container();
        $container->set('authService', $mockAuth);
        $container->set('auth.username', static fn (): string => $mockAuth->getUsername() ?? '');

        self::assertSame('', $container->get('auth.username'));
    }
}
