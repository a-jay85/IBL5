<?php

namespace Negotiation;

use Player\Player;
use Services\DatabaseService;

/**
 * Negotiation Processor
 * 
 * Orchestrates the complete contract negotiation workflow.
 * Coordinates validation, demand calculation, and view rendering.
 */
class NegotiationProcessor
{
    private $db;
    private $validator;
    private $demandCalculator;
    
    public function __construct($db)
    {
        $this->db = $db;
        $this->validator = new NegotiationValidator($db);
        $this->demandCalculator = new NegotiationDemandCalculator($db);
    }
    
    /**
     * Process a contract negotiation request
     * 
     * @param int $playerID Player ID
     * @param string $userTeamName User's team name
     * @param string $prefix Database table prefix
     * @return string HTML output for the negotiation page
     */
    public function processNegotiation(int $playerID, string $userTeamName, string $prefix): string
    {
        // Load player using existing Player class
        try {
            $player = Player::withPlayerID($this->db, $playerID);
        } catch (\Exception $e) {
            return NegotiationViewHelper::renderError('Player not found.');
        } catch (\TypeError $e) {
            return NegotiationViewHelper::renderError('Player not found.');
        }
        
        // Render page header
        $output = NegotiationViewHelper::renderHeader($player);
        
        // Validate free agency is not active
        $freeAgencyValidation = $this->validator->validateFreeAgencyNotActive($prefix);
        if (!$freeAgencyValidation['valid']) {
            return $output . NegotiationViewHelper::renderError($freeAgencyValidation['error']);
        }
        
        // Validate negotiation eligibility
        $eligibilityValidation = $this->validator->validateNegotiationEligibility($player, $userTeamName);
        if (!$eligibilityValidation['valid']) {
            return $output . NegotiationViewHelper::renderError($eligibilityValidation['error']);
        }
        
        // Get team factors for demand calculation
        $teamFactors = $this->getTeamFactors($userTeamName, $player->position, $player->name);
        
        // Calculate contract demands
        $demands = $this->demandCalculator->calculateDemands($player, $teamFactors);
        
        // Calculate available cap space
        $capSpace = $this->calculateCapSpace($userTeamName);
        
        // Determine max first year salary based on experience
        $maxYearOneSalary = \ContractRules::getMaxContractSalary($player->yearsOfExperience ?? 0);
        
        // Render negotiation form
        $output .= NegotiationViewHelper::renderNegotiationForm(
            $player,
            $demands,
            $capSpace,
            $maxYearOneSalary
        );
        
        return $output;
    }
    
    /**
     * Get team factors for demand calculation
     * 
     * @param string $teamName Team name
     * @param string $playerPosition Player position
     * @param string $playerName Player name (to exclude from position calculation)
     * @return array Team factors
     */
    private function getTeamFactors(string $teamName, string $playerPosition, string $playerName): array
    {
        $teamNameEscaped = DatabaseService::escapeString($this->db, $teamName);
        
        // Get team tradition and current season data
        $query = "SELECT Contract_Wins, Contract_Losses, Contract_AvgW, Contract_AvgL 
                  FROM ibl_team_info WHERE team_name = '$teamNameEscaped'";
        $result = $this->db->sql_query($query);
        $teamData = $this->db->sql_fetchrow($result);
        
        // Calculate money committed at position
        $moneyCommitted = $this->calculateMoneyCommittedAtPosition(
            $teamName,
            $playerPosition,
            $playerName
        );
        
        return [
            'wins' => isset($teamData['Contract_Wins']) ? (int)$teamData['Contract_Wins'] : 41,
            'losses' => isset($teamData['Contract_Losses']) ? (int)$teamData['Contract_Losses'] : 41,
            'tradition_wins' => isset($teamData['Contract_AvgW']) ? (int)$teamData['Contract_AvgW'] : 41,
            'tradition_losses' => isset($teamData['Contract_AvgL']) ? (int)$teamData['Contract_AvgL'] : 41,
            'money_committed_at_position' => $moneyCommitted
        ];
    }
    
    /**
     * Calculate money committed at a position for next season
     * 
     * @param string $teamName Team name
     * @param string $position Position
     * @param string $excludePlayerName Player to exclude
     * @return int Total salary committed
     */
    private function calculateMoneyCommittedAtPosition(
        string $teamName,
        string $position,
        string $excludePlayerName
    ): int {
        $teamNameEscaped = DatabaseService::escapeString($this->db, $teamName);
        $positionEscaped = DatabaseService::escapeString($this->db, $position);
        $playerNameEscaped = DatabaseService::escapeString($this->db, $excludePlayerName);
        
        $query = "SELECT cy, cy2, cy3, cy4, cy5, cy6 
                  FROM ibl_plr 
                  WHERE teamname = '$teamNameEscaped' 
                  AND pos = '$positionEscaped' 
                  AND name != '$playerNameEscaped'";
        
        $result = $this->db->sql_query($query);
        $totalCommitted = 0;
        
        while ($row = $this->db->sql_fetchrow($result)) {
            $currentYear = (int)($row['cy'] ?? 0);
            
            // Look at salary committed next year (for extensions)
            switch ($currentYear) {
                case 1:
                    $totalCommitted += (int)($row['cy2'] ?? 0);
                    break;
                case 2:
                    $totalCommitted += (int)($row['cy3'] ?? 0);
                    break;
                case 3:
                    $totalCommitted += (int)($row['cy4'] ?? 0);
                    break;
                case 4:
                    $totalCommitted += (int)($row['cy5'] ?? 0);
                    break;
                case 5:
                    $totalCommitted += (int)($row['cy6'] ?? 0);
                    break;
            }
        }
        
        return $totalCommitted;
    }
    
    /**
     * Calculate available cap space for next season
     * 
     * @param string $teamName Team name
     * @return int Available cap space
     */
    private function calculateCapSpace(string $teamName): int
    {
        $capSpace = \League::HARD_CAP_MAX;
        $teamNameEscaped = DatabaseService::escapeString($this->db, $teamName);
        
        $query = "SELECT cy, cy2, cy3, cy4, cy5, cy6 
                  FROM ibl_plr 
                  WHERE teamname = '$teamNameEscaped' 
                  AND retired = '0'";
        
        $result = $this->db->sql_query($query);
        
        while ($row = $this->db->sql_fetchrow($result)) {
            $currentYear = (int)($row['cy'] ?? 0);
            
            // Look at salary committed next year
            switch ($currentYear) {
                case 1:
                    $capSpace -= (int)($row['cy2'] ?? 0);
                    break;
                case 2:
                    $capSpace -= (int)($row['cy3'] ?? 0);
                    break;
                case 3:
                    $capSpace -= (int)($row['cy4'] ?? 0);
                    break;
                case 4:
                    $capSpace -= (int)($row['cy5'] ?? 0);
                    break;
                case 5:
                    $capSpace -= (int)($row['cy6'] ?? 0);
                    break;
            }
        }
        
        return $capSpace;
    }
    
}
