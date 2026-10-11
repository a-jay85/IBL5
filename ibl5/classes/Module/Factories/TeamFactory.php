<?php

declare(strict_types=1);

namespace Module\Factories;

use Module\Contracts\ModuleFactoryInterface;
use Module\ModuleServices;

/**
 * Composition root for modules/Team/index.php.
 */
final class TeamFactory implements ModuleFactoryInterface
{
    public function __construct(private readonly ModuleServices $services)
    {
    }

    public function controller(): \Team\TeamController
    {
        $db = $this->services->db();
        $commonRepo = $this->services->teamIdentity();
        $teamRepository = new \Team\TeamRepository($db);
        $service = new \Team\TeamService($db, $teamRepository, $this->services->leagueContext());
        $view = new \Team\TeamView();

        return new \Team\TeamController(
            $db,
            $commonRepo,
            $this->services->auth(),
            $service,
            $view,
            $this->services->request()
        );
    }

    /**
     * Branch-local: index.php calls this only inside its `api` arm.
     */
    public function apiHandler(): \Team\TeamApiHandler
    {
        return new \Team\TeamApiHandler($this->services->db());
    }
}
