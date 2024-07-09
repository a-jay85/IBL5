<?php

class TeamStats
{
    protected $db;

    public $seasonOffenseGamesPlayed;
    public $seasonOffenseTotalMinutes;
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
    
    public $seasonOffenseMinutesPerGame;
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
    public $seasonDefenseTotalMinutes;
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
    
    public $seasonDefenseMinutesPerGame;
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

    public function __construct()
    {
    }

    public static function withTeamName($db, string $teamName)
    {
        $instance = new self();
        $instance->loadByTeamName($db, $teamName);
        return $instance;
    }

    protected function loadByTeamName($db, string $teamName)
    {
        $queryOffenseTotals = "SELECT *
            FROM ibl_team_offense_stats
            WHERE team = '$teamName'
            LIMIT 1;";
        $resulOffenseTotals = $db->sql_query($queryOffenseTotals);
        $offenseTotalsRow = $db->sql_fetch_assoc($resulOffenseTotals);
        $this->fillOffenseTotals($offenseTotalsRow);

        $queryDefenseTotals = "SELECT *
            FROM ibl_team_defense_stats
            WHERE team = '$teamName'
            LIMIT 1;";
        $resulDefenseTotals = $db->sql_query($queryDefenseTotals);
        $defenseTotalsRow = $db->sql_fetch_assoc($resulDefenseTotals);
        $this->fillDefenseTotals($defenseTotalsRow);
    }

