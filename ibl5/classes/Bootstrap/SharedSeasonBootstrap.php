<?php

declare(strict_types=1);

namespace Bootstrap;

use Bootstrap\Contracts\BootstrapStepInterface;
use Bootstrap\Contracts\ContainerInterface;

/**
 * Arms the request-scoped Season memo (Season\SharedSeason) for web and API
 * requests. CLI scripts also boot through mainfile.php; for them this step is
 * a no-op so every `SharedSeason::get()` stays a fresh build.
 *
 * Exact compare: the PHP built-in server (router.php under `php -S`) reports
 * 'cli-server' and serves real web requests, so it must arm.
 */
class SharedSeasonBootstrap implements BootstrapStepInterface
{
    public function __construct(private readonly string $sapi = PHP_SAPI)
    {
    }

    /**
     * @see BootstrapStepInterface::boot()
     */
    public function boot(ContainerInterface $container): void
    {
        if ($this->sapi === 'cli') {
            return;
        }

        \Season\SharedSeason::enable();
    }
}
