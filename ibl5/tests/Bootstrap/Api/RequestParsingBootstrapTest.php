<?php

declare(strict_types=1);

namespace Tests\Bootstrap\Api;

use Api\Response\JsonResponder;
use Bootstrap\Container;
use Bootstrap\RequestParsingBootstrap;
use PHPUnit\Framework\TestCase;

final class RequestParsingBootstrapTest extends TestCase
{
    private Container $container;

    protected function setUp(): void
    {
        $this->container = new Container();
        $this->container->set('api.responder', $this->createStub(JsonResponder::class));
    }

    public function testGetRequestSetsMethodAndRoute(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET['route'] = 'teams';

        $step = new RequestParsingBootstrap();
        $step->boot($this->container);

        self::assertSame('GET', $this->container->get('api.method'));
        self::assertSame('teams', $this->container->get('api.route'));
        self::assertNull($this->container->get('api.body'));
        self::assertFalse($this->container->has('app.terminated'));
    }

    public function testUnsupportedMethodTerminates(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'DELETE';

        $step = new RequestParsingBootstrap();
        $step->boot($this->container);

        self::assertTrue($this->container->has('app.terminated'));
    }

    public function testPutMethodTerminates(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'PUT';

        $step = new RequestParsingBootstrap();
        $step->boot($this->container);

        self::assertTrue($this->container->has('app.terminated'));
    }

    public function testPatchMethodTerminates(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'PATCH';

        $step = new RequestParsingBootstrap();
        $step->boot($this->container);

        self::assertTrue($this->container->has('app.terminated'));
    }

    public function testMissingRouteDefaultsToEmpty(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        unset($_GET['route']);

        $step = new RequestParsingBootstrap();
        $step->boot($this->container);

        self::assertSame('', $this->container->get('api.route'));
    }

    protected function tearDown(): void
    {
        unset($_SERVER['REQUEST_METHOD'], $_GET['route']);
    }
}
