<?php

declare(strict_types=1);

namespace Updater;

use SavedDepthChart\SavedDepthChartRepository;

/**
 * Extends active saved depth charts after each sim
 *
 * Called by updateAllTheThings.php to update sim_end_date and sim_number_end
 * on all active depth charts.
 */
class SavedDepthChartUpdater
{
    private SavedDepthChartRepository $repository;

    public function __construct(object $db)
    {
        $this->repository = new SavedDepthChartRepository($db);
    }

    /**
     * Extend all active depth charts with new end dates
     *
     * @return int Number of active depth charts extended
     */
    public function update(string $newEndDate, int $newSimNumber): int
    {
        return $this->repository->extendActiveDepthCharts($newEndDate, $newSimNumber);
    }
}
