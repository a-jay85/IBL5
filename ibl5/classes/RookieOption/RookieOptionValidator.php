<?php

namespace RookieOption;

/**
 * Rookie Option Validator
 * 
 * Validates eligibility and business rules for rookie option exercises.
 */
class RookieOptionValidator
{
    private $processor;
    
    public function __construct()
    {
        $this->processor = new RookieOptionProcessor();
    }
    
    /**
     * Validates that the player is on the user's team
     * 
     * @param object $player Player object with teamName property
     * @param string $userTeamName The user's team name
     * @return array Validation result with 'valid' boolean and optional 'error' message
     */
    public function validatePlayerOwnership($player, string $userTeamName): array
    {
        if ($player->teamName !== $userTeamName) {
            return [
                'valid' => false,
                'error' => $player->position . ' ' . $player->name . ' is not on your team.'
            ];
        }
        
        return ['valid' => true];
    }
    
    /**
     * Validates rookie option eligibility and returns final year salary if eligible
     * 
     * @param object $player Player object with canRookieOption method and contract properties
     * @param string $seasonPhase Current season phase
     * @return array Validation result with 'valid' boolean, optional 'error' message, and 'finalYearSalary' if valid
     */
    public function validateEligibilityAndGetSalary($player, string $seasonPhase): array
    {
        if (!$player->canRookieOption($seasonPhase)) {
            return [
                'valid' => false,
                'error' => 'Sorry, ' . $player->position . ' ' . $player->name . ' is not eligible for a rookie option. Only draft picks are eligible for rookie options, and the option must be exercised before the final season of their rookie contract is underway.'
            ];
        }
        
        $finalYearSalary = $this->processor->getFinalYearRookieContractSalary(
            $player->draftRound,
            $player->contractYear2Salary,
            $player->contractYear3Salary
        );
        
        if ($finalYearSalary === 0) {
            return [
                'valid' => false,
                'error' => 'Sorry, ' . $player->position . ' ' . $player->name . ' is not eligible for a rookie option. Only draft picks are eligible for rookie options, and the option must be exercised before the final season of their rookie contract is underway.'
            ];
        }
        
        return [
            'valid' => true,
            'finalYearSalary' => $finalYearSalary
        ];
    }
}
