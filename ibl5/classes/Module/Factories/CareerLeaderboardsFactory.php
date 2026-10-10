<?php

declare(strict_types=1);

namespace Module\Factories;

/**
 * Composition for the CareerLeaderboards module entry point.
 */
final class CareerLeaderboardsFactory implements \Module\Contracts\ModuleFactoryInterface
{
    private ?\CareerLeaderboards\CachedCareerLeaderboardsRepository $repository = null;

    private ?\CareerLeaderboards\CareerLeaderboardsService $service = null;

    public function __construct(private readonly \Module\ModuleServices $services)
    {
    }

    public function repository(): \CareerLeaderboards\CachedCareerLeaderboardsRepository
    {
        if ($this->repository === null) {
            $db = $this->services->db();
            $dbCache = new \Cache\DatabaseCache($db);
            $innerRepository = new \CareerLeaderboards\CareerLeaderboardsRepository($db);
            $this->repository = new \CareerLeaderboards\CachedCareerLeaderboardsRepository($innerRepository, $dbCache);
        }

        return $this->repository;
    }

    public function service(): \CareerLeaderboards\CareerLeaderboardsService
    {
        if ($this->service === null) {
            $this->service = new \CareerLeaderboards\CareerLeaderboardsService();
        }

        return $this->service;
    }

    public function view(): \CareerLeaderboards\CareerLeaderboardsView
    {
        return new \CareerLeaderboards\CareerLeaderboardsView($this->service());
    }
}
