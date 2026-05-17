<?php

declare(strict_types=1);

namespace Bootstrap;

use Api\Response\JsonResponder;
use Bootstrap\Contracts\BootstrapStepInterface;
use Bootstrap\Contracts\ContainerInterface;

/**
 * Extracts HTTP method, route, and body from the request for API dispatch.
 *
 * Validates allowed methods (GET, POST) and parses JSON body for POST requests.
 */
class RequestParsingBootstrap implements BootstrapStepInterface
{
    /**
     * @see BootstrapStepInterface::boot()
     */
    public function boot(ContainerInterface $container): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $route = $_GET['route'] ?? '';

        if ($method !== 'GET' && $method !== 'POST') {
            /** @var JsonResponder $responder */
            $responder = $container->get('api.responder');
            $responder->error(405, 'method_not_allowed', 'Only GET and POST requests are supported.');
            $container->set('app.terminated', true);
            return;
        }

        $body = null;
        if ($method === 'POST') {
            $rawBody = file_get_contents('php://input');
            if ($rawBody !== false && $rawBody !== '') {
                $body = json_decode($rawBody, true);
                if (!is_array($body)) {
                    /** @var JsonResponder $responder */
                    $responder = $container->get('api.responder');
                    $responder->error(400, 'bad_request', 'Request body must be valid JSON.');
                    $container->set('app.terminated', true);
                    return;
                }
            }
        }

        $container->set('api.method', $method);
        $container->set('api.route', is_string($route) ? $route : '');
        $container->set('api.body', $body);
    }
}
