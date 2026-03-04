<?php

declare(strict_types=1);

namespace Updater\Contracts;

/**
 * Contract for the updater pipeline controller.
 *
 * Responsible for wiring dependencies, registering steps,
 * and managing progressive output via callbacks.
 */
interface UpdaterControllerInterface
{
    /**
     * Execute the full update pipeline with progressive output.
     */
    public function run(): void;
}
