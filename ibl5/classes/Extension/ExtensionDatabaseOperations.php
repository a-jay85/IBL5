<?php

declare(strict_types=1);

namespace Extension;

use Extension\Contracts\ExtensionDatabaseOperationsInterface;

/**
 * ExtensionDatabaseOperations - Database operations for contract extensions
 * 
 * Handles all database operations related to updating player contracts,
 * managing extension usage flags, and creating news stories.
 * 
 * @see ExtensionDatabaseOperationsInterface
 */
class ExtensionDatabaseOperations implements ExtensionDatabaseOperationsInterface
{
    private $db;
    private \Services\NewsService $newsService;

    public function __construct($db)
    {
        $this->db = $db;
        $this->newsService = new \Services\NewsService($db);
    }

    /**
     * @see ExtensionDatabaseOperationsInterface::updatePlayerContract()
     */
    public function updatePlayerContract($playerName, $offer, $currentSalary)
    {
        $offerYears = $this->calculateOfferYears($offer);
        $totalYears = 1 + $offerYears;
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
     * @see ExtensionDatabaseOperationsInterface::markExtensionUsedThisSim()
     */
    public function markExtensionUsedThisSim($teamName)
    {
        $teamNameEscaped = \Services\DatabaseService::escapeString($this->db, $teamName);
        $query = "UPDATE ibl_team_info SET Used_Extension_This_Chunk = 1 WHERE team_name = '$teamNameEscaped'";
        $result = $this->db->sql_query($query);
        return $result !== false;
    }

    /**
     * @see ExtensionDatabaseOperationsInterface::markExtensionUsedThisSeason()
     */
    public function markExtensionUsedThisSeason($teamName)
    {
        $teamNameEscaped = \Services\DatabaseService::escapeString($this->db, $teamName);
        $query = "UPDATE ibl_team_info SET Used_Extension_This_Season = 1 WHERE team_name = '$teamNameEscaped'";
        $result = $this->db->sql_query($query);
        return $result !== false;
    }

    /**
     * @see ExtensionDatabaseOperationsInterface::createAcceptedExtensionStory()
     */
    public function createAcceptedExtensionStory($playerName, $teamName, $offerInMillions, $offerYears, $offerDetails)
    {
        $topicID = $this->newsService->getTopicIDByTeamName($teamName);
        if ($topicID === null) {
            return false;
        }
        
        $categoryID = $this->newsService->getCategoryIDByTitle('Contract Extensions');
        if ($categoryID === null) {
            return false;
        }
        
        $this->newsService->incrementCategoryCounter('Contract Extensions');
        
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
     * @see ExtensionDatabaseOperationsInterface::createRejectedExtensionStory()
     */
    public function createRejectedExtensionStory($playerName, $teamName, $offerInMillions, $offerYears)
    {
        $topicID = $this->newsService->getTopicIDByTeamName($teamName);
        if ($topicID === null) {
            return false;
        }
        
        $categoryID = $this->newsService->getCategoryIDByTitle('Contract Extensions');
        if ($categoryID === null) {
            return false;
        }
        
        $this->newsService->incrementCategoryCounter('Contract Extensions');
        
        $playerNameEscaped = \Services\DatabaseService::escapeString($this->db, $playerName);
        $teamNameEscaped = \Services\DatabaseService::escapeString($this->db, $teamName);
        $title = "$playerNameEscaped turns down an extension offer from the $teamNameEscaped";
        $hometext = "$playerNameEscaped today rejected a contract extension offer from the $teamNameEscaped worth $offerInMillions million dollars over $offerYears years.";
        
        return $this->newsService->createNewsStory($categoryID, $topicID, $title, $hometext);
    }

    /**
     * @see ExtensionDatabaseOperationsInterface::getPlayerPreferences()
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
    
    public function processRejectedExtension($playerName, $teamName, $offer)
    {
        $offerYears = $this->calculateOfferYears($offer);
        $offerTotal = $offer['year1'] + $offer['year2'] + $offer['year3'] + $offer['year4'] + $offer['year5'];
        $offerInMillions = $offerTotal / 100;
        $this->createRejectedExtensionStory($playerName, $teamName, $offerInMillions, $offerYears);
        return ['success' => true];
    }
}
