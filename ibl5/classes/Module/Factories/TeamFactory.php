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

    public function controller(string $logoDir): \Team\TeamController
    {
        $db = $this->services->db();
        $leagueContext = $this->services->leagueContext();
        $commonRepo = $this->services->teamIdentity();
        $teamRepository = new \Team\TeamRepository($db);
        // Rivalries reads IBL tables only, so the Olympics Team page gets no rivals card.
        $rivalsCardBuilder = $leagueContext->isOlympics()
            ? null
            : new \Team\RivalsCardBuilder(
                \Rivalries\RivalriesServiceFactory::create($db, \Season\SharedSeason::get($db, $leagueContext), $logoDir),
                fn (int $tid): ?string => $commonRepo->getOwnerName($tid),
            );
        $service = new \Team\TeamService($db, $teamRepository, $leagueContext, rivalsCardBuilder: $rivalsCardBuilder);
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
