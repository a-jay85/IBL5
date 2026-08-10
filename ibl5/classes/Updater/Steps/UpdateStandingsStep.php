<?php

declare(strict_types=1);

namespace Updater\Steps;

use Updater\Contracts\PipelineStepInterface;
use Updater\StandingsUpdater;
use Updater\StepResult;

/**
 * Step 4: Update standings.
 *
 * Wraps StandingsUpdater::update() and drains its output buffer via takeOutputBuffer().
 */
class UpdateStandingsStep implements PipelineStepInterface
{
    public function __construct(
        private readonly StandingsUpdater $updater,
    ) {
    }

    public function getLabel(): string
    {
        return 'Standings updated';
    }

    public function execute(): StepResult
    {
        $this->updater->update();
        $log = $this->updater->takeOutputBuffer();

        return StepResult::success($this->getLabel(), capturedLog: $log, collapsibleLog: true);
    }
}
