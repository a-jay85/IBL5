<?php

declare(strict_types=1);

namespace Updater\Steps;

use Shared\SharedRepository;
use Updater\Contracts\PipelineStepInterface;
use Updater\StepResult;

/**
 * Step 6: Reset contract extension attempts.
 *
 * Wraps SharedRepository::resetSimContractExtensionAttempts().
 */
class ResetExtensionAttemptsStep implements PipelineStepInterface
{
    public function __construct(
        private readonly SharedRepository $repository,
    ) {
    }

    public function getLabel(): string
    {
        return 'Extension attempts reset';
    }

    public function execute(): StepResult
    {
        $this->repository->resetSimContractExtensionAttempts();

        return StepResult::success($this->getLabel());
    }
}
