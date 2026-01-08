<?php

declare(strict_types=1);

namespace Player;

use Statistics\StatsFormatter;
use Player\Contracts\PlayerStatsInterface;
use Player\Contracts\PlayerStatsRepositoryInterface;

/**
 * PlayerStats - Player statistics data object
 * 
 * Provides access to current season stats, career stats, season/career highs,
 * and per-game averages. Uses PlayerStatsRepository for all database operations.
 * 
 * @see PlayerStatsInterface
 */
class PlayerStats implements PlayerStatsInterface
{
    protected PlayerStatsRepositoryInterface $repository;

    public $playerID;
    public $plr;

    public $name;
    public $position;
    public $isRetired;
    
    public $seasonGamesStarted;
    public $seasonGamesPlayed;
    public $seasonMinutes;
    public $seasonFieldGoalsMade;
    public $seasonFieldGoalsAttempted;
    public $seasonFreeThrowsMade;
    public $seasonFreeThrowsAttempted;
    public $seasonThreePointersMade;
    public $seasonThreePointersAttempted;
    public $seasonOffensiveRebounds;
    public $seasonDefensiveRebounds;
    public $seasonTotalRebounds;
    public $seasonAssists;
    public $seasonSteals;
    public $seasonTurnovers;
    public $seasonBlocks;
    public $seasonPersonalFouls;
    public $seasonPoints;

    public $seasonMinutesPerGame;
    public $seasonFieldGoalsMadePerGame;
    public $seasonFieldGoalsAttemptedPerGame;
    public $seasonFreeThrowsMadePerGame;
    public $seasonFreeThrowsAttemptedPerGame;
    public $seasonThreePointersMadePerGame;
    public $seasonThreePointersAttemptedPerGame;
    public $seasonOffensiveReboundsPerGame;
    public $seasonDefensiveReboundsPerGame;
    public $seasonTotalReboundsPerGame;
    public $seasonAssistsPerGame;
    public $seasonStealsPerGame;
    public $seasonTurnoversPerGame;
    public $seasonBlocksPerGame;
    public $seasonPersonalFoulsPerGame;
    public $seasonPointsPerGame;
    
    public $seasonFieldGoalPercentage;
    public $seasonFreeThrowPercentage;
    public $seasonThreePointPercentage;

    public $seasonHighPoints;
    public $seasonHighRebounds;
    public $seasonHighAssists;
    public $seasonHighSteals;
    public $seasonHighBlocks;
    public $seasonDoubleDoubles;
    public $seasonTripleDoubles;

    public $seasonPlayoffHighPoints;
    public $seasonPlayoffHighRebounds;
    public $seasonPlayoffHighAssists;
    public $seasonPlayoffHighSteals;
    public $seasonPlayoffHighBlocks;

    public $careerSeasonHighPoints;
    public $careerSeasonHighRebounds;
    public $careerSeasonHighAssists;
    public $careerSeasonHighSteals;
    public $careerSeasonHighBlocks;
    public $careerDoubleDoubles;
    public $careerTripleDoubles;

    public $careerPlayoffHighPoints;
    public $careerPlayoffHighRebounds;
    public $careerPlayoffHighAssists;
    public $careerPlayoffHighSteals;
    public $careerPlayoffHighBlocks;

    public $careerGamesPlayed;
    public $careerMinutesPlayed;
    public $careerFieldGoalsMade;
    public $careerFieldGoalsAttempted;
    public $careerFreeThrowsMade;
    public $careerFreeThrowsAttempted;
    public $careerThreePointersMade;
    public $careerThreePointersAttempted;
    public $careerOffensiveRebounds;
    public $careerDefensiveRebounds;
    public $careerTotalRebounds;
    public $careerAssists;
    public $careerSteals;
    public $careerTurnovers;
    public $careerBlocks;
    public $careerPersonalFouls;
    public $careerPoints;

    public $gameMinutesPlayed;
    public $gameFieldGoalsMade;
    public $gameFieldGoalsAttempted;
    public $gameFreeThrowsMade;
    public $gameFreeThrowsAttempted;
    public $gameThreePointersMade;
    public $gameThreePointersAttempted;
    public $gameOffensiveRebounds;
    public $gameDefensiveRebounds;
    public $gameAssists;
    public $gameSteals;
    public $gameTurnovers;
    public $gameBlocks;
    public $gamePersonalFouls;

