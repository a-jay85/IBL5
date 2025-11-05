<?php

namespace Draft;

use Services\DatabaseService;

/**
 * Handles all database operations for the Draft module
 * 
 * Responsibilities:
 * - Query draft picks and selections
 * - Update draft tables
 * - Query team and player information
 * - Retrieve Discord IDs
 */
class DraftRepository
{
    private $db;
    private $commonRepository;

    // Constants for player name matching
    const IBL_PLR_NAME_MAX_LENGTH = 32;  // Matches varchar(32) in ibl_plr.name
    const PARTIAL_NAME_MATCH_LENGTH = 30;  // For LIKE queries with diacritical differences

    public function __construct($db)
    {
        $this->db = $db;
        $this->commonRepository = new \Services\CommonRepository($db);
    }

    /**
     * Get the current draft selection for a specific pick
     * 
     * @param int $draftRound The draft round
     * @param int $draftPick The pick number
     * @return string|null The player name already selected, or null if pick is available
     */
    public function getCurrentDraftSelection($draftRound, $draftPick)
    {
        $draftRound = DatabaseService::escapeString($this->db, (string)$draftRound);
        $draftPick = DatabaseService::escapeString($this->db, (string)$draftPick);

        $query = "SELECT `player`
            FROM ibl_draft
            WHERE `round` = '$draftRound' 
               AND `pick` = '$draftPick'";
        
        $result = $this->db->sql_query($query);
        
        if ($result && $this->db->sql_numrows($result) > 0) {
            return $this->db->sql_result($result, 0, 'player');
        }
        
        return null;
    }

    /**
     * Update the draft table with a player selection
     * 
     * @param string $playerName The name of the drafted player
     * @param string $date The date/time of the selection
     * @param int $draftRound The draft round
     * @param int $draftPick The pick number
     * @return bool True if update succeeded, false otherwise
     */
    public function updateDraftTable($playerName, $date, $draftRound, $draftPick)
    {
        $playerName = DatabaseService::escapeString($this->db, $playerName);
        $date = DatabaseService::escapeString($this->db, $date);
        $draftRound = DatabaseService::escapeString($this->db, (string)$draftRound);
        $draftPick = DatabaseService::escapeString($this->db, (string)$draftPick);

        $query = "UPDATE ibl_draft 
             SET `player` = '$playerName', 
                   `date` = '$date' 
            WHERE `round` = '$draftRound' 
               AND `pick` = '$draftPick'";
        
        $result = $this->db->sql_query($query);
        return (bool)$result;
    }

    /**
     * Update the rookie table to mark player as drafted
     * 
     * @param string $playerName The name of the drafted player
     * @param string $teamName The name of the team that drafted the player
     * @return bool True if update succeeded, false otherwise
     */
    public function updateRookieTable($playerName, $teamName)
    {
        $playerName = DatabaseService::escapeString($this->db, $playerName);
        $teamName = DatabaseService::escapeString($this->db, $teamName);

        $query = "UPDATE `ibl_draft_class`
              SET `team` = '$teamName', 
               `drafted` = '1'
            WHERE `name` = '$playerName'";
        
        $result = $this->db->sql_query($query);
        return (bool)$result;
    }

