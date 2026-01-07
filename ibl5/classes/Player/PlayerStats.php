<?php

declare(strict_types=1);

namespace Player;

use Player\Contracts\PlayerStatsInterface;
use Player\Contracts\PlayerStatsRepositoryInterface;
use Statistics\StatsFormatter;

/**
 * PlayerStats - Player statistics value object
 * 
 * @see PlayerStatsInterface
 */
class PlayerStats implements PlayerStatsInterface
{
    protected PlayerStatsRepositoryInterface $repository;

    public ?int $playerID = null;
    public $plr;

    public ?string $name = null;
    public ?string $position = null;
    public ?int $isRetired = null;
    
    public int $seasonGamesStarted = 0;
    public int $seasonGamesPlayed = 0;
    public int $seasonMinutes = 0;
    public int $seasonFieldGoalsMade = 0;
    public int $seasonFieldGoalsAttempted = 0;
    public int $seasonFreeThrowsMade = 0;
    public int $seasonFreeThrowsAttempted = 0;
    public int $seasonThreePointersMade = 0;
    public int $seasonThreePointersAttempted = 0;
    public int $seasonOffensiveRebounds = 0;
    public int $seasonDefensiveRebounds = 0;
    public int $seasonTotalRebounds = 0;
    public int $seasonAssists = 0;
    public int $seasonSteals = 0;
    public int $seasonTurnovers = 0;
    public int $seasonBlocks = 0;
    public int $seasonPersonalFouls = 0;
    public int $seasonPoints = 0;

    public string $seasonMinutesPerGame = '0.0';
    public string $seasonFieldGoalsMadePerGame = '0.0';
    public string $seasonFieldGoalsAttemptedPerGame = '0.0';
    public string $seasonFreeThrowsMadePerGame = '0.0';
    public string $seasonFreeThrowsAttemptedPerGame = '0.0';
    public string $seasonThreePointersMadePerGame = '0.0';
    public string $seasonThreePointersAttemptedPerGame = '0.0';
    public string $seasonOffensiveReboundsPerGame = '0.0';
    public string $seasonDefensiveReboundsPerGame = '0.0';
    public string $seasonTotalReboundsPerGame = '0.0';
    public string $seasonAssistsPerGame = '0.0';
    public string $seasonStealsPerGame = '0.0';
    public string $seasonTurnoversPerGame = '0.0';
    public string $seasonBlocksPerGame = '0.0';
    public string $seasonPersonalFoulsPerGame = '0.0';
    public string $seasonPointsPerGame = '0.0';
    
    public string $seasonFieldGoalPercentage = '.000';
    public string $seasonFreeThrowPercentage = '.000';
    public string $seasonThreePointPercentage = '.000';

    public int $seasonHighPoints = 0;
    public int $seasonHighRebounds = 0;
    public int $seasonHighAssists = 0;
    public int $seasonHighSteals = 0;
    public int $seasonHighBlocks = 0;
    public int $seasonDoubleDoubles = 0;
    public int $seasonTripleDoubles = 0;

    public int $seasonPlayoffHighPoints = 0;
    public int $seasonPlayoffHighRebounds = 0;
    public int $seasonPlayoffHighAssists = 0;
    public int $seasonPlayoffHighSteals = 0;
    public int $seasonPlayoffHighBlocks = 0;

    public int $careerSeasonHighPoints = 0;
    public int $careerSeasonHighRebounds = 0;
    public int $careerSeasonHighAssists = 0;
    public int $careerSeasonHighSteals = 0;
    public int $careerSeasonHighBlocks = 0;
    public int $careerDoubleDoubles = 0;
    public int $careerTripleDoubles = 0;

    public int $careerPlayoffHighPoints = 0;
    public int $careerPlayoffHighRebounds = 0;
    public int $careerPlayoffHighAssists = 0;
    public int $careerPlayoffHighSteals = 0;
    public int $careerPlayoffHighBlocks = 0;

    public int $careerGamesPlayed = 0;
    public int $careerMinutesPlayed = 0;
    public int $careerFieldGoalsMade = 0;
    public int $careerFieldGoalsAttempted = 0;
    public int $careerFreeThrowsMade = 0;
    public int $careerFreeThrowsAttempted = 0;
    public int $careerThreePointersMade = 0;
    public int $careerThreePointersAttempted = 0;
    public int $careerOffensiveRebounds = 0;
    public int $careerDefensiveRebounds = 0;
    public int $careerTotalRebounds = 0;
    public int $careerAssists = 0;
    public int $careerSteals = 0;
    public int $careerTurnovers = 0;
    public int $careerBlocks = 0;
    public int $careerPersonalFouls = 0;
    public int $careerPoints = 0;

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