    protected function fillOffenseTotals(array $offenseTotalsRow)
    {
        $this->seasonOffenseGamesPlayed = $offenseTotalsRow['games'];
        $this->seasonOffenseTotalMinutes = $offenseTotalsRow['minutes'];
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
        $this->seasonOffenseTotalPoints = 2 * $this->seasonOffenseTotalFieldGoalsMade + $this->seasonOffenseTotalFreeThrowsMade + $this->seasonOffenseTotalThreePointersMade;

        $this->seasonOffenseMinutesPerGame = ($this->seasonOffenseGamesPlayed) ? number_format(($this->seasonOffenseTotalMinutes / $this->seasonOffenseGamesPlayed), 1) : "0";
        $this->seasonOffenseFieldGoalsMadePerGame = ($this->seasonOffenseGamesPlayed) ? number_format(($this->seasonOffenseTotalFieldGoalsMade / $this->seasonOffenseGamesPlayed), 1) : "0";
        $this->seasonOffenseFieldGoalsAttemptedPerGame = ($this->seasonOffenseGamesPlayed) ? number_format(($this->seasonOffenseTotalFieldGoalsAttempted / $this->seasonOffenseGamesPlayed), 1) : "0";
        $this->seasonOffenseFreeThrowsMadePerGame = ($this->seasonOffenseGamesPlayed) ? number_format(($this->seasonOffenseTotalFreeThrowsMade / $this->seasonOffenseGamesPlayed), 1) : "0";
        $this->seasonOffenseFreeThrowsAttemptedPerGame = ($this->seasonOffenseGamesPlayed) ? number_format(($this->seasonOffenseTotalFreeThrowsAttempted / $this->seasonOffenseGamesPlayed), 1) : "0";
        $this->seasonOffenseThreePointersMadePerGame = ($this->seasonOffenseGamesPlayed) ? number_format(($this->seasonOffenseTotalThreePointersMade / $this->seasonOffenseGamesPlayed), 1) : "0";
        $this->seasonOffenseThreePointersAttemptedPerGame = ($this->seasonOffenseGamesPlayed) ? number_format(($this->seasonOffenseTotalThreePointersAttempted / $this->seasonOffenseGamesPlayed), 1) : "0";
        $this->seasonOffenseOffensiveReboundsPerGame = ($this->seasonOffenseGamesPlayed) ? number_format(($this->seasonOffenseTotalOffensiveRebounds / $this->seasonOffenseGamesPlayed), 1) : "0";
        $this->seasonOffenseDefensiveReboundsPerGame = ($this->seasonOffenseGamesPlayed) ? number_format(($this->seasonOffenseTotalDefensiveRebounds / $this->seasonOffenseGamesPlayed), 1) : "0";
        $this->seasonOffenseTotalReboundsPerGame = ($this->seasonOffenseGamesPlayed) ? number_format(($this->seasonOffenseTotalRebounds / $this->seasonOffenseGamesPlayed), 1) : "0";
        $this->seasonOffenseAssistsPerGame = ($this->seasonOffenseGamesPlayed) ? number_format(($this->seasonOffenseTotalAssists / $this->seasonOffenseGamesPlayed), 1) : "0";
        $this->seasonOffenseStealsPerGame = ($this->seasonOffenseGamesPlayed) ? number_format(($this->seasonOffenseTotalSteals / $this->seasonOffenseGamesPlayed), 1) : "0";
        $this->seasonOffenseTurnoversPerGame = ($this->seasonOffenseGamesPlayed) ? number_format(($this->seasonOffenseTotalTurnovers / $this->seasonOffenseGamesPlayed), 1) : "0";
        $this->seasonOffenseBlocksPerGame = ($this->seasonOffenseGamesPlayed) ? number_format(($this->seasonOffenseTotalBlocks / $this->seasonOffenseGamesPlayed), 1) : "0";
        $this->seasonOffensePersonalFoulsPerGame = ($this->seasonOffenseGamesPlayed) ? number_format(($this->seasonOffenseTotalPersonalFouls / $this->seasonOffenseGamesPlayed), 1) : "0";
        $this->seasonOffensePointsPerGame = ($this->seasonOffenseGamesPlayed) ? number_format(($this->seasonOffenseTotalPoints / $this->seasonOffenseGamesPlayed), 1) : "0";

        $this->seasonOffenseFieldGoalPercentage = ($this->seasonOffenseTotalFieldGoalsAttempted) ? number_format(($this->seasonOffenseTotalFieldGoalsMade / $this->seasonOffenseTotalFieldGoalsAttempted), 3) : "0.000";
        $this->seasonOffenseFreeThrowPercentage = ($this->seasonOffenseTotalFreeThrowsAttempted) ? number_format(($this->seasonOffenseTotalFreeThrowsMade / $this->seasonOffenseTotalFreeThrowsAttempted), 3) : "0.000";
        $this->seasonOffenseThreePointPercentage = ($this->seasonOffenseTotalThreePointersAttempted) ? number_format(($this->seasonOffenseTotalThreePointersMade / $this->seasonOffenseTotalThreePointersAttempted), 3) : "0.000";
    }

