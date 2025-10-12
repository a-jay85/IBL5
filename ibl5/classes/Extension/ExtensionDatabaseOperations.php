<?php

namespace Extension;

/**
 * Extension Database Operations Class
 * 
 * Handles all database interactions for contract extensions including:
 * - Player contract updates
 * - Team extension flag updates
 * - News story creation
 * - Data retrieval
 */
class ExtensionDatabaseOperations
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Escapes a string for SQL queries
     * Works with both real MySQL class and mock database
     * 
     * @param string $string String to escape
     * @return string Escaped string
     */
    private function escapeString($string)
    {
        // Check if this is the real MySQL class with db_connect_id
        if (isset($this->db->db_connect_id) && $this->db->db_connect_id) {
            return mysqli_real_escape_string($this->db->db_connect_id, $string);
        }
        // Otherwise use the mock's sql_escape_string or fallback to addslashes
        if (method_exists($this->db, 'sql_escape_string')) {
            return $this->db->sql_escape_string($string);
        }
        return addslashes($string);
    }

    /**
     * Updates a player's contract with the new extension terms
     * 
     * @param string $playerName Player name
     * @param array $offer Offer array with year1-year5
     * @param int $currentSalary Player's current year salary
     * @return bool Success status
     */
    public function updatePlayerContract($playerName, $offer, $currentSalary)
    {
        $offerYears = $this->calculateOfferYears($offer);
        $totalYears = 1 + $offerYears;
        
        // Ensure year4 and year5 are set to 0 if empty
        $year4 = (isset($offer['year4']) && $offer['year4'] !== '' && $offer['year4'] !== null) ? $offer['year4'] : 0;
        $year5 = (isset($offer['year5']) && $offer['year5'] !== '' && $offer['year5'] !== null) ? $offer['year5'] : 0;
        
        $playerNameEscaped = $this->escapeString($playerName);
        
        $query = "UPDATE ibl_plr SET 
            cy = 1, 
            cyt = $totalYears, 
            cy1 = $currentSalary, 
            cy2 = {$offer['year1']}, 
            cy3 = {$offer['year2']}, 
            cy4 = {$offer['year3']}, 
            cy5 = $year4, 
            cy6 = $year5 
            WHERE name = '$playerNameEscaped'";
        
        $result = $this->db->sql_query($query);
        return $result !== false;
    }

    /**
     * Marks that a team has used their extension attempt for this chunk (sim)
     * 
     * @param string $teamName Team name
     * @return bool Success status
     */
    public function markExtensionUsedThisChunk($teamName)
    {
        $teamNameEscaped = $this->escapeString($teamName);
        $query = "UPDATE ibl_team_info SET Used_Extension_This_Chunk = 1 WHERE team_name = '$teamNameEscaped'";
        $result = $this->db->sql_query($query);
        return $result !== false;
    }

    /**
     * Marks that a team has used their extension for this season
     * 
     * @param string $teamName Team name
     * @return bool Success status
     */
    public function markExtensionUsedThisSeason($teamName)
    {
        $teamNameEscaped = $this->escapeString($teamName);
        $query = "UPDATE ibl_team_info SET Used_Extension_This_Season = 1 WHERE team_name = '$teamNameEscaped'";
        $result = $this->db->sql_query($query);
        return $result !== false;
    }

    /**
     * Creates a news story for an accepted extension
     * 
     * @param string $playerName Player name
     * @param string $teamName Team name
     * @param float $offerInMillions Offer amount in millions
     * @param int $offerYears Number of years
     * @param string $offerDetails Details of the offer (year by year)
     * @return bool Success status
     */
    public function createAcceptedExtensionStory($playerName, $teamName, $offerInMillions, $offerYears, $offerDetails)
    {
        $timestamp = date('Y-m-d H:i:s', time());
        
        // Get team's topic ID
        $teamNameEscaped = $this->escapeString($teamName);
        $querytopic = "SELECT topicid FROM nuke_topics WHERE topicname = '$teamNameEscaped'";
        $resulttopic = $this->db->sql_query($querytopic);
        $topicid = $this->db->sql_result($resulttopic, 0, "topicid");
        
        // Get category info and increment counter
        $querycat = "SELECT catid, counter FROM nuke_stories_cat WHERE title = 'Contract Extensions'";
        $resultcat = $this->db->sql_query($querycat);
        $catid = $this->db->sql_result($resultcat, 0, "catid");
        $counter = $this->db->sql_result($resultcat, 0, "counter");
        
        $newCounter = $counter + 1;
        $queryUpdateCounter = "UPDATE nuke_stories_cat SET counter = $newCounter WHERE title = 'Contract Extensions'";
        $this->db->sql_query($queryUpdateCounter);
        
        // Create the story
        $playerNameEscaped = $this->escapeString($playerName);
        $title = "$playerNameEscaped extends their contract with the $teamNameEscaped";
        $hometext = "$playerNameEscaped today accepted a contract extension offer from the $teamNameEscaped worth $offerInMillions million dollars over $offerYears years";
        if ($offerDetails) {
            $hometext .= ":<br>" . $offerDetails;
        }
        $hometext .= ".";
        
        $hometextEscaped = $this->escapeString($hometext);
        $titleEscaped = $this->escapeString($title);
        
        $querystor = "INSERT INTO nuke_stories (catid, aid, title, time, hometext, topic, informant, counter, alanguage)
            VALUES ('$catid', 'Associated Press', '$titleEscaped', '$timestamp', '$hometextEscaped', '$topicid', 'Associated Press', '0', 'english')";
        
        $result = $this->db->sql_query($querystor);
        return $result !== false;
    }

    /**
     * Creates a news story for a rejected extension
     * 
     * @param string $playerName Player name
     * @param string $teamName Team name
     * @param float $offerInMillions Offer amount in millions
     * @param int $offerYears Number of years
     * @return bool Success status
     */
    public function createRejectedExtensionStory($playerName, $teamName, $offerInMillions, $offerYears)
    {
        $timestamp = date('Y-m-d H:i:s', time());
        
        // Get team's topic ID
        $teamNameEscaped = $this->escapeString($teamName);
        $querytopic = "SELECT topicid FROM nuke_topics WHERE topicname = '$teamNameEscaped'";
        $resulttopic = $this->db->sql_query($querytopic);
        $topicid = $this->db->sql_result($resulttopic, 0, "topicid");
        
        // Get category info and increment counter
        $querycat = "SELECT catid, counter FROM nuke_stories_cat WHERE title = 'Contract Extensions'";
        $resultcat = $this->db->sql_query($querycat);
        $catid = $this->db->sql_result($resultcat, 0, "catid");
        $counter = $this->db->sql_result($resultcat, 0, "counter");
        
        $newCounter = $counter + 1;
        $queryUpdateCounter = "UPDATE nuke_stories_cat SET counter = $newCounter WHERE title = 'Contract Extensions'";
        $this->db->sql_query($queryUpdateCounter);
        
        // Create the story
        $playerNameEscaped = $this->escapeString($playerName);
        $title = "$playerNameEscaped turns down an extension offer from the $teamNameEscaped";
        $hometext = "$playerNameEscaped today rejected a contract extension offer from the $teamNameEscaped worth $offerInMillions million dollars over $offerYears years.";
        
        $hometextEscaped = $this->escapeString($hometext);
        $titleEscaped = $this->escapeString($title);
        
        $querystor = "INSERT INTO nuke_stories (catid, aid, title, time, hometext, topic, informant, counter, alanguage)
            VALUES ('$catid', 'Associated Press', '$titleEscaped', '$timestamp', '$hometextEscaped', '$topicid', 'Associated Press', '0', 'english')";
        
        $result = $this->db->sql_query($querystor);
        return $result !== false;
    }

    /**
     * Retrieves team information needed for extension processing
     * 
     * @param string $teamName Team name
     * @return array|null Team info array or null if not found
     */
    public function getTeamExtensionInfo($teamName)
    {
        $teamNameEscaped = $this->escapeString($teamName);
        $query = "SELECT * FROM ibl_team_info WHERE team_name = '$teamNameEscaped'";
        $result = $this->db->sql_query($query);
        
        if (!$result || $this->db->sql_numrows($result) == 0) {
            return null;
        }
        
        return $this->db->sql_fetchrow($result);
    }

    /**
     * Retrieves player preferences and info
     * 
     * @param string $playerName Player name
     * @return array|null Player info array or null if not found
     */
    public function getPlayerPreferences($playerName)
    {
        $playerNameEscaped = $this->escapeString($playerName);
        $query = "SELECT * FROM ibl_plr WHERE name = '$playerNameEscaped'";
        $result = $this->db->sql_query($query);
        
        if (!$result || $this->db->sql_numrows($result) == 0) {
            return null;
        }
        
        return $this->db->sql_fetchrow($result);
    }

    /**
     * Retrieves player's current contract information
     * 
     * @param string $playerName Player name
     * @return array|null Contract info including current salary
     */
    public function getPlayerCurrentContract($playerName)
    {
        $playerNameEscaped = $this->escapeString($playerName);
        $query = "SELECT cy, cy1, cy2, cy3, cy4, cy5, cy6 FROM ibl_plr WHERE name = '$playerNameEscaped'";
        $result = $this->db->sql_query($query);
        
        if (!$result || $this->db->sql_numrows($result) == 0) {
            return null;
        }
        
        $contract = $this->db->sql_fetchrow($result);
        if ($contract && isset($contract['cy'])) {
            $cy = $contract['cy'];
            $contract['currentSalary'] = isset($contract['cy' . $cy]) ? $contract['cy' . $cy] : 0;
        } else {
            $contract['currentSalary'] = 0;
        }
        return $contract;
    }

    /**
     * Calculates the number of years in an offer
     * 
     * @param array $offer Offer array
     * @return int Number of years (3, 4, or 5)
     */
    private function calculateOfferYears($offer)
    {
        $years = 5;
        if ($offer['year5'] == 0) {
            $years = 4;
        }
        if ($offer['year4'] == 0) {
            $years = 3;
        }
        return $years;
    }
    
    /**
     * Process a complete accepted extension workflow
     * 
     * @param string $playerName Player name
     * @param string $teamName Team name
     * @param array $offer Offer array
     * @param int $currentSalary Current salary
     * @return array Success status
     */
    public function processAcceptedExtension($playerName, $teamName, $offer, $currentSalary)
    {
        $this->updatePlayerContract($playerName, $offer, $currentSalary);
        $this->markExtensionUsedThisSeason($teamName);
        $offerYears = $this->calculateOfferYears($offer);
        $offerTotal = $offer['year1'] + $offer['year2'] + $offer['year3'] + $offer['year4'] + $offer['year5'];
        $offerInMillions = $offerTotal / 100;
        $offerDetails = $offer['year1'] . " " . $offer['year2'] . " " . $offer['year3'] . " " . $offer['year4'] . " " . $offer['year5'];
        $this->createAcceptedExtensionStory($playerName, $teamName, $offerInMillions, $offerYears, $offerDetails);
        return ['success' => true];
    }
    
    /**
     * Process a complete rejected extension workflow
     * 
     * @param string $playerName Player name
     * @param string $teamName Team name
     * @param array $offer Offer array
     * @return array Success status
     */
    public function processRejectedExtension($playerName, $teamName, $offer)
    {
        $offerYears = $this->calculateOfferYears($offer);
        $offerTotal = $offer['year1'] + $offer['year2'] + $offer['year3'] + $offer['year4'] + $offer['year5'];
        $offerInMillions = $offerTotal / 100;
        $this->createRejectedExtensionStory($playerName, $teamName, $offerInMillions, $offerYears);
        return ['success' => true];
    }
    
    /**
     * Increments the contract extensions counter
     * 
     * @return bool Success status
     */
    public function incrementExtensionsCounter()
    {
        $query = "SELECT counter FROM nuke_stories_cat WHERE title = 'Contract Extensions'";
        $result = $this->db->sql_query($query);
        $counter = $this->db->sql_result($result, 0, 'counter');
        $newCounter = $counter + 1;
        
        $query = "UPDATE nuke_stories_cat SET counter = $newCounter WHERE title = 'Contract Extensions'";
        $result = $this->db->sql_query($query);
        return $result !== false;
    }

    /**
     * Marks that a team has used their extension attempt for this chunk using Team object
     * 
     * @param \Team $team Team object
     * @return bool Success status
     */
    public function markExtensionUsedThisChunkWithTeam($team)
    {
        return $this->markExtensionUsedThisChunk($team->name);
    }

    /**
     * Marks that a team has used their extension for this season using Team object
     * 
     * @param \Team $team Team object
     * @return bool Success status
     */
    public function markExtensionUsedThisSeasonWithTeam($team)
    {
        return $this->markExtensionUsedThisSeason($team->name);
    }

    /**
     * Updates a player's contract with the new extension terms using Player object
     * 
     * @param \Player $player Player object
     * @param array $offer Offer array with year1-year5
     * @param int $currentSalary Player's current year salary
     * @return bool Success status
     */
    public function updatePlayerContractWithPlayer($player, $offer, $currentSalary)
    {
        return $this->updatePlayerContract($player->name, $offer, $currentSalary);
    }

    /**
     * Creates a news story for an accepted extension using Player and Team objects
     * 
     * @param \Player $player Player object
     * @param \Team $team Team object
     * @param float $offerInMillions Offer amount in millions
     * @param int $offerYears Number of years
     * @param string $offerDetails Offer details string
     * @return bool Success status
     */
    public function createAcceptedExtensionStoryWithObjects($player, $team, $offerInMillions, $offerYears, $offerDetails)
    {
        return $this->createAcceptedExtensionStory($player->name, $team->name, $offerInMillions, $offerYears, $offerDetails);
    }

    /**
     * Creates a news story for a rejected extension using Player and Team objects
     * 
     * @param \Player $player Player object
     * @param \Team $team Team object
     * @param float $offerInMillions Offer amount in millions
     * @param int $offerYears Number of years
     * @return bool Success status
     */
    public function createRejectedExtensionStoryWithObjects($player, $team, $offerInMillions, $offerYears)
    {
        return $this->createRejectedExtensionStory($player->name, $team->name, $offerInMillions, $offerYears);
    }
}
