<?php

declare(strict_types=1);

namespace Bootstrap;

use Api\Response\JsonResponder;
use Discord\Discord;

/**
 * Composition root for API (api.php) bootstrap.
 *
 * Step order: CORS → config/db → request parsing → auth → rate limit → routing → dispatch.
 * AutoloaderBootstrap is NOT included — the Composer autoloader must already be
 * loaded before this factory class can be resolved.
 */
final class ApiApplicationFactory
{
    public static function build(string $basePath): Application
    {
        $serverName = $_SERVER['SERVER_NAME'] ?? '';
        Discord::init(is_string($serverName) ? $serverName : '');
        $app = new Application();
        $container = $app->getContainer();

        // Pre-register the shared JsonResponder so steps can emit error responses
        $container->set('api.responder', new JsonResponder());

        // Lazy-resolve the DB connection (available after ConfigBootstrap runs)
        $container->set(\mysqli::class, static function (): \mysqli {
            /** @var \mysqli $db */
            $db = $GLOBALS['mysqli_db'];
            return $db;
        });

        $app->addStep(new CorsBootstrap());
        $app->addStep(new ConfigBootstrap($basePath, false));
        $app->addStep(new ErrorHandlerBootstrap(ErrorHandlerBootstrap::MODE_API));
        $app->addStep(new RequestParsingBootstrap());
        $app->addStep(new ApiKeyAuthBootstrap());
        $app->addStep(new RateLimitingBootstrap());
        $app->addStep(new ApiRoutingBootstrap());
        $app->addStep(new ApiDispatchBootstrap());

        return $app;
    }
}