    /**
     * Update the player table to set team information for a drafted player
     * 
     * This method handles partial name matches since ibl_plr.name may be truncated
     * (varchar 32) or missing diacriticals compared to the full name in ibl_draft_class.
     * 
     * @param string $playerName The name of the drafted player (from ibl_draft_class)
     * @param string $teamName The name of the team that drafted the player
     * @return bool True if update succeeded, false otherwise
     */
    public function updatePlayerTable($playerName, $teamName)
    {
        // Get the team ID from team name
        $teamId = $this->commonRepository->getTidFromTeamname($teamName);
        
        if ($teamId === null) {
            // Team not found - this shouldn't happen but handle gracefully
            return false;
        }

        $playerNameEscaped = DatabaseService::escapeString($this->db, $playerName);
        $teamNameEscaped = DatabaseService::escapeString($this->db, $teamName);

        // First try exact match
        $query = "UPDATE ibl_plr
                  SET tid = $teamId, 
                      teamname = '$teamNameEscaped'
                  WHERE name = '$playerNameEscaped'";
        
        $result = $this->db->sql_query($query);
        
        // Check if the update affected any rows
        if ($result && $this->db->sql_affectedrows() > 0) {
            return true;
        }

        // If no exact match, try partial match (for truncated names or diacriticals)
        // Match if the ibl_plr name is a prefix of the full name from draft class
        // This handles truncation at 32 characters
        $truncatedName = substr($playerName, 0, self::IBL_PLR_NAME_MAX_LENGTH);
        $truncatedNameEscaped = DatabaseService::escapeString($this->db, $truncatedName);
        
        $query = "UPDATE ibl_plr
                  SET tid = $teamId, 
                      teamname = '$teamNameEscaped'
                  WHERE name = '$truncatedNameEscaped'";
        
        $result = $this->db->sql_query($query);
        
        if ($result && $this->db->sql_affectedrows() > 0) {
            return true;
        }

        // If still no match, try a LIKE match for diacritical differences
        // This uses % wildcards to match similar names
        // We'll match the first 30 characters to avoid matching wrong players
        $partialName = substr($playerName, 0, self::PARTIAL_NAME_MATCH_LENGTH);
        $partialNameEscaped = DatabaseService::escapeString($this->db, $partialName);
        
        $query = "UPDATE ibl_plr
                  SET tid = $teamId, 
                      teamname = '$teamNameEscaped'
                  WHERE name LIKE '$partialNameEscaped%'
                  LIMIT 1";
        
        $result = $this->db->sql_query($query);
        
        // Check if this final attempt affected any rows
        return (bool)($result && $this->db->sql_affectedrows() > 0);
    }

    /**
     * Check if a player has already been drafted
     * 
     * @param string $playerName The name of the player to check
     * @return bool True if player has been drafted, false otherwise
     */
    public function isPlayerAlreadyDrafted($playerName)
    {
        $playerName = DatabaseService::escapeString($this->db, $playerName);

        $query = "SELECT drafted 
            FROM ibl_draft_class 
            WHERE name = '$playerName' 
            LIMIT 1";
        
        $result = $this->db->sql_query($query);
        
        if ($result && $this->db->sql_numrows($result) > 0) {
            $drafted = $this->db->sql_result($result, 0, 'drafted');
            return $drafted == '1' || $drafted === 1;
        }
        
        return false;
    }

    /**
     * Get the next team on the clock
     * 
     * @return string|null The team name with the next pick, or null if draft is complete
     */
    public function getNextTeamOnClock()
    {
        $query = "SELECT team 
            FROM ibl_draft 
            WHERE player = '' 
            ORDER BY round ASC, pick ASC 
            LIMIT 1";
        
        $result = $this->db->sql_query($query);
        
        if ($result && $this->db->sql_numrows($result) > 0) {
            return $this->db->sql_result($result, 0, 'team');
        }
        
        return null;
    }

    /**
     * Get the Discord ID for a team
     * 
     * @deprecated Use CommonRepository::getTeamDiscordID() instead
     * @param string $teamName The team name
     * @return string|null The Discord ID, or null if not found
     */
    public function getTeamDiscordID($teamName)
    {
        return $this->commonRepository->getTeamDiscordID($teamName);
    }

    /**
     * Get all players in the draft class
     * 
     * @return array Array of player records from the draft class
     */
    public function getAllDraftClassPlayers()
    {
        $query = "SELECT * FROM ibl_draft_class ORDER BY drafted, name";
        
        $result = $this->db->sql_query($query);
        $players = [];
        
        if ($result) {
            while ($row = $this->db->sql_fetchrow($result)) {
                $players[] = $row;
            }
        }
        
        return $players;
    }

    /**
     * Get the current draft pick information (next available pick)
     * 
     * @return array|null Array with 'team', 'round', 'pick' keys, or null if draft is complete
     */
    public function getCurrentDraftPick()
    {
        $query = "SELECT * FROM ibl_draft WHERE player = '' ORDER BY round ASC, pick ASC LIMIT 1";
        
        $result = $this->db->sql_query($query);
        
        if ($result && $this->db->sql_numrows($result) > 0) {
            return [
                'team' => $this->db->sql_result($result, 0, 'team'),
                'round' => $this->db->sql_result($result, 0, 'round'),
                'pick' => $this->db->sql_result($result, 0, 'pick')
            ];
        }
        
        return null;
    }
}
