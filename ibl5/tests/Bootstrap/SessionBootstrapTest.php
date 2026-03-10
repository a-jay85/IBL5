<?php

declare(strict_types=1);

namespace Tests\Bootstrap;

use Bootstrap\SessionBootstrap;
use PHPUnit\Framework\TestCase;

final class SessionBootstrapTest extends TestCase
{
    public function testDetectHttpsReturnsTrueForHttpsOn(): void
    {
        $original = $_SERVER;
        $_SERVER['HTTPS'] = 'on';

        self::assertTrue(SessionBootstrap::detectHttps());

        $_SERVER = $original;
    }

    public function testDetectHttpsReturnsTrueForForwardedProto(): void
    {
        $original = $_SERVER;
        unset($_SERVER['HTTPS']);
        $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';

        self::assertTrue(SessionBootstrap::detectHttps());

        $_SERVER = $original;
    }

    public function testDetectHttpsReturnsTrueForPort443(): void
    {
        $original = $_SERVER;
        unset($_SERVER['HTTPS'], $_SERVER['HTTP_X_FORWARDED_PROTO']);
        $_SERVER['SERVER_PORT'] = 443;

        self::assertTrue(SessionBootstrap::detectHttps());

        $_SERVER = $original;
    }

    public function testDetectHttpsReturnsFalseForHttp(): void
    {
        $original = $_SERVER;
        unset($_SERVER['HTTPS'], $_SERVER['HTTP_X_FORWARDED_PROTO']);
        $_SERVER['SERVER_PORT'] = 80;

        self::assertFalse(SessionBootstrap::detectHttps());

        $_SERVER = $original;
    }

    public function testDetectHttpsReturnsFalseForHttpsOff(): void
    {
        $original = $_SERVER;
        $_SERVER['HTTPS'] = 'off';
        unset($_SERVER['HTTP_X_FORWARDED_PROTO']);
        $_SERVER['SERVER_PORT'] = 80;

        self::assertFalse(SessionBootstrap::detectHttps());

        $_SERVER = $original;
    }
}
