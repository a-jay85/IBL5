<?php

declare(strict_types=1);

namespace Module\Factories;

/**
 * Composition for the SeasonHighs module entry point.
 */
final class SeasonHighsFactory implements \Module\Contracts\ModuleFactoryInterface
{
    private ?\Season\Season $season = null;

    public function __construct(private readonly \Module\ModuleServices $services)
    {
    }

    public function season(): \Season\Season
    {
        if ($this->season === null) {
            $this->season = new \Season\Season($this->services->db(), $this->services->leagueContext());
        }

        return $this->season;
    }

    public function service(): \SeasonHighs\SeasonHighsService
    {
        $db = $this->services->db();
        $leagueContext = $this->services->leagueContext();

        $repository = new \SeasonHighs\CachedSeasonHighsRepository(
            new \SeasonHighs\SeasonHighsRepository($db, $leagueContext),
            new \Cache\DatabaseCache($db),
            $leagueContext->getCurrentLeague()
        );

        return new \SeasonHighs\SeasonHighsService($repository, $this->season());
    }

    public function view(): \SeasonHighs\SeasonHighsView
    {
        return new \SeasonHighs\SeasonHighsView();
    }
}
