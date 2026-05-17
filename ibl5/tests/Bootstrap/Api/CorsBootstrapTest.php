<?php

declare(strict_types=1);

namespace Tests\Bootstrap\Api;

use Bootstrap\Container;
use Bootstrap\CorsBootstrap;
use PHPUnit\Framework\TestCase;

final class CorsBootstrapTest extends TestCase
{
    public function testOptionsRequestSignalsTermination(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'OPTIONS';
        $container = new Container();
        $step = new CorsBootstrap();

        $step->boot($container);

        self::assertTrue($container->has('app.terminated'));
    }

    public function testNonOptionsRequestDoesNotTerminate(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $container = new Container();
        $step = new CorsBootstrap();

        $step->boot($container);

        self::assertFalse($container->has('app.terminated'));
    }

    protected function tearDown(): void
    {
        unset($_SERVER['REQUEST_METHOD']);
    }
}
