<?php

declare(strict_types=1);

namespace Bootstrap\Contracts;

/**
 * A single step in the application bootstrap sequence.
 */
interface BootstrapStepInterface
{
    /**
     * Execute this bootstrap step, registering services in the container.
     */
    public function boot(ContainerInterface $container): void;
}
