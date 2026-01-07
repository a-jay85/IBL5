<?php

declare(strict_types=1);

namespace Player;

use Player\Contracts\PlayerPageViewInterface;
use Player\Views\PlayerOverviewView;
use Player\Views\PlayerSimStatsView;
use Player\Views\PlayerRegularSeasonTotalsView;
use Player\Views\PlayerRegularSeasonAveragesView;
use Player\Views\PlayerPlayoffTotalsView;
use Player\Views\PlayerPlayoffAveragesView;
use Player\Views\PlayerHeatTotalsView;
use Player\Views\PlayerHeatAveragesView;
use Player\Views\PlayerOlympicTotalsView;
use Player\Views\PlayerOlympicAveragesView;
use Player\Views\PlayerRatingsAndSalaryView;
use Player\Views\PlayerAwardsAndNewsView;
use Season;
use Services\CommonMysqliRepository;

/**
 * PlayerViewFactory - Creates page view instances based on PlayerPageType
 *
 * Uses composition with injected dependencies to create view instances.
 * All views implement PlayerPageViewInterface for consistent render() method.
 */
class PlayerViewFactory
{
    private \mysqli $db;
    private Player $player;
    private PlayerStats $playerStats;
    private PlayerStatsRepository $statsRepository;
    private PlayerAwardsRepository $awardsRepository;
    private PlayerPageViewHelper $viewHelper;
    private Season $season;
    private CommonMysqliRepository $commonRepository;

    public function __construct(
        \mysqli $db,
        Player $player,
        PlayerStats $playerStats
    ) {
        $this->db = $db;
        $this->player = $player;
        $this->playerStats = $playerStats;
        $this->statsRepository = new PlayerStatsRepository($db);
        $this->awardsRepository = new PlayerAwardsRepository($db);
        $this->viewHelper = new PlayerPageViewHelper();
        $this->season = new Season($db);
        $this->commonRepository = new CommonMysqliRepository($db);
    }

    /**
     * Create a page view instance based on the page type
     *
     * @param int|null $pageType The PlayerPageType constant value
     * @return PlayerPageViewInterface|null The view instance, or null for unsupported types
     */
    public function create(?int $pageType): ?PlayerPageViewInterface
    {
        return match ($pageType) {
            \PlayerPageType::OVERVIEW, null => new PlayerOverviewView(
                $this->player,
                $this->playerStats,
                $this->statsRepository,
                $this->season,
                $this->commonRepository,
                $this->db
            ),
            \PlayerPageType::SIM_STATS => new PlayerSimStatsView(
                $this->player,
                $this->playerStats,
                $this->statsRepository
            ),
            \PlayerPageType::REGULAR_SEASON_TOTALS => new PlayerRegularSeasonTotalsView(
                $this->player,
                $this->playerStats,
                $this->statsRepository
            ),
            \PlayerPageType::REGULAR_SEASON_AVERAGES => new PlayerRegularSeasonAveragesView(
                $this->player,
                $this->playerStats,
                $this->statsRepository
            ),
            \PlayerPageType::PLAYOFF_TOTALS => new PlayerPlayoffTotalsView(
                $this->player,
                $this->playerStats,
                $this->statsRepository
            ),
            \PlayerPageType::PLAYOFF_AVERAGES => new PlayerPlayoffAveragesView(
                $this->player,
                $this->playerStats,
                $this->statsRepository
            ),
            \PlayerPageType::HEAT_TOTALS => new PlayerHeatTotalsView(
                $this->player,
                $this->playerStats,
                $this->statsRepository
            ),
            \PlayerPageType::HEAT_AVERAGES => new PlayerHeatAveragesView(
                $this->player,
                $this->playerStats,
                $this->statsRepository
            ),
            \PlayerPageType::OLYMPIC_TOTALS => new PlayerOlympicTotalsView(
                $this->player,
                $this->playerStats,
                $this->statsRepository
            ),
            \PlayerPageType::OLYMPIC_AVERAGES => new PlayerOlympicAveragesView(
                $this->player,
                $this->playerStats,
                $this->statsRepository
            ),
            \PlayerPageType::RATINGS_AND_SALARY => new PlayerRatingsAndSalaryView(
                $this->player,
                $this->playerStats,
                $this->viewHelper
            ),
            \PlayerPageType::AWARDS_AND_NEWS => new PlayerAwardsAndNewsView(
                $this->player,
                $this->playerStats,
                $this->awardsRepository
            ),
            // ONE_ON_ONE is complex and not yet migrated
            \PlayerPageType::ONE_ON_ONE => null,
            default => null,
        };
    }

    /**
     * Check if a page type has a migrated view class
     *
     * @param int|null $pageType The PlayerPageType constant value
     * @return bool True if the page type has a migrated view class
     */
    public function hasMigratedView(?int $pageType): bool
    {
        return $this->create($pageType) !== null;
    }
}
