<?php

declare(strict_types=1);

namespace Bootstrap;

use Api\Middleware\ApiKeyAuthenticator;
use Api\Repository\ApiKeyRepository;
use Api\Response\JsonResponder;
use Bootstrap\Contracts\BootstrapStepInterface;
use Bootstrap\Contracts\ContainerInterface;

/**
 * Authenticates the API request via X-API-Key header or ?key= query param.
 *
 * On failure, sends 401 and terminates the pipeline.
 */
class ApiKeyAuthBootstrap implements BootstrapStepInterface
{
    /**
     * @see BootstrapStepInterface::boot()
     */
    public function boot(ContainerInterface $container): void
    {
        /** @var \mysqli $db */
        $db = $container->get(\mysqli::class);

        $authenticator = new ApiKeyAuthenticator(new ApiKeyRepository($db));
        $apiKey = $authenticator->authenticate();

        if ($apiKey === null) {
            /** @var JsonResponder $responder */
            $responder = $container->get('api.responder');
            $responder->error(401, 'unauthorized', 'Missing or invalid API key. Include X-API-Key header.');
            $container->set('app.terminated', true);
            return;
        }

        $container->set('api.key', $apiKey);
    }
}
