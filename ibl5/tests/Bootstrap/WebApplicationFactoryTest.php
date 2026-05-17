<?php

declare(strict_types=1);

namespace Tests\Bootstrap;

use Bootstrap\AuthBootstrap;
use Bootstrap\ConfigBootstrap;
use Bootstrap\DemoModeBootstrap;
use Bootstrap\HeadersBootstrap;
use Bootstrap\LeagueBootstrap;
use Bootstrap\SecurityBootstrap;
use Bootstrap\SessionBootstrap;
use Bootstrap\WebApplicationFactory;
use PHPUnit\Framework\TestCase;

final class WebApplicationFactoryTest extends TestCase
{
    public function testBuildReturnsApplicationWithAllSteps(): void
    {
        $app = WebApplicationFactory::build(__DIR__ . '/../../');

        $reflection = new \ReflectionProperty($app, 'steps');
        /** @var list<\Bootstrap\Contracts\BootstrapStepInterface> $steps */
        $steps = $reflection->getValue($app);

        self::assertCount(7, $steps);
        self::assertInstanceOf(SecurityBootstrap::class, $steps[0]);
        self::assertInstanceOf(SessionBootstrap::class, $steps[1]);
        self::assertInstanceOf(HeadersBootstrap::class, $steps[2]);
        self::assertInstanceOf(LeagueBootstrap::class, $steps[3]);
        self::assertInstanceOf(ConfigBootstrap::class, $steps[4]);
        self::assertInstanceOf(AuthBootstrap::class, $steps[5]);
        self::assertInstanceOf(DemoModeBootstrap::class, $steps[6]);
    }
}
