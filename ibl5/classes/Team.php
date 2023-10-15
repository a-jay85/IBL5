<?php

class Team
{
    protected $db;

    public $teamID;

    public $city;
    public $name;
    public $color1;
    public $color2;

    public $ownerName;
    public $ownerEmail;
    public $discordID;

    public $hasUsedExtensionThisSim;
    public $hasUsedExtensionThisSeason;
    public $hasMLE;
    public $hasLLE;

    const SOFT_CAP_MAX = 5000;
    const HARD_CAP_MAX = 7000;
    const BUYOUT_PERCENTAGE_MAX = 0.40;

    public function __construct()
    {
    }

    public static function withTeamID($db, int $teamID)
    {
        $instance = new self();
        $instance->loadByID($db, $teamID);
        return $instance;
    }

    public static function withTeamRow($db, array $teamRow)
    {
        $instance = new self();
        $instance->fill($db, $teamRow);
        return $instance;
    }

    protected function loadByID($db, int $teamID)
    {
        $query = "SELECT * FROM ibl_team_info WHERE teamid = $teamID LIMIT 1;";
        $result = $db->sql_query($query);
        $teamRow = $db->sql_fetch_assoc($result);
        $this->fill($db, $teamRow);
    }

    protected function fill($db, array $teamRow)
    {
        $this->db = $db;

        $this->teamID = $teamRow['teamid'];

        $this->city = $teamRow['team_city'];
        $this->name = $teamRow['team_name'];
        $this->color1 = $teamRow['color1'];
        $this->color2 = $teamRow['color2'];
    
        $this->ownerName = $teamRow['owner_name'];
        $this->ownerEmail = $teamRow['owner_email'];
        $this->discordID = $teamRow['discordID'];
    
        $this->hasUsedExtensionThisSim = $teamRow['Used_Extension_This_Chunk'];
        $this->hasUsedExtensionThisSeason = $teamRow['Used_Extension_This_Season'];
        $this->hasMLE = $teamRow['HasMLE'];
        $this->hasLLE = $teamRow['HasLLE'];
    }

    public function getAlphabeticalActiveRosterResult()
    {
        $query = "SELECT * FROM ibl_plr WHERE tid = '$this->teamID' AND retired = 0 ORDER BY name ASC";
        $result = $this->db->sql_query($query);
        return $result;
    }

    public function getBuyoutsResult()
    {
        $query = "SELECT * FROM ibl_plr WHERE tid = '$this->teamID' AND name LIKE '%Buyout%' ORDER BY name ASC";
        $result = $this->db->sql_query($query);
        return $result;
    }

    public function getTotalCurrentSeasonSalariesFromPlrResult($result)
    {
        $totalCurrentSeasonSalaries = 0;

        $playerArray = $this->convertPlrResultIntoPlayerArray($result);
        foreach ($playerArray as $player) {
            $totalCurrentSeasonSalaries += $player->getCurrentSeasonSalary();
        }
        return $totalCurrentSeasonSalaries;
    }

    public function canAddContractWithoutGoingOverHardCap($currentSeasonContractValueToBeAdded)
    {
        $teamResult = $this->getAlphabeticalActiveRosterResult();
        $totalCurrentSeasonSalaries = $this->getTotalCurrentSeasonSalariesFromPlrResult($teamResult);
        $projectedTotalCurrentSeasonSalaries = $totalCurrentSeasonSalaries + $currentSeasonContractValueToBeAdded;

        if ($projectedTotalCurrentSeasonSalaries <= self::HARD_CAP_MAX) {
            return TRUE;
        }
        return FALSE;
    }

    public function canAddBuyoutWithoutExceedingBuyoutLimit($currentSeasonBuyoutValueToBeAdded)
    {
        $buyoutsResult = $this->getBuyoutsResult();
        $totalCurrentSeasonBuyouts = $this->getTotalCurrentSeasonSalariesFromPlrResult($buyoutsResult);
        $projectedTotalCurrentSeasonBuyouts = $totalCurrentSeasonBuyouts + $currentSeasonBuyoutValueToBeAdded;
        $buyoutLimit = self::HARD_CAP_MAX * self::BUYOUT_PERCENTAGE_MAX;

        if ($projectedTotalCurrentSeasonBuyouts <= $buyoutLimit) {
            return TRUE;
        }
        return FALSE;
    }

    public function convertPlrResultIntoPlayerArray($result)
    {
        $array = array();
        foreach ($result as $plrRow) {
            $playerID = $plrRow['pid'];
            $array[$playerID] = Player::withPlayerID($this->db, $playerID);
        }
        return $array;
    }
}