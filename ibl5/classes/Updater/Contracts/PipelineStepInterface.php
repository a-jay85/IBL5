<?php

declare(strict_types=1);

namespace Updater\Contracts;

use Updater\StepResult;

/**
 * Contract for a single step in the update pipeline.
 *
 * Each step encapsulates one discrete operation (e.g., importing a file,
 * updating standings). Steps are executed sequentially by the UpdaterService.
 */
interface PipelineStepInterface
{
    /**
     * Human-readable label for this step (used in progress output).
     */
    public function getLabel(): string;

    /**
     * Execute this pipeline step.
     *
     * @return StepResult The outcome of the step execution
     */
    public function execute(): StepResult;
}
