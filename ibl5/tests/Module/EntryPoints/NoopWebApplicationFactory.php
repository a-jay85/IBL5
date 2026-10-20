<?php

declare(strict_types=1);

namespace Tests\Module\EntryPoints;

/**
 * Stand-in for Bootstrap\WebApplicationFactory in tests that include a file which
 * requires mainfile.php (modules/Player/extension.php). The real factory boots
 * the config, database, and auth stack, which would overwrite the test's auth stub.
 * This one returns an Application with no steps.
 *
 * @see ModuleEntryPointTestCase::skipWebBoot()
 */
final class NoopWebApplicationFactory
{
    public static function build(string $basePath): \Bootstrap\Application
    {
        return new \Bootstrap\Application();
    }
}
