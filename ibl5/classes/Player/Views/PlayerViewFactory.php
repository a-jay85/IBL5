<?php

declare(strict_types=1);

namespace Player\Views;

use Player\PlayerRepository;
use Player\PlayerStatsRepository;
use Player\Contracts\PlayerViewInterface;
use Services\CommonMysqliRepository;

/**
 * PlayerViewFactory - Creates view instances with repository injection
 * 
 * Factory pattern for consistent view instantiation with dependencies.
 * Supports creation of all Player module views via createView() method
 * which accepts PlayerPageType constants.
 */
class PlayerViewFactory
{
    private PlayerRepository $repository;
    private PlayerStatsRepository $statsRepository;
    private ?CommonMysqliRepository $commonRepository;

    public function __construct(
        PlayerRepository $repository,
        PlayerStatsRepository $statsRepository,
        ?CommonMysqliRepository $commonRepository = null
    ) {
        $this->repository = $repository;
        $this->statsRepository = $statsRepository;
        $this->commonRepository = $commonRepository;
    }

    /**
     * Create a view based on PlayerPageType constant
     * 
     * @param int|null $pageType One of the PlayerPageType constants
     * @return PlayerViewInterface|object The view instance
     */
    public function createView(?int $pageType): object
    {
        return match ($pageType) {
            \PlayerPageType::OVERVIEW => $this->createOverviewView(),
            \PlayerPageType::AWARDS_AND_NEWS => $this->createAwardsAndNewsView(),
            \PlayerPageType::ONE_ON_ONE => $this->createOneOnOneView(),
            \PlayerPageType::REGULAR_SEASON_TOTALS => $this->createRegularSeasonTotalsView(),
            \PlayerPageType::REGULAR_SEASON_AVERAGES => $this->createRegularSeasonAveragesView(),
            \PlayerPageType::PLAYOFF_TOTALS => $this->createPlayoffTotalsView(),
            \PlayerPageType::PLAYOFF_AVERAGES => $this->createPlayoffAveragesView(),
            \PlayerPageType::HEAT_TOTALS => $this->createHeatTotalsView(),
            \PlayerPageType::HEAT_AVERAGES => $this->createHeatAveragesView(),
            \PlayerPageType::RATINGS_AND_SALARY => $this->createRatingsAndSalaryView(),
            \PlayerPageType::SIM_STATS => $this->createSimStatsView(),
            \PlayerPageType::OLYMPIC_TOTALS => $this->createOlympicTotalsView(),
            \PlayerPageType::OLYMPIC_AVERAGES => $this->createOlympicAveragesView(),
            default => $this->createOverviewView(),
        };
    }

    /**
     * Create PlayerOverviewView instance
     */
    public function createOverviewView(): PlayerOverviewView
    {
        $commonRepo = $this->commonRepository;
        if ($commonRepo === null && isset($GLOBALS['mysqli_db'])) {
            $commonRepo = new CommonMysqliRepository($GLOBALS['mysqli_db']);
        }
        
        if ($commonRepo === null) {
            throw new \RuntimeException('CommonMysqliRepository is required for PlayerOverviewView');
        }
        
        return new PlayerOverviewView($this->repository, $commonRepo);
    }

    /**
     * Create PlayerSimStatsView instance
     */
    public function createSimStatsView(): PlayerSimStatsView
    {
        return new PlayerSimStatsView($this->statsRepository);
    }

    /**
     * Create PlayerRegularSeasonTotalsView instance
     */
    public function createRegularSeasonTotalsView(): PlayerRegularSeasonTotalsView
    {
        return new PlayerRegularSeasonTotalsView($this->statsRepository);
    }

    /**
     * Create PlayerRegularSeasonAveragesView instance
     */
    public function createRegularSeasonAveragesView(): PlayerRegularSeasonAveragesView
    {
        return new PlayerRegularSeasonAveragesView($this->statsRepository);
    }

    /**
     * Create PlayerPlayoffTotalsView instance
     */
    public function createPlayoffTotalsView(): PlayerPlayoffTotalsView
    {
        return new PlayerPlayoffTotalsView($this->repository);
    }

    /**
     * Create PlayerPlayoffAveragesView instance
     */
    public function createPlayoffAveragesView(): PlayerPlayoffAveragesView
    {
        return new PlayerPlayoffAveragesView($this->repository, $this->statsRepository);
    }

    /**
     * Create PlayerHeatTotalsView instance
     */
    public function createHeatTotalsView(): PlayerHeatTotalsView
    {
        return new PlayerHeatTotalsView($this->repository);
    }

    /**
     * Create PlayerHeatAveragesView instance
     */
    public function createHeatAveragesView(): PlayerHeatAveragesView
    {
        return new PlayerHeatAveragesView($this->repository, $this->statsRepository);
    }

    /**
     * Create PlayerOlympicTotalsView instance
     */
    public function createOlympicTotalsView(): PlayerOlympicTotalsView
    {
        return new PlayerOlympicTotalsView($this->repository);
    }

    /**
     * Create PlayerOlympicAveragesView instance
     */
    public function createOlympicAveragesView(): PlayerOlympicAveragesView
    {
        return new PlayerOlympicAveragesView($this->repository, $this->statsRepository);
    }

    /**
     * Create PlayerRatingsAndSalaryView instance
     */
    public function createRatingsAndSalaryView(): PlayerRatingsAndSalaryView
    {
        return new PlayerRatingsAndSalaryView($this->statsRepository);
    }

    /**
     * Create PlayerAwardsAndNewsView instance
     */
    public function createAwardsAndNewsView(): PlayerAwardsAndNewsView
    {
        return new PlayerAwardsAndNewsView($this->repository);
    }

    /**
     * Create PlayerOneOnOneView instance
     */
    public function createOneOnOneView(): PlayerOneOnOneView
    {
        return new PlayerOneOnOneView($this->repository);
    }

    // Legacy view creation methods for backward compatibility

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
