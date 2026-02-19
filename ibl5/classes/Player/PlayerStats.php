<?php

declare(strict_types=1);

namespace Player;

use BasketballStats\StatsFormatter;
use Player\Contracts\PlayerStatsInterface;
use Player\Contracts\PlayerStatsRepositoryInterface;

/**
 * PlayerStats - Player statistics data object
 *
 * Provides access to current season stats, career stats, season/career highs,
 * and per-game averages. Uses PlayerStatsRepository for all database operations.
 *
 * @see PlayerStatsInterface
 * @phpstan-import-type PlayerRow from \Services\CommonMysqliRepository
 */
class PlayerStats implements PlayerStatsInterface
{
    protected PlayerStatsRepositoryInterface $repository;

    public int|string $playerID;
    /** @var array<string, mixed>|null */
    public ?array $plr = null;

    public string $name;
    public string $position;
    public int $isRetired;

    public int $seasonGamesStarted;
    public int $seasonGamesPlayed;
    public int $seasonMinutes;
    public int $seasonFieldGoalsMade;
    public int $seasonFieldGoalsAttempted;
    public int $seasonFreeThrowsMade;
    public int $seasonFreeThrowsAttempted;
    public int $seasonThreePointersMade;
    public int $seasonThreePointersAttempted;
    public int $seasonOffensiveRebounds;
    public int $seasonDefensiveRebounds;
    public int $seasonTotalRebounds;
    public int $seasonAssists;
    public int $seasonSteals;
    public int $seasonTurnovers;
    public int $seasonBlocks;
    public int $seasonPersonalFouls;
    public int $seasonPoints;

    public string $seasonMinutesPerGame;
    public string $seasonFieldGoalsMadePerGame;
    public string $seasonFieldGoalsAttemptedPerGame;
    public string $seasonFreeThrowsMadePerGame;
    public string $seasonFreeThrowsAttemptedPerGame;
    public string $seasonThreePointersMadePerGame;
    public string $seasonThreePointersAttemptedPerGame;
    public string $seasonOffensiveReboundsPerGame;
    public string $seasonDefensiveReboundsPerGame;
    public string $seasonTotalReboundsPerGame;
    public string $seasonAssistsPerGame;
    public string $seasonStealsPerGame;
    public string $seasonTurnoversPerGame;
    public string $seasonBlocksPerGame;
    public string $seasonPersonalFoulsPerGame;
    public string $seasonPointsPerGame;

    public string $seasonFieldGoalPercentage;
    public string $seasonFreeThrowPercentage;
    public string $seasonThreePointPercentage;

    public int $seasonHighPoints;
    public int $seasonHighRebounds;
    public int $seasonHighAssists;
    public int $seasonHighSteals;
    public int $seasonHighBlocks;
    public int $seasonDoubleDoubles;
    public int $seasonTripleDoubles;

    public int $seasonPlayoffHighPoints;
    public int $seasonPlayoffHighRebounds;
    public int $seasonPlayoffHighAssists;
    public int $seasonPlayoffHighSteals;
    public int $seasonPlayoffHighBlocks;
    public int $seasonPlayoffDoubleDoubles;
    public int $seasonPlayoffTripleDoubles;

    public int $careerSeasonHighPoints;
    public int $careerSeasonHighRebounds;
    public int $careerSeasonHighAssists;
    public int $careerSeasonHighSteals;
    public int $careerSeasonHighBlocks;
    public int $careerDoubleDoubles;
    public int $careerTripleDoubles;

    public int $careerPlayoffHighPoints;
    public int $careerPlayoffHighRebounds;
    public int $careerPlayoffHighAssists;
    public int $careerPlayoffHighSteals;
    public int $careerPlayoffHighBlocks;
    public int $careerPlayoffDoubleDoubles;
    public int $careerPlayoffTripleDoubles;

    public int $careerGamesPlayed;
    public int $careerMinutesPlayed;
    public int $careerFieldGoalsMade;
    public int $careerFieldGoalsAttempted;
    public int $careerFreeThrowsMade;
    public int $careerFreeThrowsAttempted;
    public int $careerThreePointersMade;
    public int $careerThreePointersAttempted;
    public int $careerOffensiveRebounds;
    public int $careerDefensiveRebounds;
    public int $careerTotalRebounds;
    public int $careerAssists;
    public int $careerSteals;
    public int $careerTurnovers;
    public int $careerBlocks;
    public int $careerPersonalFouls;
    public int $careerPoints;

    public string $gameMinutesPlayed;
    public string $gameFieldGoalsMade;
    public string $gameFieldGoalsAttempted;
    public string $gameFreeThrowsMade;
    public string $gameFreeThrowsAttempted;
    public string $gameThreePointersMade;
    public string $gameThreePointersAttempted;
    public string $gameOffensiveRebounds;
    public string $gameDefensiveRebounds;
    public string $gameAssists;
    public string $gameSteals;
    public string $gameTurnovers;
    public string $gameBlocks;
    public string $gamePersonalFouls;

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
    public static function withPlayerID(\mysqli $db, int $playerID): self
    {
        $repository = new PlayerStatsRepository($db);
        $instance = new self($repository);
        $instance->loadByID($playerID);
        return $instance;
    }