    /**
     * Constructor - accepts repository for database operations
     * 
     * @param PlayerStatsRepositoryInterface $repository Stats repository instance
     */
    public function __construct(PlayerStatsRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @see PlayerStatsInterface::withPlayerID()
     */
    public static function withPlayerID(object $db, int $playerID): PlayerStatsInterface
    {
        $repository = new PlayerStatsRepository($db);
        $instance = new self($repository);
        $instance->loadByID($playerID);
        return $instance;
    }

    /**
     * @see PlayerStatsInterface::withPlayerObject()
     */
    public static function withPlayerObject(object $db, Player $player): PlayerStatsInterface
    {
        $repository = new PlayerStatsRepository($db);
        $instance = new self($repository);
        $instance->loadByID($player->playerID);
        return $instance;
    }

    /**
     * @see PlayerStatsInterface::withPlrRow()
     */
    public static function withPlrRow(object $db, array $plrRow): PlayerStatsInterface
    {
        $repository = new PlayerStatsRepository($db);
        $instance = new self($repository);
        $instance->fill($plrRow);
        return $instance;
    }

    /**
     * @see PlayerStatsInterface::withHistoricalPlrRow()
     */
    public static function withHistoricalPlrRow(object $db, array $plrRow): PlayerStatsInterface
    {
        $repository = new PlayerStatsRepository($db);
        $instance = new self($repository);
        $instance->fillHistorical($plrRow);
        return $instance;
    }

    /**
     * @see PlayerStatsInterface::withBoxscoreInfoLine()
     */
    public static function withBoxscoreInfoLine(object $db, string $playerInfoLine): PlayerStatsInterface
    {
        $repository = new PlayerStatsRepository($db);
        $instance = new self($repository);
        $instance->fillBoxscoreStats($playerInfoLine);
        return $instance;
    }

    /**
     * Load player stats by ID using repository
     */
    protected function loadByID(int $playerID): void
    {
        $plrRow = $this->repository->getPlayerStats($playerID);
        if ($plrRow) {
            $this->fill($plrRow);
        }
    }

    /**
     * Fill stats from a current player database row
     */
    protected function fill(array $plrRow): void
    {
        $this->playerID = $plrRow['pid'];
        $this->name = $plrRow['name'];
        $this->position = $plrRow['pos'];
        $this->isRetired = $plrRow['retired'];

        $this->seasonGamesStarted = $plrRow['stats_gs'];
        $this->seasonGamesPlayed = $plrRow['stats_gm'];
        $this->seasonMinutes = $plrRow['stats_min'];
        $this->seasonFieldGoalsMade = $plrRow['stats_fgm'];
        $this->seasonFieldGoalsAttempted = $plrRow['stats_fga'];
        $this->seasonFreeThrowsMade = $plrRow['stats_ftm'];
        $this->seasonFreeThrowsAttempted = $plrRow['stats_fta'];
        $this->seasonThreePointersMade = $plrRow['stats_3gm'];
        $this->seasonThreePointersAttempted = $plrRow['stats_3ga'];
        $this->seasonOffensiveRebounds = $plrRow['stats_orb'];
        $this->seasonDefensiveRebounds = $plrRow['stats_drb'];
        $this->seasonTotalRebounds = $this->seasonOffensiveRebounds + $this->seasonDefensiveRebounds;
        $this->seasonAssists = $plrRow['stats_ast'];
        $this->seasonSteals = $plrRow['stats_stl'];
        $this->seasonTurnovers = $plrRow['stats_to'];
        $this->seasonBlocks = $plrRow['stats_blk'];
        $this->seasonPersonalFouls = $plrRow['stats_pf'];
        $this->seasonPoints = StatsFormatter::calculatePoints($this->seasonFieldGoalsMade, $this->seasonFreeThrowsMade, $this->seasonThreePointersMade);

        $this->seasonMinutesPerGame = StatsFormatter::formatPerGameAverage($this->seasonMinutes, $this->seasonGamesPlayed);
        $this->seasonFieldGoalsMadePerGame = StatsFormatter::formatAverage(StatsFormatter::safeDivide($this->seasonFieldGoalsMade, $this->seasonGamesPlayed));
        $this->seasonFieldGoalsAttemptedPerGame = StatsFormatter::formatAverage(StatsFormatter::safeDivide($this->seasonFieldGoalsAttempted, $this->seasonGamesPlayed));
        $this->seasonFreeThrowsMadePerGame = StatsFormatter::formatAverage(StatsFormatter::safeDivide($this->seasonFreeThrowsMade, $this->seasonGamesPlayed));
        $this->seasonFreeThrowsAttemptedPerGame = StatsFormatter::formatAverage(StatsFormatter::safeDivide($this->seasonFreeThrowsAttempted, $this->seasonGamesPlayed));
        $this->seasonThreePointersMadePerGame = StatsFormatter::formatAverage(StatsFormatter::safeDivide($this->seasonThreePointersMade, $this->seasonGamesPlayed));
        $this->seasonThreePointersAttemptedPerGame = StatsFormatter::formatAverage(StatsFormatter::safeDivide($this->seasonThreePointersAttempted, $this->seasonGamesPlayed));
        $this->seasonOffensiveReboundsPerGame = StatsFormatter::formatPerGameAverage($this->seasonOffensiveRebounds, $this->seasonGamesPlayed);
        $this->seasonDefensiveReboundsPerGame = StatsFormatter::formatPerGameAverage($this->seasonDefensiveRebounds, $this->seasonGamesPlayed);
        $this->seasonTotalReboundsPerGame = StatsFormatter::formatPerGameAverage($this->seasonTotalRebounds, $this->seasonGamesPlayed);
        $this->seasonAssistsPerGame = StatsFormatter::formatPerGameAverage($this->seasonAssists, $this->seasonGamesPlayed);
        $this->seasonStealsPerGame = StatsFormatter::formatPerGameAverage($this->seasonSteals, $this->seasonGamesPlayed);
        $this->seasonTurnoversPerGame = StatsFormatter::formatPerGameAverage($this->seasonTurnovers, $this->seasonGamesPlayed);
        $this->seasonBlocksPerGame = StatsFormatter::formatPerGameAverage($this->seasonBlocks, $this->seasonGamesPlayed);
        $this->seasonPersonalFoulsPerGame = StatsFormatter::formatPerGameAverage($this->seasonPersonalFouls, $this->seasonGamesPlayed);
        $this->seasonPointsPerGame = StatsFormatter::formatPerGameAverage($this->seasonPoints, $this->seasonGamesPlayed);

        $this->seasonFieldGoalPercentage = StatsFormatter::formatPercentage($this->seasonFieldGoalsMade, $this->seasonFieldGoalsAttempted);
        $this->seasonFreeThrowPercentage = StatsFormatter::formatPercentage($this->seasonFreeThrowsMade, $this->seasonFreeThrowsAttempted);
        $this->seasonThreePointPercentage = StatsFormatter::formatPercentage($this->seasonThreePointersMade, $this->seasonThreePointersAttempted);
        
        $this->seasonHighPoints = $plrRow['sh_pts'];
        $this->seasonHighRebounds = $plrRow['sh_reb'];
        $this->seasonHighAssists = $plrRow['sh_ast'];
        $this->seasonHighSteals = $plrRow['sh_stl'];
        $this->seasonHighBlocks = $plrRow['sh_blk'];
        $this->seasonDoubleDoubles = $plrRow['s_dd'];
        $this->seasonTripleDoubles = $plrRow['s_td'];

        $this->seasonPlayoffHighPoints = $plrRow['sp_pts'];
        $this->seasonPlayoffHighRebounds = $plrRow['sp_reb'];
        $this->seasonPlayoffHighAssists = $plrRow['sp_ast'];
        $this->seasonPlayoffHighSteals = $plrRow['sp_stl'];
        $this->seasonPlayoffHighBlocks = $plrRow['sp_blk'];

        $this->careerSeasonHighPoints = $plrRow['ch_pts'];
        $this->careerSeasonHighRebounds = $plrRow['ch_reb'];
        $this->careerSeasonHighAssists = $plrRow['ch_ast'];
        $this->careerSeasonHighSteals = $plrRow['ch_stl'];
        $this->careerSeasonHighBlocks = $plrRow['ch_blk'];
        $this->careerDoubleDoubles = $plrRow['c_dd'];
        $this->careerTripleDoubles = $plrRow['c_td'];

        $this->careerPlayoffHighPoints = $plrRow['cp_pts'];
        $this->careerPlayoffHighRebounds = $plrRow['cp_reb'];
        $this->careerPlayoffHighAssists = $plrRow['cp_ast'];
        $this->careerPlayoffHighSteals = $plrRow['cp_stl'];
        $this->careerPlayoffHighBlocks = $plrRow['cp_blk'];

        $this->careerGamesPlayed = $plrRow['car_gm'];
        $this->careerMinutesPlayed = $plrRow['car_min'];
        $this->careerFieldGoalsMade = $plrRow['car_fgm'];
        $this->careerFieldGoalsAttempted = $plrRow['car_fga'];
        $this->careerFreeThrowsMade = $plrRow['car_ftm'];
        $this->careerFreeThrowsAttempted = $plrRow['car_fta'];
        $this->careerThreePointersMade = $plrRow['car_tgm'];
        $this->careerThreePointersAttempted = $plrRow['car_tga'];
        $this->careerOffensiveRebounds = $plrRow['car_orb'];
        $this->careerDefensiveRebounds = $plrRow['car_drb'];
        $this->careerTotalRebounds = $plrRow['car_reb'];
        $this->careerAssists = $plrRow['car_ast'];
        $this->careerSteals = $plrRow['car_stl'];
        $this->careerTurnovers = $plrRow['car_to'];
        $this->careerBlocks = $plrRow['car_blk'];
        $this->careerPersonalFouls = $plrRow['car_pf'];
        $this->careerPoints = StatsFormatter::calculatePoints($this->careerFieldGoalsMade, $this->careerFreeThrowsMade, $this->careerThreePointersMade);
    }

    /**
     * Fill stats from a historical player database row
     */
    protected function fillHistorical(array $plrRow): void
    {
        $this->seasonGamesPlayed = $plrRow['gm'];
        $this->seasonMinutes = $plrRow['min'];
        $this->seasonFieldGoalsMade = $plrRow['fgm'];
        $this->seasonFieldGoalsAttempted = $plrRow['fga'];
        $this->seasonFreeThrowsMade = $plrRow['ftm'];
        $this->seasonFreeThrowsAttempted = $plrRow['fta'];
        $this->seasonThreePointersMade = $plrRow['3gm'];
        $this->seasonThreePointersAttempted = $plrRow['3ga'];
        $this->seasonOffensiveRebounds = $plrRow['orb'];
        $this->seasonTotalRebounds = $plrRow['reb'];
        $this->seasonDefensiveRebounds = $this->seasonTotalRebounds - $this->seasonOffensiveRebounds;
        $this->seasonAssists = $plrRow['ast'];
        $this->seasonSteals = $plrRow['stl'];
        $this->seasonBlocks = $plrRow['blk'];
        $this->seasonTurnovers = $plrRow['tvr'];
        $this->seasonPersonalFouls = $plrRow['pf'];
        $this->seasonPoints = StatsFormatter::calculatePoints($this->seasonFieldGoalsMade, $this->seasonFreeThrowsMade, $this->seasonThreePointersMade);

        $this->seasonMinutesPerGame = StatsFormatter::formatPerGameAverage($this->seasonMinutes, $this->seasonGamesPlayed);
        $this->seasonFieldGoalsMadePerGame = StatsFormatter::formatAverage(StatsFormatter::safeDivide($this->seasonFieldGoalsMade, $this->seasonGamesPlayed));
        $this->seasonFieldGoalsAttemptedPerGame = StatsFormatter::formatAverage(StatsFormatter::safeDivide($this->seasonFieldGoalsAttempted, $this->seasonGamesPlayed));
        $this->seasonFreeThrowsMadePerGame = StatsFormatter::formatAverage(StatsFormatter::safeDivide($this->seasonFreeThrowsMade, $this->seasonGamesPlayed));
        $this->seasonFreeThrowsAttemptedPerGame = StatsFormatter::formatAverage(StatsFormatter::safeDivide($this->seasonFreeThrowsAttempted, $this->seasonGamesPlayed));
        $this->seasonThreePointersMadePerGame = StatsFormatter::formatAverage(StatsFormatter::safeDivide($this->seasonThreePointersMade, $this->seasonGamesPlayed));
        $this->seasonThreePointersAttemptedPerGame = StatsFormatter::formatAverage(StatsFormatter::safeDivide($this->seasonThreePointersAttempted, $this->seasonGamesPlayed));
        $this->seasonOffensiveReboundsPerGame = StatsFormatter::formatPerGameAverage($this->seasonOffensiveRebounds, $this->seasonGamesPlayed);
        $this->seasonDefensiveReboundsPerGame = StatsFormatter::formatPerGameAverage($this->seasonDefensiveRebounds, $this->seasonGamesPlayed);
        $this->seasonTotalReboundsPerGame = StatsFormatter::formatPerGameAverage($this->seasonTotalRebounds, $this->seasonGamesPlayed);
        $this->seasonAssistsPerGame = StatsFormatter::formatPerGameAverage($this->seasonAssists, $this->seasonGamesPlayed);
        $this->seasonStealsPerGame = StatsFormatter::formatPerGameAverage($this->seasonSteals, $this->seasonGamesPlayed);
        $this->seasonTurnoversPerGame = StatsFormatter::formatPerGameAverage($this->seasonTurnovers, $this->seasonGamesPlayed);
        $this->seasonBlocksPerGame = StatsFormatter::formatPerGameAverage($this->seasonBlocks, $this->seasonGamesPlayed);
        $this->seasonPersonalFoulsPerGame = StatsFormatter::formatPerGameAverage($this->seasonPersonalFouls, $this->seasonGamesPlayed);
        $this->seasonPointsPerGame = StatsFormatter::formatPerGameAverage($this->seasonPoints, $this->seasonGamesPlayed);

        $this->seasonFieldGoalPercentage = StatsFormatter::formatPercentage($this->seasonFieldGoalsMade, $this->seasonFieldGoalsAttempted);
        $this->seasonFreeThrowPercentage = StatsFormatter::formatPercentage($this->seasonFreeThrowsMade, $this->seasonFreeThrowsAttempted);
        $this->seasonThreePointPercentage = StatsFormatter::formatPercentage($this->seasonThreePointersMade, $this->seasonThreePointersAttempted);
    }

    /**
     * Fill stats from a boxscore info line (fixed-width format)
     */
    protected function fillBoxscoreStats(string $playerInfoLine): void
    {
        $this->name = trim(substr($playerInfoLine, 0, 16));
        $this->position = trim(substr($playerInfoLine, 16, 2));
        $this->playerID = trim(substr($playerInfoLine, 18, 6));
        $this->gameMinutesPlayed = substr($playerInfoLine, 24, 2);
        $this->gameFieldGoalsMade = substr($playerInfoLine, 26, 2);
        $this->gameFieldGoalsAttempted = substr($playerInfoLine, 28, 3);
        $this->gameFreeThrowsMade = substr($playerInfoLine, 31, 2);
        $this->gameFreeThrowsAttempted = substr($playerInfoLine, 33, 2);
        $this->gameThreePointersMade = substr($playerInfoLine, 35, 2);
        $this->gameThreePointersAttempted = substr($playerInfoLine, 37, 2);
        $this->gameOffensiveRebounds = substr($playerInfoLine, 39, 2);
        $this->gameDefensiveRebounds = substr($playerInfoLine, 41, 2);
        $this->gameAssists = substr($playerInfoLine, 43, 2);
        $this->gameSteals = substr($playerInfoLine, 45, 2);
        $this->gameTurnovers = substr($playerInfoLine, 47, 2);
        $this->gameBlocks = substr($playerInfoLine, 49, 2);
        $this->gamePersonalFouls = substr($playerInfoLine, 51, 2);
    }
}
