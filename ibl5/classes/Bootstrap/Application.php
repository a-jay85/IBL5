<?php

declare(strict_types=1);

namespace Bootstrap;

use Bootstrap\Contracts\BootstrapStepInterface;
use Bootstrap\Contracts\ContainerInterface;

/**
 * Application orchestrator — registers and executes bootstrap steps in sequence.
 *
 * Each step populates the container with services and sets $GLOBALS for backward
 * compatibility with legacy PHP-Nuke modules.
 */
class Application
{
    private ContainerInterface $container;

    /** @var list<BootstrapStepInterface> */
    private array $steps = [];

    public function __construct(?ContainerInterface $container = null)
    {
        $this->container = $container ?? new Container();
    }

    /**
     * Register a bootstrap step to be executed during boot().
     */
    public function addStep(BootstrapStepInterface $step): void
    {
        $this->steps[] = $step;
    }

    /**
     * Execute all registered bootstrap steps in order.
     */
    public function boot(): void
    {
        foreach ($this->steps as $step) {
            $step->boot($this->container);
        }
    }

    /**
     * Access the container (for testing or post-boot service resolution).
     */
    public function getContainer(): ContainerInterface
    {
        return $this->container;
    }
}
