<?php

declare(strict_types=1);

namespace Bootstrap;

use Api\Response\JsonResponder;
use Api\Router;
use Bootstrap\Contracts\BootstrapStepInterface;
use Bootstrap\Contracts\ContainerInterface;

/**
 * Matches the API route and stores the controller class + params in the container.
 *
 * On no match, sends 404 and terminates.
 */
class ApiRoutingBootstrap implements BootstrapStepInterface
{
    /**
     * @see BootstrapStepInterface::boot()
     */
    public function boot(ContainerInterface $container): void
    {
        /** @var string $route */
        $route = $container->get('api.route');
        /** @var string $method */
        $method = $container->get('api.method');

        $router = new Router();
        $match = $router->match($route, $method);

        if ($match === null) {
            /** @var JsonResponder $responder */
            $responder = $container->get('api.responder');
            $responder->error(404, 'not_found', 'The requested endpoint does not exist.');
            $container->set('app.terminated', true);
            return;
        }

        $container->set('api.route.controller', $match['controller']);
        $container->set('api.route.params', $match['params']);
    }
}
