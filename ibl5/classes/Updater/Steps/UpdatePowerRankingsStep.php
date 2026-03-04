<?php

declare(strict_types=1);

namespace Updater\Steps;

use Updater\Contracts\PipelineStepInterface;
use Updater\PowerRankingsUpdater;
use Updater\StepResult;

/**
 * Step 5: Update power rankings.
 *
 * Wraps PowerRankingsUpdater::update() and captures any echoed output.
 */
class UpdatePowerRankingsStep implements PipelineStepInterface
{
    public function __construct(
        private readonly PowerRankingsUpdater $updater,
    ) {
    }

    public function getLabel(): string
    {
        return 'Power rankings updated';
    }

    public function execute(): StepResult
    {
        ob_start();
        $this->updater->update();
        $log = (string) ob_get_clean();

        return StepResult::success($this->getLabel(), capturedLog: $log);
    }
}