    public function __construct(PlayerStatsRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @see PlayerStatsInterface::withPlayerID
     */
    public static function withPlayerID(object $db, int $playerID): PlayerStatsInterface
    {
        $repository = new PlayerStatsRepository($db);
        $instance = new self($repository);
        $instance->loadByID($playerID);
        return $instance;
    }

    /**
     * @see PlayerStatsInterface::withPlayerObject
     */
    public static function withPlayerObject(object $db, Player $player): PlayerStatsInterface
    {
        $repository = new PlayerStatsRepository($db);
        $instance = new self($repository);
        $instance->loadByID($player->playerID);
        return $instance;
    }

    /**
     * @see PlayerStatsInterface::withPlrRow
     */
    public static function withPlrRow(object $db, array $plrRow): PlayerStatsInterface
    {
        $repository = new PlayerStatsRepository($db);
        $instance = new self($repository);
        $instance->fill($plrRow);
        return $instance;
    }

    /**
     * @see PlayerStatsInterface::withHistoricalPlrRow
     */
    public static function withHistoricalPlrRow(object $db, array $plrRow): PlayerStatsInterface
    {
        $repository = new PlayerStatsRepository($db);
        $instance = new self($repository);
        $instance->fillHistorical($plrRow);
        return $instance;
    }

    /**
     * @see PlayerStatsInterface::withBoxscoreInfoLine
     */
    public static function withBoxscoreInfoLine(object $db, string $playerInfoLine): PlayerStatsInterface
    {
        $repository = new PlayerStatsRepository($db);
        $instance = new self($repository);
        $instance->fillBoxscoreStats($playerInfoLine);
        return $instance;
    }

    /**
     * @see PlayerStatsInterface::getPlayerID
     */
    public function getPlayerID(): ?int
    {
        return $this->playerID;
    }

    /**
     * @see PlayerStatsInterface::getName
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * @see PlayerStatsInterface::getPosition
     */
    public function getPosition(): ?string
    {
        return $this->position;
    }

    /**
     * @see PlayerStatsInterface::isRetired
     */
    public function isRetired(): ?int
    {
        return $this->isRetired;
    }

    /**
     * @see PlayerStatsInterface::getSeasonTotals
     */
    public function getSeasonTotals(): array
    {
        return [
            'gamesStarted' => $this->seasonGamesStarted,
            'gamesPlayed' => $this->seasonGamesPlayed,
            'minutes' => $this->seasonMinutes,
            'fieldGoalsMade' => $this->seasonFieldGoalsMade,
            'fieldGoalsAttempted' => $this->seasonFieldGoalsAttempted,
            'freeThrowsMade' => $this->seasonFreeThrowsMade,
            'freeThrowsAttempted' => $this->seasonFreeThrowsAttempted,
            'threePointersMade' => $this->seasonThreePointersMade,
            'threePointersAttempted' => $this->seasonThreePointersAttempted,
            'offensiveRebounds' => $this->seasonOffensiveRebounds,
            'defensiveRebounds' => $this->seasonDefensiveRebounds,
            'totalRebounds' => $this->seasonTotalRebounds,
            'assists' => $this->seasonAssists,
            'steals' => $this->seasonSteals,
            'turnovers' => $this->seasonTurnovers,
            'blocks' => $this->seasonBlocks,
            'personalFouls' => $this->seasonPersonalFouls,
            'points' => $this->seasonPoints
        ];
    }

    /**
     * @see PlayerStatsInterface::getSeasonAverages
     */
    public function getSeasonAverages(): array
    {
        return [
            'minutesPerGame' => $this->seasonMinutesPerGame,
            'pointsPerGame' => $this->seasonPointsPerGame,
            'reboundsPerGame' => $this->seasonTotalReboundsPerGame,
            'assistsPerGame' => $this->seasonAssistsPerGame,
            'stealsPerGame' => $this->seasonStealsPerGame,
            'blocksPerGame' => $this->seasonBlocksPerGame,
            'turnoversPerGame' => $this->seasonTurnoversPerGame,
            'fieldGoalPercentage' => $this->seasonFieldGoalPercentage,
            'freeThrowPercentage' => $this->seasonFreeThrowPercentage,
            'threePointPercentage' => $this->seasonThreePointPercentage
        ];
    }

    /**
     * @see PlayerStatsInterface::getSeasonHighs
     */
    public function getSeasonHighs(): array
    {
        return [
            'points' => $this->seasonHighPoints,
            'rebounds' => $this->seasonHighRebounds,
            'assists' => $this->seasonHighAssists,
            'steals' => $this->seasonHighSteals,
            'blocks' => $this->seasonHighBlocks,
            'doubleDoubles' => $this->seasonDoubleDoubles,
            'tripleDoubles' => $this->seasonTripleDoubles
        ];
    }

    /**
     * @see PlayerStatsInterface::getCareerHighs
     */
    public function getCareerHighs(): array
    {
        return [
            'points' => $this->careerSeasonHighPoints,
            'rebounds' => $this->careerSeasonHighRebounds,
            'assists' => $this->careerSeasonHighAssists,
            'steals' => $this->careerSeasonHighSteals,
            'blocks' => $this->careerSeasonHighBlocks,
            'doubleDoubles' => $this->careerDoubleDoubles,
            'tripleDoubles' => $this->careerTripleDoubles,
            'playoffPoints' => $this->careerPlayoffHighPoints,
            'playoffRebounds' => $this->careerPlayoffHighRebounds,
            'playoffAssists' => $this->careerPlayoffHighAssists,
            'playoffSteals' => $this->careerPlayoffHighSteals,
            'playoffBlocks' => $this->careerPlayoffHighBlocks
        ];
    }

    /**
     * @see PlayerStatsInterface::getCareerTotals
     */
    public function getCareerTotals(): array
    {
        return [
            'gamesPlayed' => $this->careerGamesPlayed,
            'minutesPlayed' => $this->careerMinutesPlayed,
            'fieldGoalsMade' => $this->careerFieldGoalsMade,
            'fieldGoalsAttempted' => $this->careerFieldGoalsAttempted,
            'freeThrowsMade' => $this->careerFreeThrowsMade,
            'freeThrowsAttempted' => $this->careerFreeThrowsAttempted,
            'threePointersMade' => $this->careerThreePointersMade,
            'threePointersAttempted' => $this->careerThreePointersAttempted,
            'offensiveRebounds' => $this->careerOffensiveRebounds,
            'defensiveRebounds' => $this->careerDefensiveRebounds,
            'totalRebounds' => $this->careerTotalRebounds,
            'assists' => $this->careerAssists,
            'steals' => $this->careerSteals,
            'turnovers' => $this->careerTurnovers,
            'blocks' => $this->careerBlocks,
            'personalFouls' => $this->careerPersonalFouls,
            'points' => $this->careerPoints
        ];
    }

    protected function loadByID(int $playerID): void
    {
        $plrRow = $this->repository->getPlayerStats($playerID);
        if ($plrRow) {
            $this->fill($plrRow);
        }
    }

    protected function fill(array $plrRow): void
    {
        $this->playerID = (int) $plrRow['pid'];
        $this->name = $plrRow['name'];
        $this->position = $plrRow['pos'];
        $this->isRetired = (int) $plrRow['retired'];

        $this->seasonGamesStarted = (int) $plrRow['stats_gs'];
        $this->seasonGamesPlayed = (int) $plrRow['stats_gm'];
        $this->seasonMinutes = (int) $plrRow['stats_min'];
        $this->seasonFieldGoalsMade = (int) $plrRow['stats_fgm'];
        $this->seasonFieldGoalsAttempted = (int) $plrRow['stats_fga'];
        $this->seasonFreeThrowsMade = (int) $plrRow['stats_ftm'];
        $this->seasonFreeThrowsAttempted = (int) $plrRow['stats_fta'];
        $this->seasonThreePointersMade = (int) $plrRow['stats_3gm'];
        $this->seasonThreePointersAttempted = (int) $plrRow['stats_3ga'];
        $this->seasonOffensiveRebounds = (int) $plrRow['stats_orb'];
        $this->seasonDefensiveRebounds = (int) $plrRow['stats_drb'];
        $this->seasonTotalRebounds = $this->seasonOffensiveRebounds + $this->seasonDefensiveRebounds;
        $this->seasonAssists = (int) $plrRow['stats_ast'];
        $this->seasonSteals = (int) $plrRow['stats_stl'];
        $this->seasonTurnovers = (int) $plrRow['stats_to'];
        $this->seasonBlocks = (int) $plrRow['stats_blk'];
        $this->seasonPersonalFouls = (int) $plrRow['stats_pf'];
        $this->seasonPoints = StatsFormatter::calculatePoints(
            $this->seasonFieldGoalsMade, 
            $this->seasonFreeThrowsMade, 
            $this->seasonThreePointersMade
        );

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
        
        $this->seasonHighPoints = (int) $plrRow['sh_pts'];
        $this->seasonHighRebounds = (int) $plrRow['sh_reb'];
        $this->seasonHighAssists = (int) $plrRow['sh_ast'];
        $this->seasonHighSteals = (int) $plrRow['sh_stl'];
        $this->seasonHighBlocks = (int) $plrRow['sh_blk'];
        $this->seasonDoubleDoubles = (int) $plrRow['s_dd'];
        $this->seasonTripleDoubles = (int) $plrRow['s_td'];

        $this->seasonPlayoffHighPoints = (int) $plrRow['sp_pts'];
        $this->seasonPlayoffHighRebounds = (int) $plrRow['sp_reb'];
        $this->seasonPlayoffHighAssists = (int) $plrRow['sp_ast'];
        $this->seasonPlayoffHighSteals = (int) $plrRow['sp_stl'];
        $this->seasonPlayoffHighBlocks = (int) $plrRow['sp_blk'];

        $this->careerSeasonHighPoints = (int) $plrRow['ch_pts'];
        $this->careerSeasonHighRebounds = (int) $plrRow['ch_reb'];
        $this->careerSeasonHighAssists = (int) $plrRow['ch_ast'];
        $this->careerSeasonHighSteals = (int) $plrRow['ch_stl'];
        $this->careerSeasonHighBlocks = (int) $plrRow['ch_blk'];
        $this->careerDoubleDoubles = (int) $plrRow['c_dd'];
        $this->careerTripleDoubles = (int) $plrRow['c_td'];

        $this->careerPlayoffHighPoints = (int) $plrRow['cp_pts'];
        $this->careerPlayoffHighRebounds = (int) $plrRow['cp_reb'];
        $this->careerPlayoffHighAssists = (int) $plrRow['cp_ast'];
        $this->careerPlayoffHighSteals = (int) $plrRow['cp_stl'];
        $this->careerPlayoffHighBlocks = (int) $plrRow['cp_blk'];

        $this->careerGamesPlayed = (int) $plrRow['car_gm'];
        $this->careerMinutesPlayed = (int) $plrRow['car_min'];
        $this->careerFieldGoalsMade = (int) $plrRow['car_fgm'];
        $this->careerFieldGoalsAttempted = (int) $plrRow['car_fga'];
        $this->careerFreeThrowsMade = (int) $plrRow['car_ftm'];
        $this->careerFreeThrowsAttempted = (int) $plrRow['car_fta'];
        $this->careerThreePointersMade = (int) $plrRow['car_tgm'];
        $this->careerThreePointersAttempted = (int) $plrRow['car_tga'];
        $this->careerOffensiveRebounds = (int) $plrRow['car_orb'];
        $this->careerDefensiveRebounds = (int) $plrRow['car_drb'];
        $this->careerTotalRebounds = (int) $plrRow['car_reb'];
        $this->careerAssists = (int) $plrRow['car_ast'];
        $this->careerSteals = (int) $plrRow['car_stl'];
        $this->careerTurnovers = (int) $plrRow['car_to'];
        $this->careerBlocks = (int) $plrRow['car_blk'];
        $this->careerPersonalFouls = (int) $plrRow['car_pf'];
        $this->careerPoints = StatsFormatter::calculatePoints(
            $this->careerFieldGoalsMade, 
            $this->careerFreeThrowsMade, 
            $this->careerThreePointersMade
        );
    }

    protected function fillHistorical(array $plrRow): void
    {
        $this->seasonGamesPlayed = (int) $plrRow['gm'];
        $this->seasonMinutes = (int) $plrRow['min'];
        $this->seasonFieldGoalsMade = (int) $plrRow['fgm'];
        $this->seasonFieldGoalsAttempted = (int) $plrRow['fga'];
        $this->seasonFreeThrowsMade = (int) $plrRow['ftm'];
        $this->seasonFreeThrowsAttempted = (int) $plrRow['fta'];
        $this->seasonThreePointersMade = (int) $plrRow['3gm'];
        $this->seasonThreePointersAttempted = (int) $plrRow['3ga'];
        $this->seasonOffensiveRebounds = (int) $plrRow['orb'];
        $this->seasonTotalRebounds = (int) $plrRow['reb'];
        $this->seasonDefensiveRebounds = $this->seasonTotalRebounds - $this->seasonOffensiveRebounds;
        $this->seasonAssists = (int) $plrRow['ast'];
        $this->seasonSteals = (int) $plrRow['stl'];
        $this->seasonBlocks = (int) $plrRow['blk'];
        $this->seasonTurnovers = (int) $plrRow['tvr'];
        $this->seasonPersonalFouls = (int) $plrRow['pf'];
        $this->seasonPoints = StatsFormatter::calculatePoints(
            $this->seasonFieldGoalsMade, 
            $this->seasonFreeThrowsMade, 
            $this->seasonThreePointersMade
        );

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

    protected function fillBoxscoreStats(string $playerInfoLine): void
    {
        $this->name = trim(substr($playerInfoLine, 0, 16));
        $this->position = trim(substr($playerInfoLine, 16, 2));
        $this->playerID = (int) trim(substr($playerInfoLine, 18, 6));
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
