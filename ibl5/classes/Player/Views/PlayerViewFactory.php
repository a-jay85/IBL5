<?php

declare(strict_types=1);

namespace Player\Views;

use Player\PlayerRepository;

/**
 * PlayerViewFactory - Creates view instances with repository injection
 * 
 * Factory pattern for consistent view instantiation with dependencies
 */
class PlayerViewFactory
{
    private PlayerRepository $repository;

    public function __construct(PlayerRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Create PlayerAwardsView instance
     * 
     * @return PlayerAwardsView
     */
    public function createAwardsView(): PlayerAwardsView
    {
        return new PlayerAwardsView($this->repository);
    }

    /**
     * Create PlayerGameLogView instance
     * 
     * @return PlayerGameLogView
     */
    public function createGameLogView(): PlayerGameLogView
    {
        return new PlayerGameLogView($this->repository);
    }

    /**
     * Create PlayerSeasonStatsView instance
     * 
     * @return PlayerSeasonStatsView
     */
    public function createSeasonStatsView(): PlayerSeasonStatsView
    {
        return new PlayerSeasonStatsView($this->repository);
    }

    /**
     * Create PlayerPlayoffStatsView instance
     * 
     * @return PlayerPlayoffStatsView
     */
    public function createPlayoffStatsView(): PlayerPlayoffStatsView
    {
        return new PlayerPlayoffStatsView($this->repository);
    }

    /**
     * Create PlayerHeatStatsView instance
     * 
     * @return PlayerHeatStatsView
     */
    public function createHeatStatsView(): PlayerHeatStatsView
    {
        return new PlayerHeatStatsView($this->repository);
    }

    /**
     * Create PlayerOlympicsStatsView instance
     * 
     * @return PlayerOlympicsStatsView
     */
    public function createOlympicsStatsView(): PlayerOlympicsStatsView
    {
        return new PlayerOlympicsStatsView($this->repository);
    }
}
