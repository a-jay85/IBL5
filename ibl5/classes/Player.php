<?php

class Player
{
    protected $db;
    public $playerID;
    public $plr;

    public $ordinal;
    public $name;
    public $nickname;
    public $age;

    public $teamID;
    public $teamName;
    public $position;

    public $ratingFieldGoalAttempts;
    public $ratingFieldGoalPercentage;
    public $ratingFreeThrowAttempts;
    public $ratingFreeThrowPercentage;
    public $ratingThreePointAttempts;
    public $ratingThreePointPercentage;
    public $ratingOffensiveRebounds;
    public $ratingDefensiveRebounds;
    public $ratingAssists;
    public $ratingSteals;
    public $ratingTurnovers;
    public $ratingBlocks;
    public $ratingFouls;
    public $ratingOutsideOffense;
    public $ratingOutsideDefense;
    public $ratingDriveOffense;
    public $ratingDriveDefense;
    public $ratingPostOffense;
    public $ratingPostDefense;
    public $ratingTransitionOffense;
    public $ratingTransitionDefense;
    public $ratingClutch;
    public $ratingConsistency;
    public $ratingTalent;
    public $ratingSkill;
    public $ratingIntangibles;

    public $freeAgencyLoyalty;
    public $freeAgencyPlayingTime;
    public $freeAgencyPlayForWinner;
    public $freeAgencyTradition;
    public $freeAgencySecurity;

    public $yearsOfExperience;
    public $birdYears;
    public $contractCurrentYear;
    public $contractTotalYears;
    public $contractYear1Salary;
    public $contractYear2Salary;
    public $contractYear3Salary;
    public $contractYear4Salary;
    public $contractYear5Salary;
    public $contractYear6Salary;

    public $draftYear;
    public $draftRound;
    public $draftPickNumber;
    public $draftTeamOriginalName;
    public $draftTeamCurrentName;
    public $collegeName;

    public $daysRemainingForInjury;

    public $heightFeet;
    public $heightInches;
    public $weightPounds;

    public $isRetired;

    public $timeDroppedOnWaivers;

    public function __construct($db, int $playerID)
    {
        $this->db = $db;
        $this->playerID = $playerID;

        $query = "SELECT * FROM ibl_plr WHERE pid = $playerID LIMIT 1;";
        $result = $db->sql_query($query);
        $plr = $db->sql_fetch_assoc($result);
        $this->plr = $plr;

        $this->ordinal = $plr['ordinal'];
        $this->name = $plr['name'];
        $this->nickname = $plr['nickname'];
        $this->age = $plr['age'];

        $this->teamID = $plr['tid'];
        $this->teamName = $plr['teamname'];
        $this->position = $plr['pos'];
        
        $this->ratingFieldGoalAttempts = $plr['r_fga'];
        $this->ratingFieldGoalPercentage = $plr['r_fgp'];
        $this->ratingFreeThrowAttempts = $plr['r_fta'];
        $this->ratingFreeThrowPercentage = $plr['r_ftp'];
        $this->ratingThreePointAttempts = $plr['r_tga'];
        $this->ratingThreePointPercentage = $plr['r_tgp'];
        $this->ratingOffensiveRebounds = $plr['r_orb'];
        $this->ratingDefensiveRebounds = $plr['r_drb'];
        $this->ratingAssists = $plr['r_ast'];
        $this->ratingSteals = $plr['r_stl'];
        $this->ratingTurnovers = $plr['r_to'];
        $this->ratingBlocks = $plr['r_blk'];
        $this->ratingFouls = $plr['r_foul'];
        $this->ratingOutsideOffense = $plr['oo'];
        $this->ratingOutsideDefense = $plr['od'];
        $this->ratingDriveOffense = $plr['do'];
        $this->ratingDriveDefense = $plr['dd'];
        $this->ratingPostOffense = $plr['po'];
        $this->ratingPostDefense = $plr['pd'];
        $this->ratingTransitionOffense = $plr['to'];
        $this->ratingTransitionDefense = $plr['td'];
        $this->ratingClutch = $plr['Clutch'];
        $this->ratingConsistency = $plr['Consistency'];
        $this->ratingTalent = $plr['talent'];
        $this->ratingSkill = $plr['skill'];
        $this->ratingIntangibles = $plr['intangibles'];

        $this->freeAgencyLoyalty = $plr['loyalty'];
        $this->freeAgencyPlayingTime = $plr['playingTime'];
        $this->freeAgencyPlayForWinner = $plr['winner'];
        $this->freeAgencyTradition = $plr['tradition'];
        $this->freeAgencySecurity = $plr['security'];

        $this->yearsOfExperience = $plr['exp'];
        $this->birdYears = $plr['bird'];
        $this->contractCurrentYear = $plr['cy'];
        $this->contractTotalYears = $plr['cyt'];
        $this->contractYear1Salary = $plr['cy1'];
        $this->contractYear2Salary = $plr['cy2'];
        $this->contractYear3Salary = $plr['cy3'];
        $this->contractYear4Salary = $plr['cy4'];
        $this->contractYear5Salary = $plr['cy5'];
        $this->contractYear6Salary = $plr['cy6'];
    
        $this->draftYear = $plr['draftyear'];
        $this->draftRound = $plr['draftround'];
        $this->draftPickNumber = $plr['draftpickno'];
        $this->draftTeamOriginalName = $plr['draftedby'];
        $this->draftTeamCurrentName = $plr['draftedbycurrentname'];
        $this->collegeName = $plr['college'];
    
        $this->daysRemainingForInjury = $plr['injured'];
    
        $this->heightFeet = $plr['htft'];
        $this->heightInches = $plr['htin'];
        $this->weightPounds = $plr['wt'];
    
        $this->isRetired = $plr['retired'];
    
        $this->timeDroppedOnWaivers = $plr['droptime'];
    }
}