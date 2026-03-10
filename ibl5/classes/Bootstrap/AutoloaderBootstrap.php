<?php

declare(strict_types=1);

namespace Bootstrap;

use Bootstrap\Contracts\BootstrapStepInterface;
use Bootstrap\Contracts\ContainerInterface;

/**
 * Autoloader bootstrap: Composer autoloader + worktree prepend autoloader.
 *
 * Extracted from mainfile.php lines 87-103.
 */
class AutoloaderBootstrap implements BootstrapStepInterface
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
        require_once $this->basePath . '/vendor/autoload.php';

        // In git worktrees, vendor/ is symlinked to the main repo. Composer resolves
        // __DIR__ through the symlink, so it loads classes from the main repo instead
        // of the worktree. Prepend the worktree's classes/ directory so modified files
        // are used at runtime.
        if (is_link($this->basePath . '/vendor')) {
            $worktreeClasses = realpath($this->basePath . '/classes');
            if ($worktreeClasses !== false) {
                spl_autoload_register(static function (string $class) use ($worktreeClasses): void {
                    $file = $worktreeClasses . '/' . str_replace('\\', '/', $class) . '.php';
                    if (file_exists($file)) {
                        require $file;
                    }
                }, true, true);
            }
        }
    }
}
