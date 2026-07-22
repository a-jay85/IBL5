<?php

declare(strict_types=1);

namespace Updater\Steps;

use Updater\Contracts\PipelineStepInterface;
use Updater\StepResult;

/**
 * Enqueue the current sim for recap generation.
 *
 * Ships UNREGISTERED — updateAllTheThings.php is NOT touched by this PR.
 * Registration and the accompanying page notification ship in unit 3 so the
 * producer and its consumer go live in one deploy (shared-context decision 11).
 */
class QueueSimSummaryStep implements PipelineStepInterface
{
    public function __construct(
        private readonly \SimRecap\SimSummaryRepository $summaries,
        private readonly \Season\SeasonQueryRepository $seasonQuery,
    ) {
    }

    public function getLabel(): string
    {
        return 'Sim recap queued';
    }

    public function execute(): StepResult
    {
        $sim = $this->seasonQuery->getLastSimDatesArray()['sim'];

        if ($sim <= 0) {
            return StepResult::skipped($this->getLabel(), 'No sim dates recorded — nothing to queue.');
        }

        if ($this->summaries->queuePendingIfAbsent($sim)) {
            return StepResult::success($this->getLabel(), "Queued sim {$sim} for recap generation.");
        }

        return StepResult::skipped($this->getLabel(), "Sim {$sim} already has a summary row.");
    }
}
