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
    public $historicalYear;

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
    public $currentSeasonSalary;
    public $salaryJSB;

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

    public function __construct()
    {
    }

    public static function withPlayerID($db, int $playerID)
    {
        $instance = new self();
        $instance->loadByID($db, $playerID);
        return $instance;
    }

    public static function withPlrRow($db, array $plrRow)
    {
        $instance = new self();
        $instance->fill($db, $plrRow);
        return $instance;
    }

    public static function withHistoricalPlrRow($db, array $plrRow)
    {
        $instance = new self();
        $instance->fillHistorical($db, $plrRow);
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
        $this->fill($db, $plrRow);
    }

    protected function fill($db, array $plrRow)
    {
        $this->db = $db;

        $this->playerID = $plrRow['pid'];
        $this->ordinal = $plrRow['ordinal'];
        $this->name = $plrRow['name'];
        $this->nickname = $plrRow['nickname'];
        $this->age = $plrRow['age'];

        $this->teamID = $plrRow['tid'];
        $this->teamName = $plrRow['teamname'];
        $this->position = $plrRow['pos'];
        
        $this->ratingFieldGoalAttempts = $plrRow['r_fga'];
        $this->ratingFieldGoalPercentage = $plrRow['r_fgp'];
        $this->ratingFreeThrowAttempts = $plrRow['r_fta'];
        $this->ratingFreeThrowPercentage = $plrRow['r_ftp'];
        $this->ratingThreePointAttempts = $plrRow['r_tga'];
        $this->ratingThreePointPercentage = $plrRow['r_tgp'];
        $this->ratingOffensiveRebounds = $plrRow['r_orb'];
        $this->ratingDefensiveRebounds = $plrRow['r_drb'];
        $this->ratingAssists = $plrRow['r_ast'];
        $this->ratingSteals = $plrRow['r_stl'];
        $this->ratingTurnovers = $plrRow['r_to'];
        $this->ratingBlocks = $plrRow['r_blk'];
        $this->ratingFouls = $plrRow['r_foul'];
        $this->ratingOutsideOffense = $plrRow['oo'];
        $this->ratingOutsideDefense = $plrRow['od'];
        $this->ratingDriveOffense = $plrRow['do'];
        $this->ratingDriveDefense = $plrRow['dd'];
        $this->ratingPostOffense = $plrRow['po'];
        $this->ratingPostDefense = $plrRow['pd'];
        $this->ratingTransitionOffense = $plrRow['to'];
        $this->ratingTransitionDefense = $plrRow['td'];
        $this->ratingClutch = $plrRow['Clutch'];
        $this->ratingConsistency = $plrRow['Consistency'];
        $this->ratingTalent = $plrRow['talent'];
        $this->ratingSkill = $plrRow['skill'];
        $this->ratingIntangibles = $plrRow['intangibles'];

        $this->freeAgencyLoyalty = $plrRow['loyalty'];
        $this->freeAgencyPlayingTime = $plrRow['playingTime'];
        $this->freeAgencyPlayForWinner = $plrRow['winner'];
        $this->freeAgencyTradition = $plrRow['tradition'];
        $this->freeAgencySecurity = $plrRow['security'];

        $this->yearsOfExperience = $plrRow['exp'];
        $this->birdYears = $plrRow['bird'];
        $this->contractCurrentYear = $plrRow['cy'];
        $this->contractTotalYears = $plrRow['cyt'];
        $this->contractYear1Salary = $plrRow['cy1'];
        $this->contractYear2Salary = $plrRow['cy2'];
        $this->contractYear3Salary = $plrRow['cy3'];
        $this->contractYear4Salary = $plrRow['cy4'];
        $this->contractYear5Salary = $plrRow['cy5'];
        $this->contractYear6Salary = $plrRow['cy6'];
        $this->currentSeasonSalary = $this->getCurrentSeasonSalary();
    
        $this->draftYear = $plrRow['draftyear'];
        $this->draftRound = $plrRow['draftround'];
        $this->draftPickNumber = $plrRow['draftpickno'];
        $this->draftTeamOriginalName = $plrRow['draftedby'];
        $this->draftTeamCurrentName = $plrRow['draftedbycurrentname'];
        $this->collegeName = $plrRow['college'];
    
        $this->daysRemainingForInjury = $plrRow['injured'];
    
        $this->heightFeet = $plrRow['htft'];
        $this->heightInches = $plrRow['htin'];
        $this->weightPounds = $plrRow['wt'];
    
        $this->isRetired = $plrRow['retired'];
    
        $this->timeDroppedOnWaivers = $plrRow['droptime'];
    }

    protected function fillHistorical($db, array $plrRow)
    {
        $this->db = $db;

        $this->playerID = $plrRow['pid'];
        $this->historicalYear = $plrRow['year'];
        $this->name = $plrRow['name'];

        $this->teamName = $plrRow['team'];
        $this->teamID = $plrRow['teamid'];
        
        $this->ratingFieldGoalAttempts = $plrRow['r_2ga'];
        $this->ratingFieldGoalPercentage = $plrRow['r_2gp'];
        $this->ratingFreeThrowAttempts = $plrRow['r_fta'];
        $this->ratingFreeThrowPercentage = $plrRow['r_ftp'];
        $this->ratingThreePointAttempts = $plrRow['r_3ga'];
        $this->ratingThreePointPercentage = $plrRow['r_3gp'];
        $this->ratingOffensiveRebounds = $plrRow['r_orb'];
        $this->ratingDefensiveRebounds = $plrRow['r_drb'];
        $this->ratingAssists = $plrRow['r_ast'];
        $this->ratingSteals = $plrRow['r_stl'];
        $this->ratingBlocks = $plrRow['r_blk'];
        $this->ratingTurnovers = $plrRow['r_tvr'];

        $this->ratingOutsideOffense = $plrRow['r_oo'];
        $this->ratingOutsideDefense = $plrRow['r_od'];
        $this->ratingDriveOffense = $plrRow['r_do'];
        $this->ratingDriveDefense = $plrRow['r_dd'];
        $this->ratingPostOffense = $plrRow['r_po'];
        $this->ratingPostDefense = $plrRow['r_pd'];
        $this->ratingTransitionOffense = $plrRow['r_to'];
        $this->ratingTransitionDefense = $plrRow['r_td'];

        $this->salaryJSB = $plrRow['salary'];
    }

    public function getCurrentSeasonSalary()
    {
        $currentSeasonSalary = $this->{"contractYear" . $this->contractCurrentYear . "Salary"};
        return $currentSeasonSalary;
    }

    public function getFreeAgencyDemands()
    {
        $query = "SELECT *
            FROM ibl_demands
            WHERE name='$this->name'";
        $result = $this->db->sql_query($query);
        return $result;
    }

    public function getNextSeasonSalary()
    {
        $contractNextYear = $this->contractCurrentYear + 1;
        $nextSeasonSalary = $this->{"contractYear" . $contractNextYear . "Salary"};
        return $nextSeasonSalary;
    }

    public function getLongBuyoutArray()
    {
        $totalRemainingSalary = $this->getTotalRemainingSalary();
        $oneSixthOfTotalRemainingSalary = round($totalRemainingSalary / 6);
        $longBuyoutArray[1] = $longBuyoutArray[2] = $longBuyoutArray[3] = $longBuyoutArray[4] = $longBuyoutArray[5] = $longBuyoutArray[6] = $oneSixthOfTotalRemainingSalary;
        return $longBuyoutArray;
    }

    public function getShortBuyoutArray()
    {
        $totalRemainingSalary = $this->getTotalRemainingSalary();
        $oneHalfOfTotalRemainingSalary = round($totalRemainingSalary / 2);
        $shortBuyoutArray[1] = $shortBuyoutArray[2] = $oneHalfOfTotalRemainingSalary;
        return $shortBuyoutArray;
    }

    public function getRemainingContractArray()
    {
        $contractCurrentYear = ($this->contractCurrentYear != 0) ? $this->contractCurrentYear : 1;
        $contractTotalYears = ($this->contractTotalYears != 0) ? $this->contractTotalYears : 1;

        $remainingContractYear = 1;
        for ($i = $contractCurrentYear; $i <= $contractTotalYears; $i++) {
            if ($this->{"contractYear" . $i . "Salary"} != 0) {
                $contractArray[$remainingContractYear] = $this->{"contractYear" . $i . "Salary"};
            }
            $remainingContractYear++;
        }

        $contractArray[1] = ($contractArray) ? $contractArray[1] : 0;
        return $contractArray;
    }

    public function getTotalRemainingSalary()
    {
        $contractArray = $this->getRemainingContractArray();
        return array_sum($contractArray);
    }

    public function canRenegotiateContract()
    {
        if (
            (($this->contractCurrentYear == 0 OR $this->contractCurrentYear == 1) AND $this->contractYear2Salary == 0)
            OR $this->contractCurrentYear == 1 AND $this->contractYear2Salary == 0
            OR $this->contractCurrentYear == 2 AND $this->contractYear3Salary == 0
            OR $this->contractCurrentYear == 3 AND $this->contractYear4Salary == 0
            OR $this->contractCurrentYear == 4 AND $this->contractYear5Salary == 0
            OR $this->contractCurrentYear == 5 AND $this->contractYear6Salary == 0
            OR $this->contractCurrentYear == 6
        ) {
            return TRUE;
        }
        return FALSE;
    }

    public function canRookieOption($seasonPhase)
    {
        if ($seasonPhase == "Free Agency") {
            if (
                ($this->draftRound == 1 AND $this->yearsOfExperience == 2 AND $this->contractYear4Salary == 0)
                OR ($this->draftRound == 2 AND $this->yearsOfExperience == 1 AND $this->contractYear3Salary == 0)
            ) {
                return TRUE;
            }
        } elseif ($seasonPhase == "Preseason" or $seasonPhase == "HEAT") {
            if (
                ($this->draftRound == 1 AND $this->yearsOfExperience == 3 AND $this->contractYear4Salary == 0)
                OR ($this->draftRound == 2 AND $this->yearsOfExperience == 2 AND $this->contractYear3Salary == 0)
            ) {
                return TRUE;
            }
        } else {
            return FALSE;
        }
    }

    public function wasRookieOptioned()
    {
        if ((
            $this->yearsOfExperience == 4 
            AND $this->draftRound == 1
            AND $this->contractYear4Salary != 0
            AND 2 * $this->contractYear3Salary == $this->contractYear4Salary
        ) OR (
            $this->yearsOfExperience == 3
            AND $this->draftRound == 2
            AND $this->contractYear3Salary != 0
            AND 2 * $this->contractYear2Salary == $this->contractYear3Salary
        )) {
            return TRUE;
        }
        return FALSE;
    }
}