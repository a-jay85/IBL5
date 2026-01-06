<?php

declare(strict_types=1);

use Statistics\StatsFormatter;
use LeagueStats\LeagueStatsRepository;

class TeamStats
{
    protected LeagueStatsRepository $repository;

    public $seasonOffenseGamesPlayed;
    public $seasonOffenseTotalFieldGoalsMade;
    public $seasonOffenseTotalFieldGoalsAttempted;
    public $seasonOffenseTotalFreeThrowsMade;
    public $seasonOffenseTotalFreeThrowsAttempted;
    public $seasonOffenseTotalThreePointersMade;
    public $seasonOffenseTotalThreePointersAttempted;
    public $seasonOffenseTotalOffensiveRebounds;
    public $seasonOffenseTotalDefensiveRebounds;
    public $seasonOffenseTotalRebounds;
    public $seasonOffenseTotalAssists;
    public $seasonOffenseTotalSteals;
    public $seasonOffenseTotalTurnovers;
    public $seasonOffenseTotalBlocks;
    public $seasonOffenseTotalPersonalFouls;
    public $seasonOffenseTotalPoints;
    
    public $seasonOffenseFieldGoalsMadePerGame;
    public $seasonOffenseFieldGoalsAttemptedPerGame;
    public $seasonOffenseFreeThrowsMadePerGame;
    public $seasonOffenseFreeThrowsAttemptedPerGame;
    public $seasonOffenseThreePointersMadePerGame;
    public $seasonOffenseThreePointersAttemptedPerGame;
    public $seasonOffenseOffensiveReboundsPerGame;
    public $seasonOffenseDefensiveReboundsPerGame;
    public $seasonOffenseTotalReboundsPerGame;
    public $seasonOffenseAssistsPerGame;
    public $seasonOffenseStealsPerGame;
    public $seasonOffenseTurnoversPerGame;
    public $seasonOffenseBlocksPerGame;
    public $seasonOffensePersonalFoulsPerGame;
    public $seasonOffensePointsPerGame;

    public $seasonOffenseFieldGoalPercentage;
    public $seasonOffenseFreeThrowPercentage;
    public $seasonOffenseThreePointPercentage;

    public $seasonDefenseGamesPlayed;
    public $seasonDefenseTotalFieldGoalsMade;
    public $seasonDefenseTotalFieldGoalsAttempted;
    public $seasonDefenseTotalFreeThrowsMade;
    public $seasonDefenseTotalFreeThrowsAttempted;
    public $seasonDefenseTotalThreePointersMade;
    public $seasonDefenseTotalThreePointersAttempted;
    public $seasonDefenseTotalOffensiveRebounds;
    public $seasonDefenseTotalDefensiveRebounds;
    public $seasonDefenseTotalRebounds;
    public $seasonDefenseTotalAssists;
    public $seasonDefenseTotalSteals;
    public $seasonDefenseTotalTurnovers;
    public $seasonDefenseTotalBlocks;
    public $seasonDefenseTotalPersonalFouls;
    public $seasonDefenseTotalPoints;
    
    public $seasonDefenseFieldGoalsMadePerGame;
    public $seasonDefenseFieldGoalsAttemptedPerGame;
    public $seasonDefenseFreeThrowsMadePerGame;
    public $seasonDefenseFreeThrowsAttemptedPerGame;
    public $seasonDefenseThreePointersMadePerGame;
    public $seasonDefenseThreePointersAttemptedPerGame;
    public $seasonDefenseOffensiveReboundsPerGame;
    public $seasonDefenseDefensiveReboundsPerGame;
    public $seasonDefenseTotalReboundsPerGame;
    public $seasonDefenseAssistsPerGame;
    public $seasonDefenseStealsPerGame;
    public $seasonDefenseTurnoversPerGame;
    public $seasonDefenseBlocksPerGame;
    public $seasonDefensePersonalFoulsPerGame;
    public $seasonDefensePointsPerGame;

