<?php

declare(strict_types=1);

namespace Tests\Bootstrap\Api;

use Api\Controller\TeamListController;
use Api\Response\JsonResponder;
use Bootstrap\ApiRoutingBootstrap;
use Bootstrap\Container;
use PHPUnit\Framework\TestCase;

final class ApiRoutingBootstrapTest extends TestCase
{
    public function testValidRouteStoresControllerAndParams(): void
    {
        $container = new Container();
        $container->set('api.responder', self::createStub(JsonResponder::class));
        $container->set('api.route', 'teams');
        $container->set('api.method', 'GET');

        $step = new ApiRoutingBootstrap();
        $step->boot($container);

        self::assertFalse($container->has('app.terminated'));
        self::assertSame(TeamListController::class, $container->get('api.route.controller'));
        self::assertIsArray($container->get('api.route.params'));
    }

    public function testUnknownRouteTerminates(): void
    {
        $container = new Container();
        $container->set('api.responder', self::createStub(JsonResponder::class));
        $container->set('api.route', 'nonexistent/endpoint');
        $container->set('api.method', 'GET');

        $step = new ApiRoutingBootstrap();
        $step->boot($container);

        self::assertTrue($container->has('app.terminated'));
    }
}
