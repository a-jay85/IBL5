<?php

class Shared
{
    protected $db;
    protected $commonRepository;

    public function __construct($db)
    {
        $this->db = $db;
        $this->commonRepository = new \Services\CommonRepository($db);
    }

    public function getNumberOfTitles($teamname, $titleName)
    {
        $queryNumberOfTitles = $this->db->sql_query("SELECT COUNT(name)
        	FROM ibl_team_awards
        	WHERE name = '$teamname'
        	  AND Award LIKE '%$titleName%';");

        return $this->db->sql_result($queryNumberOfTitles, 0, 'COUNT(name)');
    }

    public function getCurrentOwnerOfDraftPick($draftYear, $draftRound, $teamNameOfDraftPickOrigin)
    {
        $queryCurrentOwnerOfDraftPick = $this->db->sql_query("SELECT ownerofpick
            FROM ibl_draft_picks
            WHERE year = '$draftYear'
              AND round = '$draftRound'
              AND teampick = '$teamNameOfDraftPickOrigin'
            LIMIT 1;");

        return $this->db->sql_result($queryCurrentOwnerOfDraftPick, 0, 'ownerofpick');
    }
    
    /**
     * Gets player ID from player name
     * 
     * @deprecated Use CommonRepository::getPlayerIDFromPlayerName() instead
     * @param string $playerName Player name to look up
     * @return int|null Player ID or null if not found
     */
    public function getPlayerIDFromPlayerName($playerName)
    {
        return $this->commonRepository->getPlayerIDFromPlayerName($playerName);
    }

    /**
     * Gets team name from team ID
     * 
     * @deprecated Use CommonRepository::getTeamnameFromTeamID() instead
     * @param int $teamID Team ID to look up
     * @return string|null Team name or null if not found
     */
    public function getTeamnameFromTeamID($teamID)
    {
        return $this->commonRepository->getTeamnameFromTeamID($teamID);
    }

    /**
     * Gets team name from username
     * 
     * @deprecated Use CommonRepository::getTeamnameFromUsername() instead
     * @param string $username Username to look up
     * @return string|null Team name, "Free Agents" for empty username, or null if not found
     */
    public function getTeamnameFromUsername($username)
    {
        return $this->commonRepository->getTeamnameFromUsername($username);
    }

    /**
     * Gets team ID from team name
     * 
     * @deprecated Use CommonRepository::getTidFromTeamname() instead
     * @param string $teamname Team name to look up
     * @return int|null Team ID or null if not found
     */
    public function getTidFromTeamname($teamname)
    {
        return $this->commonRepository->getTidFromTeamname($teamname);
    }

    public function isFreeAgencyModuleActive()
    {
        $queryIsFreeAgencyModuleActive = $this->db->sql_query("SELECT title, active
            FROM nuke_modules
            WHERE title = 'Free_Agency'
            LIMIT 1");

        return $this->db->sql_result($queryIsFreeAgencyModuleActive, 0, "active");
    }

    public function resetSimContractExtensionAttempts()
    {
        echo '<p>Resetting sim contract extension attempts...<p>';

        $sqlQueryString = "UPDATE ibl_team_info SET Used_Extension_This_Chunk = 0;";
        if ($this->db->sql_query($sqlQueryString)) {
            \UI::displayDebugOutput($sqlQueryString, 'Reset Sim Contract Extension Attempts SQL Query');
            echo '<p>Sim contract extension attempts have been reset.<p>';
            return;
        } else {
            die('Invalid query: ' . $this->db->sql_error());
        }
    }
}
