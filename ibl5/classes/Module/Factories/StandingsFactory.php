<?php

declare(strict_types=1);

namespace Module\Factories;

use Module\Contracts\ModuleFactoryInterface;
use Module\ModuleServices;

/**
 * Composition root for modules/Standings/index.php.
 */
final class StandingsFactory implements ModuleFactoryInterface
{
    public function __construct(private readonly ModuleServices $services)
    {
    }

    /**
     * Olympics standings view when the league context is Olympics, else the IBL view.
     */
    public function view(): \Standings\StandingsView|\Standings\OlympicsStandingsView
    {
        $db = $this->services->db();
        $leagueContext = $this->services->leagueContext();

        $repository = new \Standings\StandingsRepository($db, $leagueContext);
        $season = new \Season\Season($db, $leagueContext);

        if ($leagueContext->isOlympics()) {
            $realTeamIds = \League\OlympicsTeamFilter::getRealTeamIds($db);
            return new \Standings\OlympicsStandingsView($repository, $season->endingYear, $realTeamIds);
        }

        return new \Standings\StandingsView($repository, $season->endingYear);
    }
}
