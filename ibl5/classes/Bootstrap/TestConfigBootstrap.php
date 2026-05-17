<?php

declare(strict_types=1);

namespace Bootstrap;

use Bootstrap\Contracts\BootstrapStepInterface;
use Bootstrap\Contracts\ContainerInterface;

class TestConfigBootstrap implements BootstrapStepInterface
{
    private string $basePath;

    public function __construct(string $basePath)
    {
        $this->basePath = $basePath;
    }

    /**
     * @see BootstrapStepInterface::boot()
     */
    public function boot(ContainerInterface $container): void
    {
        \Logging\LoggerFactory::forTests();

        if (!isset($_SERVER['SERVER_NAME'])) {
            $_SERVER['SERVER_NAME'] = 'localhost';
        }
        if (!isset($_SERVER['SCRIPT_FILENAME'])) {
            $_SERVER['SCRIPT_FILENAME'] = $this->basePath . '/index.php';
        }

        /** @phpstan-ignore ibl.requireOnce (loads application config for test environment) */
        require_once $this->basePath . '/config.php';
    }
}
