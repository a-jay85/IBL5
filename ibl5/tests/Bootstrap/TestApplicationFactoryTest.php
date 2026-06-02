<?php

declare(strict_types=1);

namespace Tests\Bootstrap;

use Bootstrap\TestApplicationFactory;
use PHPUnit\Framework\TestCase;

final class TestApplicationFactoryTest extends TestCase
{
    public function testBuildRegistersThreeSteps(): void
    {
        $app = TestApplicationFactory::build(__DIR__ . '/../..');

        $reflection = new \ReflectionClass($app);
        $prop = $reflection->getProperty('steps');
        $steps = $prop->getValue($app);

        self::assertCount(3, $steps);
        self::assertInstanceOf(\Bootstrap\TestEnvironmentBootstrap::class, $steps[0]);
        self::assertInstanceOf(\Bootstrap\TestAliasesBootstrap::class, $steps[1]);
        self::assertInstanceOf(\Bootstrap\TestConfigBootstrap::class, $steps[2]);
    }
}
