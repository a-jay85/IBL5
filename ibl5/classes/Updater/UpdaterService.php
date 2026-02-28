<?php

declare(strict_types=1);

namespace Updater;

use Updater\Contracts\PipelineStepInterface;
use Updater\Contracts\UpdaterServiceInterface;

/**
 * Pipeline orchestration service.
 *
 * Iterates registered steps sequentially, wrapping each in a try/catch
 * for per-step error isolation. Invokes callbacks before and after each
 * step to enable progressive output.
 */
class UpdaterService implements UpdaterServiceInterface
{
    /** @var list<PipelineStepInterface> */
    private array $steps = [];

    private int $successCount = 0;

    private int $errorCount = 0;

    /**
     * @see UpdaterServiceInterface::addStep()
     */
    public function addStep(PipelineStepInterface $step): void
    {
        $this->steps[] = $step;
    }

    /**
     * @see UpdaterServiceInterface::run()
     *
     * @param callable(PipelineStepInterface): void $onStepStart
     * @param callable(StepResult): void $onStepComplete
     * @return list<StepResult>
     */
    public function run(callable $onStepStart, callable $onStepComplete): array
    {
        $this->successCount = 0;
        $this->errorCount = 0;

        /** @var list<StepResult> $results */
        $results = [];

        foreach ($this->steps as $step) {
            $onStepStart($step);

            try {
                $result = $step->execute();
            } catch (\Throwable $e) {
                $result = StepResult::failure($step->getLabel(), $e->getMessage());
            }

            if ($result->success) {
                $this->successCount++;
            } else {
                $this->errorCount++;
            }

            $results[] = $result;
            $onStepComplete($result);
        }

        return $results;
    }

    /**
     * @see UpdaterServiceInterface::getSuccessCount()
     */
    public function getSuccessCount(): int
    {
        return $this->successCount;
    }

    /**
     * @see UpdaterServiceInterface::getErrorCount()
     */
    public function getErrorCount(): int
    {
        return $this->errorCount;
    }
}
