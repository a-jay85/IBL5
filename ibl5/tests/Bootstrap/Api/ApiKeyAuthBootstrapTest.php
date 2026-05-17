<?php

declare(strict_types=1);

namespace Tests\Bootstrap\Api;

use Api\Response\JsonResponder;
use Bootstrap\ApiKeyAuthBootstrap;
use Bootstrap\Container;
use PHPUnit\Framework\TestCase;

final class ApiKeyAuthBootstrapTest extends TestCase
{
    public function testMissingApiKeyTerminates(): void
    {
        unset($_SERVER['HTTP_X_API_KEY']);
        $_GET['key'] = '';

        $container = new Container();
        $container->set('api.responder', $this->createStub(JsonResponder::class));
        $container->set(\mysqli::class, $this->createStub(\mysqli::class));

        $step = new ApiKeyAuthBootstrap();
        $step->boot($container);

        self::assertTrue($container->has('app.terminated'));
        self::assertFalse($container->has('api.key'));
    }

    protected function tearDown(): void
    {
        unset($_SERVER['HTTP_X_API_KEY'], $_GET['key']);
    }
}
