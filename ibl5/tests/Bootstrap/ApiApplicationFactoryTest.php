<?php

declare(strict_types=1);

namespace Tests\Bootstrap;

use Bootstrap\ApiApplicationFactory;
use Bootstrap\ApiDispatchBootstrap;
use Bootstrap\ApiKeyAuthBootstrap;
use Bootstrap\ApiRoutingBootstrap;
use Bootstrap\ConfigBootstrap;
use Bootstrap\CorsBootstrap;
use Bootstrap\RateLimitingBootstrap;
use Bootstrap\RequestParsingBootstrap;
use PHPUnit\Framework\TestCase;

final class ApiApplicationFactoryTest extends TestCase
{
    public function testBuildReturnsApplicationWithAllSteps(): void
    {
        $app = ApiApplicationFactory::build(__DIR__ . '/../../');

        $reflection = new \ReflectionProperty($app, 'steps');
        /** @var list<\Bootstrap\Contracts\BootstrapStepInterface> $steps */
        $steps = $reflection->getValue($app);

        self::assertCount(7, $steps);
        self::assertInstanceOf(CorsBootstrap::class, $steps[0]);
        self::assertInstanceOf(ConfigBootstrap::class, $steps[1]);
        self::assertInstanceOf(RequestParsingBootstrap::class, $steps[2]);
        self::assertInstanceOf(ApiKeyAuthBootstrap::class, $steps[3]);
        self::assertInstanceOf(RateLimitingBootstrap::class, $steps[4]);
        self::assertInstanceOf(ApiRoutingBootstrap::class, $steps[5]);
        self::assertInstanceOf(ApiDispatchBootstrap::class, $steps[6]);
    }

    public function testBuildRegistersResponderInContainer(): void
    {
        $app = ApiApplicationFactory::build(__DIR__ . '/../../');
        $container = $app->getContainer();

        self::assertTrue($container->has('api.responder'));
        self::assertInstanceOf(\Api\Response\JsonResponder::class, $container->get('api.responder'));
    }

    public function testBuildRegistersMysqliFactory(): void
    {
        $app = ApiApplicationFactory::build(__DIR__ . '/../../');
        $container = $app->getContainer();

        self::assertTrue($container->has(\mysqli::class));
    }
}