    /**
     * @see PlayerStatsInterface::withPlayerObject()
     */
    public static function withPlayerObject(\mysqli $db, Player $player): PlayerStatsInterface
    {
        $repository = new PlayerStatsRepository($db);
        $instance = new self($repository);
        $instance->loadByID($player->playerID ?? 0);
        return $instance;
    }

    /**
     * @see PlayerStatsInterface::withPlrRow()
     */
    public static function withPlrRow(\mysqli $db, array $plrRow): PlayerStatsInterface
    {
        $repository = new PlayerStatsRepository($db);
        $instance = new self($repository);
        /** @var PlayerRow $plrRow */
        $instance->fill($plrRow);
        return $instance;
    }

    /**
     * @see PlayerStatsInterface::withHistoricalPlrRow()
     */
    public static function withHistoricalPlrRow(\mysqli $db, array $plrRow): PlayerStatsInterface
    {
        $repository = new PlayerStatsRepository($db);
        $instance = new self($repository);
        $instance->fillHistorical($plrRow);
        return $instance;
    }

    /**
     * @see PlayerStatsInterface::withBoxscoreInfoLine()
     */
    public static function withBoxscoreInfoLine(\mysqli $db, string $playerInfoLine): PlayerStatsInterface
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
        /** @var PlayerRow|null $plrRow */
        $plrRow = $this->repository->getPlayerStats($playerID);
        if ($plrRow !== null) {
            $this->fill($plrRow);
        }
    }

    /**
     * Fill stats from a current player database row
     *
     * @param PlayerRow $plrRow
     */
    protected function fill(array $plrRow): void
    {
        $this->playerID = $plrRow['pid'];
        $this->name = (string) $plrRow['name'];
        $this->position = (string) $plrRow['pos'];
        $this->isRetired = $plrRow['retired'] ?? 0;

        $this->seasonGamesStarted = (int) ($plrRow['stats_gs'] ?? 0);
        $this->seasonGamesPlayed = (int) ($plrRow['stats_gm'] ?? 0);
        $this->seasonMinutes = (int) ($plrRow['stats_min'] ?? 0);
        $this->seasonFieldGoalsMade = (int) ($plrRow['stats_fgm'] ?? 0);
        $this->seasonFieldGoalsAttempted = (int) ($plrRow['stats_fga'] ?? 0);
        $this->seasonFreeThrowsMade = (int) ($plrRow['stats_ftm'] ?? 0);
        $this->seasonFreeThrowsAttempted = (int) ($plrRow['stats_fta'] ?? 0);
        $this->seasonThreePointersMade = (int) ($plrRow['stats_3gm'] ?? 0);
        $this->seasonThreePointersAttempted = (int) ($plrRow['stats_3ga'] ?? 0);
        $this->seasonOffensiveRebounds = (int) ($plrRow['stats_orb'] ?? 0);
        $this->seasonDefensiveRebounds = (int) ($plrRow['stats_drb'] ?? 0);
        $this->seasonTotalRebounds = $this->seasonOffensiveRebounds + $this->seasonDefensiveRebounds;
        $this->seasonAssists = (int) ($plrRow['stats_ast'] ?? 0);
        $this->seasonSteals = (int) ($plrRow['stats_stl'] ?? 0);
        $this->seasonTurnovers = (int) ($plrRow['stats_to'] ?? 0);
        $this->seasonBlocks = (int) ($plrRow['stats_blk'] ?? 0);
        $this->seasonPersonalFouls = (int) ($plrRow['stats_pf'] ?? 0);
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

        $this->seasonHighPoints = (int) ($plrRow['sh_pts'] ?? 0);
        $this->seasonHighRebounds = (int) ($plrRow['sh_reb'] ?? 0);
        $this->seasonHighAssists = (int) ($plrRow['sh_ast'] ?? 0);
        $this->seasonHighSteals = (int) ($plrRow['sh_stl'] ?? 0);
        $this->seasonHighBlocks = (int) ($plrRow['sh_blk'] ?? 0);
        $this->seasonDoubleDoubles = (int) ($plrRow['s_dd'] ?? 0);
        $this->seasonTripleDoubles = (int) ($plrRow['s_td'] ?? 0);

        $this->seasonPlayoffHighPoints = (int) ($plrRow['sp_pts'] ?? 0);
        $this->seasonPlayoffHighRebounds = (int) ($plrRow['sp_reb'] ?? 0);
        $this->seasonPlayoffHighAssists = (int) ($plrRow['sp_ast'] ?? 0);
        $this->seasonPlayoffHighSteals = (int) ($plrRow['sp_stl'] ?? 0);
        $this->seasonPlayoffHighBlocks = (int) ($plrRow['sp_blk'] ?? 0);
        $this->seasonPlayoffDoubleDoubles = 0;
        $this->seasonPlayoffTripleDoubles = 0;

        $this->careerSeasonHighPoints = (int) ($plrRow['ch_pts'] ?? 0);
        $this->careerSeasonHighRebounds = (int) ($plrRow['ch_reb'] ?? 0);
        $this->careerSeasonHighAssists = (int) ($plrRow['ch_ast'] ?? 0);
        $this->careerSeasonHighSteals = (int) ($plrRow['ch_stl'] ?? 0);
        $this->careerSeasonHighBlocks = (int) ($plrRow['ch_blk'] ?? 0);
        $this->careerDoubleDoubles = (int) ($plrRow['c_dd'] ?? 0);
        $this->careerTripleDoubles = (int) ($plrRow['c_td'] ?? 0);

        $this->careerPlayoffHighPoints = (int) ($plrRow['cp_pts'] ?? 0);
        $this->careerPlayoffHighRebounds = (int) ($plrRow['cp_reb'] ?? 0);
        $this->careerPlayoffHighAssists = (int) ($plrRow['cp_ast'] ?? 0);
        $this->careerPlayoffHighSteals = (int) ($plrRow['cp_stl'] ?? 0);
        $this->careerPlayoffHighBlocks = (int) ($plrRow['cp_blk'] ?? 0);
        $this->careerPlayoffDoubleDoubles = 0;
        $this->careerPlayoffTripleDoubles = 0;

        $this->careerGamesPlayed = (int) ($plrRow['car_gm'] ?? 0);
        $this->careerMinutesPlayed = (int) ($plrRow['car_min'] ?? 0);
        $this->careerFieldGoalsMade = (int) ($plrRow['car_fgm'] ?? 0);
        $this->careerFieldGoalsAttempted = (int) ($plrRow['car_fga'] ?? 0);
        $this->careerFreeThrowsMade = (int) ($plrRow['car_ftm'] ?? 0);
        $this->careerFreeThrowsAttempted = (int) ($plrRow['car_fta'] ?? 0);
        $this->careerThreePointersMade = (int) ($plrRow['car_tgm'] ?? 0);
        $this->careerThreePointersAttempted = (int) ($plrRow['car_tga'] ?? 0);
        $this->careerOffensiveRebounds = (int) ($plrRow['car_orb'] ?? 0);
        $this->careerDefensiveRebounds = (int) ($plrRow['car_drb'] ?? 0);
        $this->careerTotalRebounds = (int) ($plrRow['car_reb'] ?? 0);
        $this->careerAssists = (int) ($plrRow['car_ast'] ?? 0);
        $this->careerSteals = (int) ($plrRow['car_stl'] ?? 0);
        $this->careerTurnovers = (int) ($plrRow['car_to'] ?? 0);
        $this->careerBlocks = (int) ($plrRow['car_blk'] ?? 0);
        $this->careerPersonalFouls = (int) ($plrRow['car_pf'] ?? 0);
        $this->careerPoints = StatsFormatter::calculatePoints($this->careerFieldGoalsMade, $this->careerFreeThrowsMade, $this->careerThreePointersMade);
    }

    /**
     * Fill stats from a historical player database row
     *
     * @param array<string, mixed> $plrRow
     */
    protected function fillHistorical(array $plrRow): void
    {
        /** @var array{games: ?int, minutes: ?int, fgm: ?int, fga: ?int, ftm: ?int, fta: ?int, tgm: ?int, tga: ?int, orb: ?int, reb: ?int, ast: ?int, stl: ?int, blk: ?int, tvr: ?int, pf: ?int, ...} $plrRow */
        $this->seasonGamesStarted = 0;
        $this->seasonGamesPlayed = $plrRow['games'] ?? 0;
        $this->seasonMinutes = $plrRow['minutes'] ?? 0;
        $this->seasonFieldGoalsMade = $plrRow['fgm'] ?? 0;
        $this->seasonFieldGoalsAttempted = $plrRow['fga'] ?? 0;
        $this->seasonFreeThrowsMade = $plrRow['ftm'] ?? 0;
        $this->seasonFreeThrowsAttempted = $plrRow['fta'] ?? 0;
        $this->seasonThreePointersMade = $plrRow['tgm'] ?? 0;
        $this->seasonThreePointersAttempted = $plrRow['tga'] ?? 0;
        $this->seasonOffensiveRebounds = $plrRow['orb'] ?? 0;
        $this->seasonTotalRebounds = $plrRow['reb'] ?? 0;
        $this->seasonDefensiveRebounds = $this->seasonTotalRebounds - $this->seasonOffensiveRebounds;
        $this->seasonAssists = $plrRow['ast'] ?? 0;
        $this->seasonSteals = $plrRow['stl'] ?? 0;
        $this->seasonBlocks = $plrRow['blk'] ?? 0;
        $this->seasonTurnovers = $plrRow['tvr'] ?? 0;
        $this->seasonPersonalFouls = $plrRow['pf'] ?? 0;
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
