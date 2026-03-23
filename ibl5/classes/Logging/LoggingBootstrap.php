<?php

declare(strict_types=1);

namespace Logging;

use Bootstrap\Contracts\BootstrapStepInterface;
use Bootstrap\Contracts\ContainerInterface;
use Logging\Contracts\LoggerFactoryInterface;

/**
 * Bootstrap step that initializes structured logging.
 *
 * Ready to be wired into Bootstrap\Application when the Container
 * is integrated into mainfile.php. Until then, mainfile.php calls
 * LoggerFactory::fromConfig() directly.
 */
class LoggingBootstrap implements BootstrapStepInterface
{
    /**
     * @see BootstrapStepInterface::boot()
     */
    public function boot(ContainerInterface $container): void
    {
        $factory = LoggerFactory::fromConfig();
        $container->set(LoggerFactoryInterface::class, $factory);
    }
}
