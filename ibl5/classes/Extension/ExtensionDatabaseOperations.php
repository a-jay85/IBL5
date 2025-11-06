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
    private $newsService;

    public function __construct($db)
    {
        $this->db = $db;
        $this->newsService = new \Services\NewsService($db);
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
        
        $playerNameEscaped = \Services\DatabaseService::escapeString($this->db, $playerName);
        
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
     * Marks that a team has used their extension attempt for this sim
     * 
     * @param string $teamName Team name
     * @return bool Success status
     */
    public function markExtensionUsedThisSim($teamName)
    {
        $teamNameEscaped = \Services\DatabaseService::escapeString($this->db, $teamName);
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
        $teamNameEscaped = \Services\DatabaseService::escapeString($this->db, $teamName);
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
        // Get team's topic ID
        $topicID = $this->newsService->getTopicIDByTeamName($teamName);
        if ($topicID === null) {
            return false;
        }
        
        // Get category ID
        $categoryID = $this->newsService->getCategoryIDByTitle('Contract Extensions');
        if ($categoryID === null) {
            return false;
        }
        
        // Increment counter
        $this->newsService->incrementCategoryCounter('Contract Extensions');
        
        // Create the story
        $playerNameEscaped = \Services\DatabaseService::escapeString($this->db, $playerName);
        $teamNameEscaped = \Services\DatabaseService::escapeString($this->db, $teamName);
        $title = "$playerNameEscaped extends their contract with the $teamNameEscaped";
        $hometext = "$playerNameEscaped today accepted a contract extension offer from the $teamNameEscaped worth $offerInMillions million dollars over $offerYears years";
        if ($offerDetails) {
            $hometext .= ":<br>" . $offerDetails;
        }
        $hometext .= ".";
        
        return $this->newsService->createNewsStory($categoryID, $topicID, $title, $hometext);
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
        // Get team's topic ID
        $topicID = $this->newsService->getTopicIDByTeamName($teamName);
        if ($topicID === null) {
            return false;
        }
        
        // Get category ID
        $categoryID = $this->newsService->getCategoryIDByTitle('Contract Extensions');
        if ($categoryID === null) {
            return false;
        }
        
        // Increment counter
        $this->newsService->incrementCategoryCounter('Contract Extensions');
        
        // Create the story
        $playerNameEscaped = \Services\DatabaseService::escapeString($this->db, $playerName);
        $teamNameEscaped = \Services\DatabaseService::escapeString($this->db, $teamName);
        $title = "$playerNameEscaped turns down an extension offer from the $teamNameEscaped";
        $hometext = "$playerNameEscaped today rejected a contract extension offer from the $teamNameEscaped worth $offerInMillions million dollars over $offerYears years.";
        
        return $this->newsService->createNewsStory($categoryID, $topicID, $title, $hometext);
    }

    /**
     * Retrieves player preferences and info
     * 
     * @param string $playerName Player name
     * @return array|null Player info array or null if not found
     */
    public function getPlayerPreferences($playerName)
    {
        $playerNameEscaped = \Services\DatabaseService::escapeString($this->db, $playerName);
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
        $playerNameEscaped = \Services\DatabaseService::escapeString($this->db, $playerName);
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
}
