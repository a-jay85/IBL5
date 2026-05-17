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

        $this->step->boot($this->container);

        $this->expectNotToPerformAssertions();
    }

    public function testDoesNothingWhenDemoModeIsFalse(): void
    {
        $_SESSION['demo_mode'] = false;
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $this->step->boot($this->container);

        $this->expectNotToPerformAssertions();
    }

    public function testDoesNothingOnGetRequestWithDemoMode(): void
    {
        $_SESSION['demo_mode'] = true;
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $this->step->boot($this->container);

        $this->expectNotToPerformAssertions();
    }
}