    protected function fillDefenseTotals(array $defenseTotalsRow)
    {
        $this->seasonDefenseGamesPlayed = $defenseTotalsRow['games'];
        $this->seasonDefenseTotalMinutes = $defenseTotalsRow['minutes'];
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
        $this->seasonDefenseTotalPoints = 2 * $this->seasonDefenseTotalFieldGoalsMade + $this->seasonDefenseTotalFreeThrowsMade + $this->seasonDefenseTotalThreePointersMade;

        $this->seasonDefenseMinutesPerGame = ($this->seasonDefenseGamesPlayed) ? number_format(($this->seasonDefenseTotalMinutes / $this->seasonDefenseGamesPlayed), 1) : "0";
        $this->seasonDefenseFieldGoalsMadePerGame = ($this->seasonDefenseGamesPlayed) ? number_format(($this->seasonDefenseTotalFieldGoalsMade / $this->seasonDefenseGamesPlayed), 1) : "0";
        $this->seasonDefenseFieldGoalsAttemptedPerGame = ($this->seasonDefenseGamesPlayed) ? number_format(($this->seasonDefenseTotalFieldGoalsAttempted / $this->seasonDefenseGamesPlayed), 1) : "0";
        $this->seasonDefenseFreeThrowsMadePerGame = ($this->seasonDefenseGamesPlayed) ? number_format(($this->seasonDefenseTotalFreeThrowsMade / $this->seasonDefenseGamesPlayed), 1) : "0";
        $this->seasonDefenseFreeThrowsAttemptedPerGame = ($this->seasonDefenseGamesPlayed) ? number_format(($this->seasonDefenseTotalFreeThrowsAttempted / $this->seasonDefenseGamesPlayed), 1) : "0";
        $this->seasonDefenseThreePointersMadePerGame = ($this->seasonDefenseGamesPlayed) ? number_format(($this->seasonDefenseTotalThreePointersMade / $this->seasonDefenseGamesPlayed), 1) : "0";
        $this->seasonDefenseThreePointersAttemptedPerGame = ($this->seasonDefenseGamesPlayed) ? number_format(($this->seasonDefenseTotalThreePointersAttempted / $this->seasonDefenseGamesPlayed), 1) : "0";
        $this->seasonDefenseOffensiveReboundsPerGame = ($this->seasonDefenseGamesPlayed) ? number_format(($this->seasonDefenseTotalOffensiveRebounds / $this->seasonDefenseGamesPlayed), 1) : "0";
        $this->seasonDefenseDefensiveReboundsPerGame = ($this->seasonDefenseGamesPlayed) ? number_format(($this->seasonDefenseTotalDefensiveRebounds / $this->seasonDefenseGamesPlayed), 1) : "0";
        $this->seasonDefenseTotalReboundsPerGame = ($this->seasonDefenseGamesPlayed) ? number_format(($this->seasonDefenseTotalRebounds / $this->seasonDefenseGamesPlayed), 1) : "0";
        $this->seasonDefenseAssistsPerGame = ($this->seasonDefenseGamesPlayed) ? number_format(($this->seasonDefenseTotalAssists / $this->seasonDefenseGamesPlayed), 1) : "0";
        $this->seasonDefenseStealsPerGame = ($this->seasonDefenseGamesPlayed) ? number_format(($this->seasonDefenseTotalSteals / $this->seasonDefenseGamesPlayed), 1) : "0";
        $this->seasonDefenseTurnoversPerGame = ($this->seasonDefenseGamesPlayed) ? number_format(($this->seasonDefenseTotalTurnovers / $this->seasonDefenseGamesPlayed), 1) : "0";
        $this->seasonDefenseBlocksPerGame = ($this->seasonDefenseGamesPlayed) ? number_format(($this->seasonDefenseTotalBlocks / $this->seasonDefenseGamesPlayed), 1) : "0";
        $this->seasonDefensePersonalFoulsPerGame = ($this->seasonDefenseGamesPlayed) ? number_format(($this->seasonDefenseTotalPersonalFouls / $this->seasonDefenseGamesPlayed), 1) : "0";
        $this->seasonDefensePointsPerGame = ($this->seasonDefenseGamesPlayed) ? number_format(($this->seasonDefenseTotalPoints / $this->seasonDefenseGamesPlayed), 1) : "0";

        $this->seasonDefenseFieldGoalPercentage = ($this->seasonDefenseTotalFieldGoalsAttempted) ? number_format(($this->seasonDefenseTotalFieldGoalsMade / $this->seasonDefenseTotalFieldGoalsAttempted), 3) : "0.000";
        $this->seasonDefenseFreeThrowPercentage = ($this->seasonDefenseTotalFreeThrowsAttempted) ? number_format(($this->seasonDefenseTotalFreeThrowsMade / $this->seasonDefenseTotalFreeThrowsAttempted), 3) : "0.000";
        $this->seasonDefenseThreePointPercentage = ($this->seasonDefenseTotalThreePointersAttempted) ? number_format(($this->seasonDefenseTotalThreePointersMade / $this->seasonDefenseTotalThreePointersAttempted), 3) : "0.000";
    }
}