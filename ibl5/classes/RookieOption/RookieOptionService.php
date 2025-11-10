<?php

namespace RookieOption;

use Services\CommonRepository;

/**
 * Service class for rookie option form display operations
 * 
 * Handles authorization, eligibility checking, and data preparation
 * for the rookie option form display.
 */
class RookieOptionService
{
    private $db;
    private $commonRepository;
    private $processor;
    
    public function __construct($db)
    {
        $this->db = $db;
        $this->commonRepository = new CommonRepository($db);
        $this->processor = new RookieOptionProcessor();
    }
    
    /**
     * Validates that the user owns the player
     * 
     * @param string $username Current user's username
     * @param object $player Player object with teamID property
     * @return bool True if user owns the player
     */
    public function validatePlayerOwnership(string $username, $player): bool
    {
        $userteam = $this->commonRepository->getTeamnameFromUsername($username);
        $userTeamID = $this->commonRepository->getTidFromTeamname($userteam);
        
        return $userTeamID === $player->teamID;
    }
    
    /**
     * Gets the team name for a username
     * 
     * @param string $username Username
     * @return string Team name
     */
    public function getTeamNameFromUsername(string $username): string
    {
        return $this->commonRepository->getTeamnameFromUsername($username);
    }
    
    /**
     * Checks if a player is eligible for rookie option and gets the final year salary
     * 
     * @param object $player Player object with canRookieOption method and contract properties
     * @param string $seasonPhase Current season phase
     * @return array|null Array with 'eligible' => bool, 'finalYearSalary' => int, or null if not eligible
     */
    public function checkEligibilityAndGetSalary($player, string $seasonPhase): ?array
    {
        if (!$player->canRookieOption($seasonPhase)) {
            return null;
        }
        
        $finalYearSalary = $this->processor->getFinalYearRookieContractSalary(
            $player->draftRound,
            $player->contractYear2Salary,
            $player->contractYear3Salary
        );
        
        if ($finalYearSalary === 0) {
            return null;
        }
        
        return [
            'eligible' => true,
            'finalYearSalary' => $finalYearSalary
        ];
    }
    
    /**
     * Calculates the rookie option value
     * 
     * @param int $finalYearSalary Final year salary of rookie contract
     * @return int Rookie option value
     */
    public function calculateRookieOptionValue(int $finalYearSalary): int
    {
        return $this->processor->calculateRookieOptionValue($finalYearSalary);
    }
}
