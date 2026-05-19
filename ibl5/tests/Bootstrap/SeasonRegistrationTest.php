<?php

declare(strict_types=1);

namespace Tests\Bootstrap;

use Bootstrap\ConfigBootstrap;
use PHPUnit\Framework\TestCase;

final class SeasonRegistrationTest extends TestCase
{
    public function testRegisterSharedServicesRegistersExpectedKeys(): void
    {
        $container = new \Bootstrap\Container();

        $reflection = new \ReflectionMethod(ConfigBootstrap::class, 'registerSharedServices');
        $bootstrap = new ConfigBootstrap(__DIR__ . '/../../', false);
        $reflection->invoke($bootstrap, $container);

        self::assertTrue($container->has('season'));
        self::assertTrue($container->has('mysqli_db'));
        self::assertTrue($container->has('logger.app'));
        self::assertTrue($container->has('logger.audit'));
        self::assertTrue($container->has('logger.db'));
        self::assertTrue($container->has('logger.discord'));
        self::assertTrue($container->has('logger.draft'));
        self::assertTrue($container->has('logger.admin'));
        self::assertTrue($container->has('logger.perf'));
    }
}
