<?php

declare(strict_types=1);

namespace Bootstrap;

use Api\Contracts\ControllerInterface;
use Api\Response\JsonResponder;
use Bootstrap\Contracts\BootstrapStepInterface;
use Bootstrap\Contracts\ContainerInterface;

/**
 * Resolves and dispatches the matched API controller.
 *
 * Controller resolution uses the 'api.controllerFactory' callable registered
 * by ApiApplicationFactory, which handles dependency injection per controller.
 */
class ApiDispatchBootstrap implements BootstrapStepInterface
{
    /**
     * @see BootstrapStepInterface::boot()
     */
    public function boot(ContainerInterface $container): void
    {
        /** @var class-string<ControllerInterface> $controllerClass */
        $controllerClass = $container->get('api.route.controller');
        /** @var array<string, string> $params */
        $params = $container->get('api.route.params');
        /** @var JsonResponder $responder */
        $responder = $container->get('api.responder');
        /** @var array<string, mixed>|null $body */
        $body = $container->get('api.body');

        /** @var callable(class-string<ControllerInterface>): ControllerInterface $factory */
        $factory = $container->get('api.controllerFactory');
        $controller = $factory($controllerClass);

        /** @var array<string, string> $query */
        $query = $_GET;
        $controller->handle($params, $query, $responder, $body);
    }
}
