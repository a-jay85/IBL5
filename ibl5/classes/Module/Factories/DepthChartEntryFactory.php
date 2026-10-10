<?php

declare(strict_types=1);

namespace Module\Factories;

use Module\Contracts\ModuleFactoryInterface;
use Module\ModuleServices;

/**
 * Composition root for modules/DepthChartEntry/index.php.
 *
 * Each handler function in index.php resolves this factory in its own body, after
 * its own gates, and calls the one method for the object it dispatches to.
 */
final class DepthChartEntryFactory implements ModuleFactoryInterface
{
    public function __construct(private readonly ModuleServices $services)
    {
    }

    public function controller(): \DepthChart\DepthChartController
    {
        $db = $this->services->db();
        $commonRepo = $this->services->teamIdentity();

        $repository = new \DepthChart\DepthChartRepository($db);
        $service = new \DepthChart\DepthChartService();
        $view = new \DepthChart\DepthChartView($this->services->leagueContext(), $service);
        $teamRepository = new \Team\TeamRepository($db);
        $teamTableService = new \Team\TeamTableService($db, $teamRepository);
        $submissionHandler = new \DepthChart\DepthChartSubmissionHandler($db, $commonRepo);

        return new \DepthChart\DepthChartController(
            $db,
            $commonRepo,
            $repository,
            $service,
            $view,
            $teamTableService,
            $submissionHandler,
            $this->services->request()
        );
    }

    public function apiHandler(): \DepthChart\DepthChartApiHandler
    {
        return new \DepthChart\DepthChartApiHandler(
            $this->services->db(),
            $this->services->teamIdentity(),
            $this->services->leagueContext()
        );
    }

    public function nextSimTabApiHandler(): \NextSim\NextSimTabApiHandler
    {
        return new \NextSim\NextSimTabApiHandler($this->services->db());
    }

    public function savedDepthChartApiHandler(): \DepthChartSnapshot\DepthChartSnapshotApiHandler
    {
        return new \DepthChartSnapshot\DepthChartSnapshotApiHandler($this->services->db());
    }
}
