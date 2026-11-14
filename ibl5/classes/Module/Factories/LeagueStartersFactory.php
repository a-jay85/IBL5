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

    /**
     * The factory supplies the handle; the entry point supplies the identity.
     *
     * @return \Closure(int|string): \Team\Team
     */
    public function teamResolver(): \Closure
    {
        $db = $this->services->db();

        return static fn (int|string $identifier): \Team\Team => \Team\Team::initialize($db, $identifier);
    }

    /**
     * Renders the page body with the request's database handle bound in.
     *
     * @return \Closure(LeagueStartersView, \Season\Season, array<string, array<int, \Player\Player>>, \Team\Team, string): string
     */
    public function viewRenderer(): \Closure
    {
        $db = $this->services->db();

        return static fn (
            LeagueStartersView $view,
            \Season\Season $season,
            array $startersByPosition,
            \Team\Team $userTeam,
            string $display
        ): string => $view->render($db, $season, $startersByPosition, $userTeam, $display);
    }

}
