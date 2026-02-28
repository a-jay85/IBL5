<?php

declare(strict_types=1);

namespace Updater\Contracts;

use Updater\StepResult;

/**
 * Contract for the pipeline orchestration service.
 *
 * Manages step registration, sequential execution with per-step error isolation,
 * and progress callbacks for streaming output.
 */
interface UpdaterServiceInterface
{
    /**
     * Register a step to be executed in the pipeline.
     */
    public function addStep(PipelineStepInterface $step): void;

    /**
     * Run all registered steps sequentially.
     *
     * Each step is wrapped in a try/catch so that one failure does not abort
     * the remaining steps. Callbacks are invoked before and after each step
     * for progressive output.
     *
     * @param callable(PipelineStepInterface): void $onStepStart Called before each step executes
     * @param callable(StepResult): void $onStepComplete Called after each step completes (success or failure)
     * @return list<StepResult> Results from all executed steps
     */
    public function run(callable $onStepStart, callable $onStepComplete): array;

    /**
     * Get the count of successful steps from the last run.
     */
    public function getSuccessCount(): int;

    /**
     * Get the count of failed steps from the last run.
     */
    public function getErrorCount(): int;
}
