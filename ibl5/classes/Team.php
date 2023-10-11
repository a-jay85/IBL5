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

    public function getActiveRosterArrayAlphabetically()
    {
        $query = "SELECT * FROM ibl_plr WHERE tid = '$this->teamID' AND retired = 0 ORDER BY name ASC";
        $result = $this->db->sql_query($query);
        $array = array();
        foreach ($result as $plrRow) {
            $playerID = $plrRow['pid'];
            $array[$playerID] = Player::withPlayerID($this->db, $playerID);
        }

        return $array;
    }
}