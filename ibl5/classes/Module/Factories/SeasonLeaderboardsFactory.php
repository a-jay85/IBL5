<?php

declare(strict_types=1);

namespace Module\Factories;

/**
 * Composition for the SeasonLeaderboards module entry point.
 */
final class SeasonLeaderboardsFactory implements \Module\Contracts\ModuleFactoryInterface
{
    private ?\SeasonLeaderboards\CachedSeasonLeaderboardsRepository $repository = null;

    private ?\SeasonLeaderboards\SeasonLeaderboardsService $service = null;

    public function __construct(private readonly \Module\ModuleServices $services)
    {
    }

    public function repository(): \SeasonLeaderboards\CachedSeasonLeaderboardsRepository
    {
        if ($this->repository === null) {
            $db = $this->services->db();
            $dbCache = new \Cache\DatabaseCache($db);
            $innerRepository = new \SeasonLeaderboards\SeasonLeaderboardsRepository(
                $db,
                $this->services->leagueContext()
            );
            $this->repository = new \SeasonLeaderboards\CachedSeasonLeaderboardsRepository($innerRepository, $dbCache);
        }

        return $this->repository;
    }

    public function service(): \SeasonLeaderboards\SeasonLeaderboardsService
    {
        if ($this->service === null) {
            $this->service = new \SeasonLeaderboards\SeasonLeaderboardsService($this->repository());
        }

        return $this->service;
    }

    public function view(): \SeasonLeaderboards\SeasonLeaderboardsView
    {
        return new \SeasonLeaderboards\SeasonLeaderboardsView($this->service());
    }
}
