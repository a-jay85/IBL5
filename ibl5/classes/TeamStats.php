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
        $queryOffenseTotals = "SELECT * FROM ibl_team_offense_stats WHERE team = '$teamName' LIMIT 1;";
        $resulOffenseTotals = $db->sql_query($queryOffenseTotals);
        $offenseTotalsRow = $db->sql_fetch_assoc($resulOffenseTotals);
        $this->fillOffenseTotals($offenseTotalsRow);

        $queryDefenseTotals = "SELECT * FROM ibl_team_defense_stats WHERE team = '$teamName' LIMIT 1;";
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

        @$this->seasonOffenseMinutesPerGame = number_format(($this->seasonOffenseTotalMinutes / $this->seasonOffenseGamesPlayed), 1);
        @$this->seasonOffenseFieldGoalsMadePerGame = number_format(($this->seasonOffenseTotalFieldGoalsMade / $this->seasonOffenseGamesPlayed) ,1);
        @$this->seasonOffenseFieldGoalsAttemptedPerGame = number_format(($this->seasonOffenseTotalFieldGoalsAttempted / $this->seasonOffenseGamesPlayed) ,1);
        @$this->seasonOffenseFreeThrowsMadePerGame = number_format(($this->seasonOffenseTotalFreeThrowsMade / $this->seasonOffenseGamesPlayed) ,1);
        @$this->seasonOffenseFreeThrowsAttemptedPerGame = number_format(($this->seasonOffenseTotalFreeThrowsAttempted / $this->seasonOffenseGamesPlayed) ,1);
        @$this->seasonOffenseThreePointersMadePerGame = number_format(($this->seasonOffenseTotalThreePointersMade / $this->seasonOffenseGamesPlayed) ,1);
        @$this->seasonOffenseThreePointersAttemptedPerGame = number_format(($this->seasonOffenseTotalThreePointersAttempted / $this->seasonOffenseGamesPlayed) ,1);
        @$this->seasonOffenseOffensiveReboundsPerGame = number_format(($this->seasonOffenseTotalOffensiveRebounds / $this->seasonOffenseGamesPlayed) ,1);
        @$this->seasonOffenseDefensiveReboundsPerGame = number_format(($this->seasonOffenseTotalDefensiveRebounds / $this->seasonOffenseGamesPlayed) ,1);
        @$this->seasonOffenseTotalReboundsPerGame = number_format(($this->seasonOffenseTotalRebounds / $this->seasonOffenseGamesPlayed) ,1);
        @$this->seasonOffenseAssistsPerGame = number_format(($this->seasonOffenseTotalAssists / $this->seasonOffenseGamesPlayed) ,1);
        @$this->seasonOffenseStealsPerGame = number_format(($this->seasonOffenseTotalSteals / $this->seasonOffenseGamesPlayed) ,1);
        @$this->seasonOffenseTurnoversPerGame = number_format(($this->seasonOffenseTotalTurnovers / $this->seasonOffenseGamesPlayed) ,1);
        @$this->seasonOffenseBlocksPerGame = number_format(($this->seasonOffenseTotalBlocks / $this->seasonOffenseGamesPlayed) ,1);
        @$this->seasonOffensePersonalFoulsPerGame = number_format(($this->seasonOffenseTotalPersonalFouls / $this->seasonOffenseGamesPlayed) ,1);
        @$this->seasonOffensePointsPerGame = number_format(($this->seasonOffenseTotalPoints / $this->seasonOffenseGamesPlayed) ,1);

        @$this->seasonOffenseFieldGoalPercentage = number_format(($this->seasonOffenseTotalFieldGoalsMade / $this->seasonOffenseTotalFieldGoalsAttempted), 3);
        @$this->seasonOffenseFreeThrowPercentage = number_format(($this->seasonOffenseTotalFreeThrowsMade / $this->seasonOffenseTotalFreeThrowsAttempted), 3);
        @$this->seasonOffenseThreePointPercentage = number_format(($this->seasonOffenseTotalThreePointersMade / $this->seasonOffenseTotalThreePointersAttempted), 3);
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

        @$this->seasonDefenseMinutesPerGame = number_format(($this->seasonDefenseTotalMinutes / $this->seasonDefenseGamesPlayed), 1);
        @$this->seasonDefenseFieldGoalsMadePerGame = number_format(($this->seasonDefenseTotalFieldGoalsMade / $this->seasonDefenseGamesPlayed) ,1);
        @$this->seasonDefenseFieldGoalsAttemptedPerGame = number_format(($this->seasonDefenseTotalFieldGoalsAttempted / $this->seasonDefenseGamesPlayed) ,1);
        @$this->seasonDefenseFreeThrowsMadePerGame = number_format(($this->seasonDefenseTotalFreeThrowsMade / $this->seasonDefenseGamesPlayed) ,1);
        @$this->seasonDefenseFreeThrowsAttemptedPerGame = number_format(($this->seasonDefenseTotalFreeThrowsAttempted / $this->seasonDefenseGamesPlayed) ,1);
        @$this->seasonDefenseThreePointersMadePerGame = number_format(($this->seasonDefenseTotalThreePointersMade / $this->seasonDefenseGamesPlayed) ,1);
        @$this->seasonDefenseThreePointersAttemptedPerGame = number_format(($this->seasonDefenseTotalThreePointersAttempted / $this->seasonDefenseGamesPlayed) ,1);
        @$this->seasonDefenseOffensiveReboundsPerGame = number_format(($this->seasonDefenseTotalOffensiveRebounds / $this->seasonDefenseGamesPlayed) ,1);
        @$this->seasonDefenseDefensiveReboundsPerGame = number_format(($this->seasonDefenseTotalDefensiveRebounds / $this->seasonDefenseGamesPlayed) ,1);
        @$this->seasonDefenseTotalReboundsPerGame = number_format(($this->seasonDefenseTotalRebounds / $this->seasonDefenseGamesPlayed) ,1);
        @$this->seasonDefenseAssistsPerGame = number_format(($this->seasonDefenseTotalAssists / $this->seasonDefenseGamesPlayed) ,1);
        @$this->seasonDefenseStealsPerGame = number_format(($this->seasonDefenseTotalSteals / $this->seasonDefenseGamesPlayed) ,1);
        @$this->seasonDefenseTurnoversPerGame = number_format(($this->seasonDefenseTotalTurnovers / $this->seasonDefenseGamesPlayed) ,1);
        @$this->seasonDefenseBlocksPerGame = number_format(($this->seasonDefenseTotalBlocks / $this->seasonDefenseGamesPlayed) ,1);
        @$this->seasonDefensePersonalFoulsPerGame = number_format(($this->seasonDefenseTotalPersonalFouls / $this->seasonDefenseGamesPlayed) ,1);
        @$this->seasonDefensePointsPerGame = number_format(($this->seasonDefenseTotalPoints / $this->seasonDefenseGamesPlayed) ,1);

        @$this->seasonDefenseFieldGoalPercentage = number_format(($this->seasonDefenseTotalFieldGoalsMade / $this->seasonDefenseTotalFieldGoalsAttempted), 3);
        @$this->seasonDefenseFreeThrowPercentage = number_format(($this->seasonDefenseTotalFreeThrowsMade / $this->seasonDefenseTotalFreeThrowsAttempted), 3);
        @$this->seasonDefenseThreePointPercentage = number_format(($this->seasonDefenseTotalThreePointersMade / $this->seasonDefenseTotalThreePointersAttempted), 3);
    }
}