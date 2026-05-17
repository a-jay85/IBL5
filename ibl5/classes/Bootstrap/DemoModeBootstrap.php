<?php

declare(strict_types=1);

namespace Bootstrap;

use Bootstrap\Contracts\BootstrapStepInterface;
use Bootstrap\Contracts\ContainerInterface;

/** Demo mode: block all state-mutating requests with a user-friendly page. */
class DemoModeBootstrap implements BootstrapStepInterface
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
        if (($_SESSION['demo_mode'] ?? false) !== true) {
            return;
        }

        $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if (!is_string($requestMethod) || $requestMethod !== 'POST') {
            return;
        }

        // Flush output buffers since ob_start('ob_gzhandler') was called earlier
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        /** @phpstan-ignore ibl.requireOnce (demo-mode 403 page; not a class) */
        require_once $this->basePath . '/includes/demo-403.php';
        exit;
    }
}
