<?php

class PlayerStats
{
    protected $db;
    public $playerID;
    public $plr;

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

    protected function loadByID($db, int $playerID)
    {
        $query = "SELECT * FROM ibl_plr WHERE pid = $playerID LIMIT 1;";
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

        $this->seasonMinutesPerGame = $this->seasonMinutes / $this->seasonGamesPlayed;
        $this->seasonFieldGoalsMadePerGame = $this->seasonFieldGoalsMade / $this->seasonGamesPlayed;
        $this->seasonFieldGoalsAttemptedPerGame = $this->seasonFieldGoalsAttempted / $this->seasonGamesPlayed;
        $this->seasonFreeThrowsMadePerGame = $this->seasonFreeThrowsMade / $this->seasonGamesPlayed;
        $this->seasonFreeThrowsAttemptedPerGame = $this->seasonFreeThrowsAttempted / $this->seasonGamesPlayed;
        $this->seasonThreePointersMadePerGame = $this->seasonThreePointersMade / $this->seasonGamesPlayed;
        $this->seasonThreePointersAttemptedPerGame = $this->seasonThreePointersAttempted / $this->seasonGamesPlayed;
        $this->seasonOffensiveReboundsPerGame = $this->seasonOffensiveRebounds / $this->seasonGamesPlayed;
        $this->seasonDefensiveReboundsPerGame = $this->seasonDefensiveRebounds / $this->seasonGamesPlayed;
        $this->seasonTotalReboundsPerGame = $this->seasonOffensiveReboundsPerGame + $this->seasonDefensiveReboundsPerGame;
        $this->seasonAssistsPerGame = $this->seasonAssists / $this->seasonGamesPlayed;
        $this->seasonStealsPerGame = $this->seasonSteals / $this->seasonGamesPlayed;
        $this->seasonTurnoversPerGame = $this->seasonTurnovers / $this->seasonGamesPlayed;
        $this->seasonBlocksPerGame = $this->seasonBlocks / $this->seasonGamesPlayed;
        $this->seasonPersonalFoulsPerGame = $this->seasonPersonalFouls / $this->seasonGamesPlayed;
        $this->seasonPointsPerGame = $this->seasonPoints / $this->seasonGamesPlayed;

        $this->seasonFieldGoalPercentage = $this->seasonFieldGoalsMade / $this->seasonFieldGoalsAttempted;
        $this->seasonFreeThrowPercentage = $this->seasonFreeThrowsMade / $this->seasonFreeThrowsAttempted;
        $this->seasonThreePointPercentage = $this->seasonThreePointersMade / $this->seasonThreePointersAttempted;
        
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
}