    public $seasonDefenseFieldGoalPercentage;
    public $seasonDefenseFreeThrowPercentage;
    public $seasonDefenseThreePointPercentage;

    public function __construct(LeagueStatsRepository $repository)
    {
        $this->repository = $repository;
    }

    public static function withTeamName($db, string $teamName): self
    {
        $repository = new LeagueStatsRepository($db);
        $instance = new self($repository);
        $instance->loadByTeamName($teamName);
        return $instance;
    }

    protected function loadByTeamName(string $teamName): void
    {
        $offenseTotalsRow = $this->repository->getTeamOffenseStats($teamName);
        if ($offenseTotalsRow) {
            $this->fillOffenseTotals($offenseTotalsRow);
        }

        $defenseTotalsRow = $this->repository->getTeamDefenseStats($teamName);
        if ($defenseTotalsRow) {
            $this->fillDefenseTotals($defenseTotalsRow);
        }
    }

    protected function fillOffenseTotals(array $offenseTotalsRow)
    {
        $this->seasonOffenseGamesPlayed = $offenseTotalsRow['games'];
        $this->seasonOffenseTotalFieldGoalsMade = $offenseTotalsRow['fgm'];
        $this->seasonOffenseTotalFieldGoalsAttempted = $offenseTotalsRow['fga'];
        $this->seasonOffenseTotalFreeThrowsMade = $offenseTotalsRow['ftm'];
        $this->seasonOffenseTotalFreeThrowsAttempted = $offenseTotalsRow['fta'];
        $this->seasonOffenseTotalThreePointersMade = $offenseTotalsRow['tgm'];
        $this->seasonOffenseTotalThreePointersAttempted = $offenseTotalsRow['tga'];
        $this->seasonOffenseTotalOffensiveRebounds = $offenseTotalsRow['orb'];
        $this->seasonOffenseTotalDefensiveRebounds = $this->seasonOffenseTotalRebounds - $this->seasonOffenseTotalOffensiveRebounds;
        $this->seasonOffenseTotalRebounds = $offenseTotalsRow['reb'];
        $this->seasonOffenseTotalAssists = $offenseTotalsRow['ast'];
        $this->seasonOffenseTotalSteals = $offenseTotalsRow['stl'];
        $this->seasonOffenseTotalTurnovers = $offenseTotalsRow['tvr'];
        $this->seasonOffenseTotalBlocks = $offenseTotalsRow['blk'];
        $this->seasonOffenseTotalPersonalFouls = $offenseTotalsRow['pf'];
        $this->seasonOffenseTotalPoints = StatsFormatter::calculatePoints($this->seasonOffenseTotalFieldGoalsMade, $this->seasonOffenseTotalFreeThrowsMade, $this->seasonOffenseTotalThreePointersMade);

        $this->seasonOffenseFieldGoalsMadePerGame = StatsFormatter::formatPerGameAverage($this->seasonOffenseTotalFieldGoalsMade, $this->seasonOffenseGamesPlayed);
        $this->seasonOffenseFieldGoalsAttemptedPerGame = StatsFormatter::formatPerGameAverage($this->seasonOffenseTotalFieldGoalsAttempted, $this->seasonOffenseGamesPlayed);
        $this->seasonOffenseFreeThrowsMadePerGame = StatsFormatter::formatPerGameAverage($this->seasonOffenseTotalFreeThrowsMade, $this->seasonOffenseGamesPlayed);
        $this->seasonOffenseFreeThrowsAttemptedPerGame = StatsFormatter::formatPerGameAverage($this->seasonOffenseTotalFreeThrowsAttempted, $this->seasonOffenseGamesPlayed);
        $this->seasonOffenseThreePointersMadePerGame = StatsFormatter::formatPerGameAverage($this->seasonOffenseTotalThreePointersMade, $this->seasonOffenseGamesPlayed);
        $this->seasonOffenseThreePointersAttemptedPerGame = StatsFormatter::formatPerGameAverage($this->seasonOffenseTotalThreePointersAttempted, $this->seasonOffenseGamesPlayed);
        $this->seasonOffenseOffensiveReboundsPerGame = StatsFormatter::formatPerGameAverage($this->seasonOffenseTotalOffensiveRebounds, $this->seasonOffenseGamesPlayed);
        $this->seasonOffenseDefensiveReboundsPerGame = StatsFormatter::formatPerGameAverage($this->seasonOffenseTotalDefensiveRebounds, $this->seasonOffenseGamesPlayed);
        $this->seasonOffenseTotalReboundsPerGame = StatsFormatter::formatPerGameAverage($this->seasonOffenseTotalRebounds, $this->seasonOffenseGamesPlayed);
        $this->seasonOffenseAssistsPerGame = StatsFormatter::formatPerGameAverage($this->seasonOffenseTotalAssists, $this->seasonOffenseGamesPlayed);
        $this->seasonOffenseStealsPerGame = StatsFormatter::formatPerGameAverage($this->seasonOffenseTotalSteals, $this->seasonOffenseGamesPlayed);
        $this->seasonOffenseTurnoversPerGame = StatsFormatter::formatPerGameAverage($this->seasonOffenseTotalTurnovers, $this->seasonOffenseGamesPlayed);
        $this->seasonOffenseBlocksPerGame = StatsFormatter::formatPerGameAverage($this->seasonOffenseTotalBlocks, $this->seasonOffenseGamesPlayed);
        $this->seasonOffensePersonalFoulsPerGame = StatsFormatter::formatPerGameAverage($this->seasonOffenseTotalPersonalFouls, $this->seasonOffenseGamesPlayed);
        $this->seasonOffensePointsPerGame = StatsFormatter::formatPerGameAverage($this->seasonOffenseTotalPoints, $this->seasonOffenseGamesPlayed);

        $this->seasonOffenseFieldGoalPercentage = StatsFormatter::formatPercentage($this->seasonOffenseTotalFieldGoalsMade, $this->seasonOffenseTotalFieldGoalsAttempted);
        $this->seasonOffenseFreeThrowPercentage = StatsFormatter::formatPercentage($this->seasonOffenseTotalFreeThrowsMade, $this->seasonOffenseTotalFreeThrowsAttempted);
        $this->seasonOffenseThreePointPercentage = StatsFormatter::formatPercentage($this->seasonOffenseTotalThreePointersMade, $this->seasonOffenseTotalThreePointersAttempted);
    }

