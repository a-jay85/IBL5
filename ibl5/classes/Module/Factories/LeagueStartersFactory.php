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

    public function team(string $teamName): \Team\Team
    {
        return \Team\Team::initialize($this->services->db(), $teamName);
    }

    /**
     * The view's table renderers need the connection, which index.php never holds.
     *
     * @param array<string, array<int, \Player\Player>> $startersByPosition
     */
    public function renderPage(
        LeagueStartersView $view,
        \Season\Season $season,
        array $startersByPosition,
        \Team\Team $highlightedTeam,
        string $display
    ): string {
        return $view->render($this->services->db(), $season, $startersByPosition, $highlightedTeam, $display);
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
