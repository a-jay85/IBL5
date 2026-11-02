<?php

declare(strict_types=1);

namespace Updater\Steps;

use Updater\Contracts\PipelineStepInterface;
use Updater\StepResult;

/**
 * Record which "on this day" moments the latest sim shows, so a later sim
 * never repeats them. Runs on every updater run in every season phase; the
 * homepage card only reads. Idempotent per (moment, sim). Never fails the
 * pipeline: any problem skips the step and the card keeps working.
 */
final class RecordOnThisDayShownStep implements PipelineStepInterface
{
    public function __construct(
        private readonly \OnThisDay\Contracts\OnThisDayServiceInterface $service,
        private readonly \OnThisDay\Contracts\ShownMomentHistoryInterface $history,
    ) {
    }

    public function getLabel(): string
    {
        return 'On this day history recorded';
    }

    public function execute(): StepResult
    {
        $label = $this->getLabel();

        try {
            $selection = $this->service->getMomentsForSim();
            if ($selection->sim <= 0) {
                return StepResult::skipped($label, 'No sim dates recorded, nothing to record.');
            }
            if ($selection->isEmpty()) {
                return StepResult::skipped($label, "No moment selected for sim {$selection->sim}.");
            }

            $keys = array_map(static fn (\OnThisDay\Moment $m): string => $m->key, $selection->moments);
            $inserted = $this->history->recordShown($selection->sim, $selection->currentSeasonYear, $keys);
        } catch (\Throwable $e) {
            return StepResult::skipped($label, 'On this day history not recorded: ' . $e->getMessage());
        }

        return $inserted > 0
            ? StepResult::success($label, "Recorded {$inserted} moment(s) for sim {$selection->sim}.")
            : StepResult::success($label, "Sim {$selection->sim} already recorded.");
    }
}
