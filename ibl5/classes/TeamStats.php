<?php

declare(strict_types=1);

use BasketballStats\StatsFormatter;
use TeamOffDefStats\TeamOffDefStatsRepository;

/**
 * Team statistics container for offense and defense season stats
 *
 * Loads team offense/defense totals from the database and computes
 * per-game averages and shooting percentages using StatsFormatter.
 *
 * @phpstan-import-type TeamOffenseStatsRow from \TeamOffDefStats\Contracts\TeamOffDefStatsRepositoryInterface
 * @phpstan-import-type TeamDefenseStatsRow from \TeamOffDefStats\Contracts\TeamOffDefStatsRepositoryInterface
 */
class TeamStats
{
    protected TeamOffDefStatsRepository $repository;

    // Offense totals (from database, int columns with native types)
    public int $seasonOffenseGamesPlayed = 0;
    public int $seasonOffenseTotalFieldGoalsMade = 0;
    public int $seasonOffenseTotalFieldGoalsAttempted = 0;
    public int $seasonOffenseTotalFreeThrowsMade = 0;
    public int $seasonOffenseTotalFreeThrowsAttempted = 0;
    public int $seasonOffenseTotalThreePointersMade = 0;
    public int $seasonOffenseTotalThreePointersAttempted = 0;
    public int $seasonOffenseTotalOffensiveRebounds = 0;
    public int $seasonOffenseTotalDefensiveRebounds = 0;
    public int $seasonOffenseTotalRebounds = 0;
    public int $seasonOffenseTotalAssists = 0;
    public int $seasonOffenseTotalSteals = 0;
    public int $seasonOffenseTotalTurnovers = 0;
    public int $seasonOffenseTotalBlocks = 0;
    public int $seasonOffenseTotalPersonalFouls = 0;
    public int $seasonOffenseTotalPoints = 0;

    // Offense per-game averages (formatted strings from StatsFormatter)
    public string $seasonOffenseFieldGoalsMadePerGame = '0.0';
    public string $seasonOffenseFieldGoalsAttemptedPerGame = '0.0';
    public string $seasonOffenseFreeThrowsMadePerGame = '0.0';
    public string $seasonOffenseFreeThrowsAttemptedPerGame = '0.0';
    public string $seasonOffenseThreePointersMadePerGame = '0.0';
    public string $seasonOffenseThreePointersAttemptedPerGame = '0.0';
    public string $seasonOffenseOffensiveReboundsPerGame = '0.0';
    public string $seasonOffenseDefensiveReboundsPerGame = '0.0';
    public string $seasonOffenseTotalReboundsPerGame = '0.0';
    public string $seasonOffenseAssistsPerGame = '0.0';
    public string $seasonOffenseStealsPerGame = '0.0';
    public string $seasonOffenseTurnoversPerGame = '0.0';
    public string $seasonOffenseBlocksPerGame = '0.0';
    public string $seasonOffensePersonalFoulsPerGame = '0.0';
    public string $seasonOffensePointsPerGame = '0.0';

    // Offense shooting percentages (formatted strings from StatsFormatter)
    public string $seasonOffenseFieldGoalPercentage = '0.000';
    public string $seasonOffenseFreeThrowPercentage = '0.000';
    public string $seasonOffenseThreePointPercentage = '0.000';

    // Defense totals (from database, int columns with native types)
    public int $seasonDefenseGamesPlayed = 0;
    public int $seasonDefenseTotalFieldGoalsMade = 0;
    public int $seasonDefenseTotalFieldGoalsAttempted = 0;
    public int $seasonDefenseTotalFreeThrowsMade = 0;
    public int $seasonDefenseTotalFreeThrowsAttempted = 0;
    public int $seasonDefenseTotalThreePointersMade = 0;
    public int $seasonDefenseTotalThreePointersAttempted = 0;
    public int $seasonDefenseTotalOffensiveRebounds = 0;
    public int $seasonDefenseTotalDefensiveRebounds = 0;
    public int $seasonDefenseTotalRebounds = 0;
    public int $seasonDefenseTotalAssists = 0;
    public int $seasonDefenseTotalSteals = 0;
    public int $seasonDefenseTotalTurnovers = 0;
    public int $seasonDefenseTotalBlocks = 0;
    public int $seasonDefenseTotalPersonalFouls = 0;
    public int $seasonDefenseTotalPoints = 0;

    // Defense per-game averages (formatted strings from StatsFormatter)
    public string $seasonDefenseFieldGoalsMadePerGame = '0.0';
    public string $seasonDefenseFieldGoalsAttemptedPerGame = '0.0';
    public string $seasonDefenseFreeThrowsMadePerGame = '0.0';
    public string $seasonDefenseFreeThrowsAttemptedPerGame = '0.0';
    public string $seasonDefenseThreePointersMadePerGame = '0.0';
    public string $seasonDefenseThreePointersAttemptedPerGame = '0.0';
    public string $seasonDefenseOffensiveReboundsPerGame = '0.0';
    public string $seasonDefenseDefensiveReboundsPerGame = '0.0';
    public string $seasonDefenseTotalReboundsPerGame = '0.0';
    public string $seasonDefenseAssistsPerGame = '0.0';
    public string $seasonDefenseStealsPerGame = '0.0';
    public string $seasonDefenseTurnoversPerGame = '0.0';
    public string $seasonDefenseBlocksPerGame = '0.0';
    public string $seasonDefensePersonalFoulsPerGame = '0.0';
    public string $seasonDefensePointsPerGame = '0.0';

    // Defense shooting percentages (formatted strings from StatsFormatter)
    public string $seasonDefenseFieldGoalPercentage = '0.000';
    public string $seasonDefenseFreeThrowPercentage = '0.000';
    public string $seasonDefenseThreePointPercentage = '0.000';

    public function __construct(TeamOffDefStatsRepository $repository)
    {
        $this->repository = $repository;
    }

    public static function withTeamName(object $db, string $teamName, int $seasonYear): self
    {
        $repository = new TeamOffDefStatsRepository($db);
        $instance = new self($repository);
        $instance->loadByTeamName($teamName, $seasonYear);
        return $instance;
    }

    protected function loadByTeamName(string $teamName, int $seasonYear): void
    {
        $bothStats = $this->repository->getTeamBothStats($teamName, $seasonYear);
        if ($bothStats !== null) {
            $this->fillOffenseTotals($bothStats['offense']);
            $this->fillDefenseTotals($bothStats['defense']);
        }
    }

    /**
     * @param TeamOffenseStatsRow $offenseTotalsRow
     */
    protected function fillOffenseTotals(array $offenseTotalsRow): void
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

    /**
     * @param TeamDefenseStatsRow $defenseTotalsRow
     */
    protected function fillDefenseTotals(array $defenseTotalsRow): void
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
