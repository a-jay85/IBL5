<?php

declare(strict_types=1);

namespace Updater\Steps;

use Updater\Contracts\PipelineStepInterface;
use Updater\ScheduleUpdater;
use Updater\StepResult;

/**
 * Step 3: Update schedule.
 *
 * Wraps ScheduleUpdater::update() and captures any echoed output.
 */
class UpdateScheduleStep implements PipelineStepInterface
{
    public function __construct(
        private readonly ScheduleUpdater $updater,
    ) {
    }

    public function getLabel(): string
    {
        return 'Schedule updated';
    }

    public function execute(): StepResult
    {
        ob_start();
        $this->updater->update();
        $log = (string) ob_get_clean();

        return StepResult::success($this->getLabel(), capturedLog: $log);
    }
}
