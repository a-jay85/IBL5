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
     * Get the next available player ID for drafted players
     * 
     * Uses a high PID range (starting at 90000) to avoid conflicts with JSB-assigned PIDs.
     * These are temporary PIDs that allow drafted players to appear in rosters immediately.
     * When plrParser.php runs with an updated .plr file, it will create proper entries
     * with JSB-assigned PIDs using INSERT ... ON DUPLICATE KEY UPDATE based on pid.
     * 
     * @return int Next available PID in the draft range (>= 90000)
     */
    private function getNextAvailablePid()
    {
        // Use PID range starting at 90000 for drafted players to avoid JSB conflicts
        $draftPidStart = 90000;
        
        $query = "SELECT MAX(pid) as max_pid FROM ibl_plr WHERE pid >= $draftPidStart";
        $result = $this->db->sql_query($query);
        
        if ($result && $this->db->sql_numrows($result) > 0) {
            $maxPid = $this->db->sql_result($result, 0, 'max_pid');
            if ($maxPid !== null && $maxPid !== '' && $maxPid >= $draftPidStart) {
                return (int) $maxPid + 1;
            }
        }
        
        return $draftPidStart; // Start at 90000 if no draft PIDs exist yet
    }

    /**
     * Create a new player entry in ibl_plr from ibl_draft_class data
     * 
     * This method creates a new player record when they are drafted, mapping
     * columns from ibl_draft_class to ibl_plr.
     * 
     * @param string $playerName The name of the drafted player (from ibl_draft_class)
     * @param string $teamName The name of the team that drafted the player
     * @return bool True if insert succeeded, false otherwise
     */
    public function createPlayerFromDraftClass($playerName, $teamName)
    {
        // Get the team ID from team name
        $teamId = $this->commonRepository->getTidFromTeamname($teamName);
        
        if ($teamId === null) {
            // Team not found - this shouldn't happen but handle gracefully
            return false;
        }

        // Ensure teamId is safely cast to int
        $teamId = (int) $teamId;
        
        // Get player data from ibl_draft_class
        $playerNameEscaped = DatabaseService::escapeString($this->db, $playerName);
        $query = "SELECT * FROM ibl_draft_class WHERE name = '$playerNameEscaped' LIMIT 1";
        $result = $this->db->sql_query($query);
        
        if (!$result || $this->db->sql_numrows($result) === 0) {
            // Player not found in draft class
            return false;
        }
        
        $draftClassPlayer = $this->db->sql_fetchrow($result);
        
        // Get next available PID
        $pid = $this->getNextAvailablePid();
        
        // Map columns from ibl_draft_class to ibl_plr
        // Truncate name to 32 characters to fit ibl_plr.name varchar(32)
        $name = substr($playerName, 0, self::IBL_PLR_NAME_MAX_LENGTH);
        $nameEscaped = DatabaseService::escapeString($this->db, $name);
        $teamNameEscaped = DatabaseService::escapeString($this->db, $teamName);
        $pos = DatabaseService::escapeString($this->db, $draftClassPlayer['pos']);
        
        // Map ratings from draft class to player table
        // Offensive: offo->oo, offd->od, offp->po, offt->to
        // Defensive: defo->do, defd->dd, defp->pd, deft->td
        $oo = (int) $draftClassPlayer['offo'];
        $od = (int) $draftClassPlayer['offd'];
        $po = (int) $draftClassPlayer['offp'];
        $to = (int) $draftClassPlayer['offt'];
        $do = (int) $draftClassPlayer['defo'];
        $dd = (int) $draftClassPlayer['defd'];
        $pd = (int) $draftClassPlayer['defp'];
        $td = (int) $draftClassPlayer['deft'];
        
        $age = (int) $draftClassPlayer['age'];
        $sta = (int) $draftClassPlayer['sta'];
        $talent = (int) $draftClassPlayer['tal'];
        $skill = (int) $draftClassPlayer['skl'];
        $intangibles = (int) $draftClassPlayer['int'];
        
        // Insert new player into ibl_plr
        $query = "INSERT INTO ibl_plr (
            pid, name, age, tid, teamname, pos,
            sta, oo, od, po, `to`, `do`, dd, pd, td,
            talent, skill, intangibles,
            active, bird, exp, cy, cyt
        ) VALUES (
            $pid, '$nameEscaped', $age, $teamId, '$teamNameEscaped', '$pos',
            $sta, $oo, $od, $po, $to, $do, $dd, $pd, $td,
            $talent, $skill, $intangibles,
            1, 0, 0, 0, 0
        )";
        
        $result = $this->db->sql_query($query);
        return (bool)$result;
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
