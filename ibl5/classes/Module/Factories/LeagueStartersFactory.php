<?php

declare(strict_types=1);

namespace Module\Factories;

use LeagueStarters\LeagueStartersApiHandler;
use LeagueStarters\LeagueStartersService;
use LeagueStarters\LeagueStartersView;

/**
 * Composition for the LeagueStarters module entry point.
 */
final class LeagueStartersFactory implements \Module\Contracts\ModuleFactoryInterface
{
    public function __construct(private readonly \Module\ModuleServices $services)
    {
    }

    public function db(): \mysqli
    {
        return $this->services->db();
    }

    public function teamIdentity(): \Repositories\TeamIdentityRepository
    {
        return $this->services->teamIdentity();
    }

    public function season(): \Season\Season
    {
        return $this->services->season();
    }

    public function apiHandler(): LeagueStartersApiHandler
    {
        return new LeagueStartersApiHandler(
            $this->services->db(),
            $this->services->teamIdentity(),
            $this->services->auth()
        );
    }

    public function service(): LeagueStartersService
    {
        $db = $this->services->db();

        return new LeagueStartersService($db, new \League\League($db));
    }

    public function view(string $moduleName): LeagueStartersView
    {
        return new LeagueStartersView($moduleName);
    }

}
