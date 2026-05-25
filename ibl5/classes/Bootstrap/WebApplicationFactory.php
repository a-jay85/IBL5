<?php

declare(strict_types=1);

namespace Bootstrap;

use Discord\Discord;

/**
 * Composition root for web (mainfile.php) bootstrap.
 *
 * Step order mirrors the original mainfile.php procedural sequence.
 */
final class WebApplicationFactory
{
    public static function build(string $basePath): Application
    {
        // AutoloaderBootstrap is NOT included here — the Composer autoloader
        // must already be loaded before this factory class can be resolved.
        // mainfile.php handles autoloader setup inline before calling build().
        $serverName = $_SERVER['SERVER_NAME'] ?? '';
        Discord::init(is_string($serverName) ? $serverName : '');
        $app = new Application();
        $app->addStep(new SecurityBootstrap());
        $app->addStep(new SessionBootstrap());
        $app->addStep(new HeadersBootstrap());
        $app->addStep(new LeagueBootstrap());
        $app->addStep(new ConfigBootstrap($basePath));
        $app->addStep(new AuthBootstrap($basePath));
        $app->addStep(new DemoModeBootstrap($basePath));
        return $app;
    }
}
