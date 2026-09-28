<?php

declare(strict_types=1);

namespace Tests\Bootstrap;

use Bootstrap\Container;
use Bootstrap\DemoModeBootstrap;
use PHPUnit\Framework\TestCase;

final class DemoModeBootstrapTest extends TestCase
{
    private DemoModeBootstrap $step;
    private Container $container;

    protected function setUp(): void
    {
        $this->step = new DemoModeBootstrap(__DIR__ . '/../../');
        $this->container = new Container();
        $_SESSION = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
    }

    public function testDoesNothingWhenDemoModeNotSet(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $obLevel = ob_get_level();

        $this->step->boot($this->container);

        // demo_mode guard fires — no output buffers drained, no session key written
        self::assertSame($obLevel, ob_get_level(), 'boot() must not drain output buffers when demo_mode is unset');
        self::assertArrayNotHasKey('demo_mode', $_SESSION, 'boot() must not write a demo_mode session key');
    }

    public function testDoesNothingWhenDemoModeIsFalse(): void
    {
        $_SESSION['demo_mode'] = false;
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $obLevel = ob_get_level();

        $this->step->boot($this->container);

        // demo_mode guard fires — no output buffers drained, demo_mode stays false
        self::assertSame($obLevel, ob_get_level(), 'boot() must not drain output buffers when demo_mode is false');
        self::assertFalse($_SESSION['demo_mode'], 'boot() must not alter the demo_mode session value');
    }

    public function testDoesNothingOnGetRequestWithDemoMode(): void
    {
        $_SESSION['demo_mode'] = true;
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $obLevel = ob_get_level();

        $this->step->boot($this->container);

        // POST guard fires — no output buffers drained, demo_mode stays true
        self::assertSame($obLevel, ob_get_level(), 'boot() must not drain output buffers on GET request');
        self::assertTrue($_SESSION['demo_mode'], 'boot() must not alter the demo_mode session value');
    }
}