    protected function fillDefenseTotals(array $defenseTotalsRow)
    {
        $this->seasonDefenseGamesPlayed = $defenseTotalsRow['games'];
        $this->seasonDefenseTotalFieldGoalsMade = $defenseTotalsRow['fgm'];
        $this->seasonDefenseTotalFieldGoalsAttempted = $defenseTotalsRow['fga'];
        $this->seasonDefenseTotalFreeThrowsMade = $defenseTotalsRow['ftm'];
        $this->seasonDefenseTotalFreeThrowsAttempted = $defenseTotalsRow['fta'];
        $this->seasonDefenseTotalThreePointersMade = $defenseTotalsRow['tgm'];
        $this->seasonDefenseTotalThreePointersAttempted = $defenseTotalsRow['tga'];
        $this->seasonDefenseTotalOffensiveRebounds = $defenseTotalsRow['orb'];
        $this->seasonDefenseTotalDefensiveRebounds = $this->seasonDefenseTotalRebounds - $this->seasonDefenseTotalOffensiveRebounds;
        $this->seasonDefenseTotalRebounds = $defenseTotalsRow['reb'];
        $this->seasonDefenseTotalAssists = $defenseTotalsRow['ast'];
        $this->seasonDefenseTotalSteals = $defenseTotalsRow['stl'];
        $this->seasonDefenseTotalTurnovers = $defenseTotalsRow['tvr'];
        $this->seasonDefenseTotalBlocks = $defenseTotalsRow['blk'];
        $this->seasonDefenseTotalPersonalFouls = $defenseTotalsRow['pf'];
        $this->seasonDefenseTotalPoints = StatsFormatter::calculatePoints($this->seasonDefenseTotalFieldGoalsMade, $this->seasonDefenseTotalFreeThrowsMade, $this->seasonDefenseTotalThreePointersMade);

        $this->seasonDefenseFieldGoalsMadePerGame = StatsFormatter::formatPerGameAverage($this->seasonDefenseTotalFieldGoalsMade, $this->seasonDefenseGamesPlayed);
        $this->seasonDefenseFieldGoalsAttemptedPerGame = StatsFormatter::formatPerGameAverage($this->seasonDefenseTotalFieldGoalsAttempted, $this->seasonDefenseGamesPlayed);
        $this->seasonDefenseFreeThrowsMadePerGame = StatsFormatter::formatPerGameAverage($this->seasonDefenseTotalFreeThrowsMade, $this->seasonDefenseGamesPlayed);
        $this->seasonDefenseFreeThrowsAttemptedPerGame = StatsFormatter::formatPerGameAverage($this->seasonDefenseTotalFreeThrowsAttempted, $this->seasonDefenseGamesPlayed);
        $this->seasonDefenseThreePointersMadePerGame = StatsFormatter::formatPerGameAverage($this->seasonDefenseTotalThreePointersMade, $this->seasonDefenseGamesPlayed);
        $this->seasonDefenseThreePointersAttemptedPerGame = StatsFormatter::formatPerGameAverage($this->seasonDefenseTotalThreePointersAttempted, $this->seasonDefenseGamesPlayed);
        $this->seasonDefenseOffensiveReboundsPerGame = StatsFormatter::formatPerGameAverage($this->seasonDefenseTotalOffensiveRebounds, $this->seasonDefenseGamesPlayed);
        $this->seasonDefenseDefensiveReboundsPerGame = StatsFormatter::formatPerGameAverage($this->seasonDefenseTotalDefensiveRebounds, $this->seasonDefenseGamesPlayed);
        $this->seasonDefenseTotalReboundsPerGame = StatsFormatter::formatPerGameAverage($this->seasonDefenseTotalRebounds, $this->seasonDefenseGamesPlayed);
        $this->seasonDefenseAssistsPerGame = StatsFormatter::formatPerGameAverage($this->seasonDefenseTotalAssists, $this->seasonDefenseGamesPlayed);
        $this->seasonDefenseStealsPerGame = StatsFormatter::formatPerGameAverage($this->seasonDefenseTotalSteals, $this->seasonDefenseGamesPlayed);
        $this->seasonDefenseTurnoversPerGame = StatsFormatter::formatPerGameAverage($this->seasonDefenseTotalTurnovers, $this->seasonDefenseGamesPlayed);
        $this->seasonDefenseBlocksPerGame = StatsFormatter::formatPerGameAverage($this->seasonDefenseTotalBlocks, $this->seasonDefenseGamesPlayed);
        $this->seasonDefensePersonalFoulsPerGame = StatsFormatter::formatPerGameAverage($this->seasonDefenseTotalPersonalFouls, $this->seasonDefenseGamesPlayed);
        $this->seasonDefensePointsPerGame = StatsFormatter::formatPerGameAverage($this->seasonDefenseTotalPoints, $this->seasonDefenseGamesPlayed);

        $this->seasonDefenseFieldGoalPercentage = StatsFormatter::formatPercentage($this->seasonDefenseTotalFieldGoalsMade, $this->seasonDefenseTotalFieldGoalsAttempted);
        $this->seasonDefenseFreeThrowPercentage = StatsFormatter::formatPercentage($this->seasonDefenseTotalFreeThrowsMade, $this->seasonDefenseTotalFreeThrowsAttempted);
        $this->seasonDefenseThreePointPercentage = StatsFormatter::formatPercentage($this->seasonDefenseTotalThreePointersMade, $this->seasonDefenseTotalThreePointersAttempted);
    }
}