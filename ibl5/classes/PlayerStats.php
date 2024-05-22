<?php

class PlayerStats
{
    protected $db;

    public $playerID;
    public $plr;

    public $name;
    public $position;
    
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

    public function __construct()
    {
    }

    public static function withPlayerID($db, int $playerID)
    {
        $instance = new self();
        $instance->loadByID($db, $playerID);
        return $instance;
    }

    public static function withPlayerObject($db, Player $player)
    {
        $instance = new self();
        $instance->loadByID($db, $player->playerID);
        return $instance;
    }

    public static function withPlrRow($db, array $plrRow)
    {
        $instance = new self();
        $instance->fill($plrRow);
        return $instance;
    }

    public static function withHistoricalPlrRow($db, array $plrRow)
    {
        $instance = new self();
        $instance->fillHistorical($plrRow);
        return $instance;
    }

    protected function loadByID($db, int $playerID)
    {
        $query = "SELECT *
            FROM ibl_plr
            WHERE pid = $playerID
            LIMIT 1;";
        $result = $db->sql_query($query);
        $plrRow = $db->sql_fetch_assoc($result);
        $this->fill($plrRow);
    }

    protected function fill(array $plrRow)
    {
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
        $this->seasonPoints = 2 * $this->seasonFieldGoalsMade + $this->seasonFreeThrowsMade + $this->seasonThreePointersMade;

        @$this->seasonMinutesPerGame = number_format(($this->seasonMinutes / $this->seasonGamesPlayed), 1);
        @$this->seasonFieldGoalsMadePerGame = number_format(($this->seasonFieldGoalsMade / $this->seasonGamesPlayed), 2);
        @$this->seasonFieldGoalsAttemptedPerGame = number_format(($this->seasonFieldGoalsAttempted / $this->seasonGamesPlayed), 2);
        @$this->seasonFreeThrowsMadePerGame = number_format(($this->seasonFreeThrowsMade / $this->seasonGamesPlayed), 2);
        @$this->seasonFreeThrowsAttemptedPerGame = number_format(($this->seasonFreeThrowsAttempted / $this->seasonGamesPlayed), 2);
        @$this->seasonThreePointersMadePerGame = number_format(($this->seasonThreePointersMade / $this->seasonGamesPlayed), 2);
        @$this->seasonThreePointersAttemptedPerGame = number_format(($this->seasonThreePointersAttempted / $this->seasonGamesPlayed), 2);
        @$this->seasonOffensiveReboundsPerGame = number_format(($this->seasonOffensiveRebounds / $this->seasonGamesPlayed), 1);
        @$this->seasonDefensiveReboundsPerGame = number_format(($this->seasonDefensiveRebounds / $this->seasonGamesPlayed), 1);
        @$this->seasonTotalReboundsPerGame = number_format(($this->seasonOffensiveReboundsPerGame + $this->seasonDefensiveReboundsPerGame), 1);
        @$this->seasonAssistsPerGame = number_format(($this->seasonAssists / $this->seasonGamesPlayed), 1);
        @$this->seasonStealsPerGame = number_format(($this->seasonSteals / $this->seasonGamesPlayed), 1);
        @$this->seasonTurnoversPerGame = number_format(($this->seasonTurnovers / $this->seasonGamesPlayed), 1);
        @$this->seasonBlocksPerGame = number_format(($this->seasonBlocks / $this->seasonGamesPlayed), 1);
        @$this->seasonPersonalFoulsPerGame = number_format(($this->seasonPersonalFouls / $this->seasonGamesPlayed), 1);
        @$this->seasonPointsPerGame = number_format(($this->seasonPoints / $this->seasonGamesPlayed), 1);

        @$this->seasonFieldGoalPercentage = number_format(($this->seasonFieldGoalsMade / $this->seasonFieldGoalsAttempted), 3);
        @$this->seasonFreeThrowPercentage = number_format(($this->seasonFreeThrowsMade / $this->seasonFreeThrowsAttempted), 3);
        @$this->seasonThreePointPercentage = number_format(($this->seasonThreePointersMade / $this->seasonThreePointersAttempted), 3);
        
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
    }

    protected function fillHistorical(array $plrRow)
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
        $this->seasonPoints = 2 * $this->seasonFieldGoalsMade + $this->seasonFreeThrowsMade + $this->seasonThreePointersMade;

        @$this->seasonMinutesPerGame = number_format(($this->seasonMinutes / $this->seasonGamesPlayed), 1);
        @$this->seasonFieldGoalsMadePerGame = number_format(($this->seasonFieldGoalsMade / $this->seasonGamesPlayed), 2);
        @$this->seasonFieldGoalsAttemptedPerGame = number_format(($this->seasonFieldGoalsAttempted / $this->seasonGamesPlayed), 2);
        @$this->seasonFreeThrowsMadePerGame = number_format(($this->seasonFreeThrowsMade / $this->seasonGamesPlayed), 2);
        @$this->seasonFreeThrowsAttemptedPerGame = number_format(($this->seasonFreeThrowsAttempted / $this->seasonGamesPlayed), 2);
        @$this->seasonThreePointersMadePerGame = number_format(($this->seasonThreePointersMade / $this->seasonGamesPlayed), 2);
        @$this->seasonThreePointersAttemptedPerGame = number_format(($this->seasonThreePointersAttempted / $this->seasonGamesPlayed), 2);
        @$this->seasonOffensiveReboundsPerGame = number_format(($this->seasonOffensiveRebounds / $this->seasonGamesPlayed), 1);
        @$this->seasonDefensiveReboundsPerGame = number_format(($this->seasonDefensiveRebounds / $this->seasonGamesPlayed), 1);
        @$this->seasonTotalReboundsPerGame = number_format(($this->seasonOffensiveReboundsPerGame + $this->seasonDefensiveReboundsPerGame), 1);
        @$this->seasonAssistsPerGame = number_format(($this->seasonAssists / $this->seasonGamesPlayed), 1);
        @$this->seasonStealsPerGame = number_format(($this->seasonSteals / $this->seasonGamesPlayed), 1);
        @$this->seasonTurnoversPerGame = number_format(($this->seasonTurnovers / $this->seasonGamesPlayed), 1);
        @$this->seasonBlocksPerGame = number_format(($this->seasonBlocks / $this->seasonGamesPlayed), 1);
        @$this->seasonPersonalFoulsPerGame = number_format(($this->seasonPersonalFouls / $this->seasonGamesPlayed), 1);
        @$this->seasonPointsPerGame = number_format(($this->seasonPoints / $this->seasonGamesPlayed), 1);

        @$this->seasonFieldGoalPercentage = number_format(($this->seasonFieldGoalsMade / $this->seasonFieldGoalsAttempted), 3);
        @$this->seasonFreeThrowPercentage = number_format(($this->seasonFreeThrowsMade / $this->seasonFreeThrowsAttempted), 3);
        @$this->seasonThreePointPercentage = number_format(($this->seasonThreePointersMade / $this->seasonThreePointersAttempted), 3);
    }

    protected function fillBoxscoreStats(string $playerInfoLine)
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