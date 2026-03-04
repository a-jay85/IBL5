<?php

declare(strict_types=1);

namespace Updater\Steps;

use SavedDepthChart\SavedDepthChartRepository;
use Updater\Contracts\PipelineStepInterface;
use Updater\StepResult;

/**
 * Step 7: Extend active saved depth charts.
 *
 * Wraps SavedDepthChartRepository::extendActiveDepthCharts() and captures output.
 */
class ExtendDepthChartsStep implements PipelineStepInterface
{
    public function __construct(
        private readonly SavedDepthChartRepository $repository,
        private readonly string $lastSimEndDate,
        private readonly int $lastSimNumber,
    ) {
    }

    public function getLabel(): string
    {
        return 'Saved depth charts updated';
    }

    public function execute(): StepResult
    {
        ob_start();
        $count = $this->repository->extendActiveDepthCharts($this->lastSimEndDate, $this->lastSimNumber);
        $log = (string) ob_get_clean();

        return StepResult::success($this->getLabel(), $count . ' active DCs extended', capturedLog: $log);
    }
}
