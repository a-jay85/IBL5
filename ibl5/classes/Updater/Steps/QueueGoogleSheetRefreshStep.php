<?php

declare(strict_types=1);

namespace Updater\Steps;

use GoogleSheets\Contracts\GoogleSheetConnectionRepositoryInterface;
use Updater\Contracts\PipelineStepInterface;
use Updater\StepResult;

/**
 * Flag every active Google Sheet connection for refresh after a sim.
 *
 * One UPDATE, no Google I/O: the prod cron worker (scripts/googleSheetRefreshTick.php)
 * drains the queue. This step never fails and never throws, so the feature can
 * never break the sim pipeline.
 */
class QueueGoogleSheetRefreshStep implements PipelineStepInterface
{
    public function __construct(
        private readonly GoogleSheetConnectionRepositoryInterface $connections,
    ) {
    }

    public function getLabel(): string
    {
        return 'Google Sheet refresh queued';
    }

    public function execute(): StepResult
    {
        try {
            $count = $this->connections->markAllActivePending();
        } catch (\Throwable $e) {
            return StepResult::skipped($this->getLabel(), 'Google Sheets queue unavailable: ' . get_class($e));
        }

        if ($count === 0) {
            return StepResult::skipped($this->getLabel(), 'No connected Google Sheets.');
        }

        return StepResult::success(
            $this->getLabel(),
            "Queued {$count} Google Sheet refresh(es); the cron worker writes them within about 5 minutes."
        );
    }
}
