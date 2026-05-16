<?php

declare(strict_types=1);

namespace Player\Views;

use Player\PlayerPageType;
use Player\PlayerRepository;
use Player\Stats\PlayerStatsRepository;
use Player\Stats\Views\PlayerHeatAveragesView;
use Player\Stats\Views\PlayerHeatTotalsView;
use Player\Stats\Views\PlayerOlympicAveragesView;
use Player\Stats\Views\PlayerOlympicTotalsView;
use Player\Stats\Views\PlayerPlayoffAveragesView;
use Player\Stats\Views\PlayerPlayoffTotalsView;
use Player\Stats\Views\PlayerRatingsAndSalaryView;
use Player\Stats\Views\PlayerRegularSeasonAveragesView;
use Player\Stats\Views\PlayerRegularSeasonTotalsView;
use Player\Stats\Views\PlayerSimStatsView;
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
            PlayerPageType::OVERVIEW => $this->createOverviewView(),
            PlayerPageType::AWARDS_AND_NEWS => $this->createAwardsAndNewsView(),
            PlayerPageType::ONE_ON_ONE => $this->createOneOnOneView(),
            PlayerPageType::REGULAR_SEASON_TOTALS => $this->createRegularSeasonTotalsView(),
            PlayerPageType::REGULAR_SEASON_AVERAGES => $this->createRegularSeasonAveragesView(),
            PlayerPageType::PLAYOFF_TOTALS => $this->createPlayoffTotalsView(),
            PlayerPageType::PLAYOFF_AVERAGES => $this->createPlayoffAveragesView(),
            PlayerPageType::HEAT_TOTALS => $this->createHeatTotalsView(),
            PlayerPageType::HEAT_AVERAGES => $this->createHeatAveragesView(),
            PlayerPageType::RATINGS_AND_SALARY => $this->createRatingsAndSalaryView(),
            PlayerPageType::SIM_STATS => $this->createSimStatsView(),
            PlayerPageType::OLYMPIC_TOTALS => $this->createOlympicTotalsView(),
            PlayerPageType::OLYMPIC_AVERAGES => $this->createOlympicAveragesView(),
            default => $this->createOverviewView(),
        };
    }

    /**
     * Create PlayerOverviewView instance
     */
    public function createOverviewView(): PlayerOverviewView
    {
        $commonRepo = $this->commonRepository;
        if ($commonRepo === null && isset($GLOBALS['mysqli_db']) && $GLOBALS['mysqli_db'] instanceof \mysqli) {
            $commonRepo = new CommonMysqliRepository($GLOBALS['mysqli_db']);
        }
        
        if ($commonRepo === null) {
            throw new \RuntimeException('CommonMysqliRepository is required for PlayerOverviewView');
        }
        
        return new PlayerOverviewView($this->statsRepository, $commonRepo);
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
        return new PlayerPlayoffTotalsView($this->statsRepository);
    }

    /**
     * Create PlayerPlayoffAveragesView instance
     */
    public function createPlayoffAveragesView(): PlayerPlayoffAveragesView
    {
        return new PlayerPlayoffAveragesView($this->statsRepository);
    }

    /**
     * Create PlayerHeatTotalsView instance
     */
    public function createHeatTotalsView(): PlayerHeatTotalsView
    {
        return new PlayerHeatTotalsView($this->statsRepository);
    }

    /**
     * Create PlayerHeatAveragesView instance
     */
    public function createHeatAveragesView(): PlayerHeatAveragesView
    {
        return new PlayerHeatAveragesView($this->statsRepository);
    }

    /**
     * Create PlayerOlympicTotalsView instance
     */
    public function createOlympicTotalsView(): PlayerOlympicTotalsView
    {
        return new PlayerOlympicTotalsView($this->statsRepository);
    }

    /**
     * Create PlayerOlympicAveragesView instance
     */
    public function createOlympicAveragesView(): PlayerOlympicAveragesView
    {
        return new PlayerOlympicAveragesView($this->statsRepository);
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

}
