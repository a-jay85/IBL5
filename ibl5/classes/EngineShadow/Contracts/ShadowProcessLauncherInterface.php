<?php

declare(strict_types=1);

namespace EngineShadow\Contracts;

/**
 * Spawns the engine shadow sim as a detached, fire-and-forget background process.
 */
interface ShadowProcessLauncherInterface
{
    /**
     * Launch the detached shadow run and return immediately. Fire-and-forget by
     * design: there is no completion feedback (the run outlives the request that
     * triggered it). Throws only if the launch itself cannot start (e.g. the CLI
     * script is missing) — never on the run's own success or failure.
     */
    public function launch(): void;
}
