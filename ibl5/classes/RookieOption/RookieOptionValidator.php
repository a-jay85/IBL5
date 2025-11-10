<?php

namespace RookieOption;

use Services\CommonValidator;

/**
 * Rookie Option Validator
 * 
 * Validates eligibility and business rules for rookie option exercises.
 * Delegates contract validation to PlayerContractValidator via Player object.
 */
class RookieOptionValidator
{
    /**
     * Validates that the player is on the user's team
     * 
     * Delegates to CommonValidator for consistent player ownership validation
     * across the application.
     * 
     * @param object $player Player object with teamName property
     * @param string $userTeamName The user's team name
     * @return array Validation result with 'valid' boolean and optional 'error' message
     */
    public function validatePlayerOwnership($player, string $userTeamName): array
    {
        return CommonValidator::validatePlayerOwnership($player, $userTeamName);
    }
    
    /**
     * Validates rookie option eligibility and returns final year salary if eligible
     * 
     * @param object $player Player object with canRookieOption and getFinalYearRookieContractSalary methods
     * @param string $seasonPhase Current season phase
     * @return array Validation result with 'valid' boolean, optional 'error' message, and 'finalYearSalary' if valid
     */
    public function validateEligibilityAndGetSalary($player, string $seasonPhase): array
    {
        // Use PlayerContractValidator via Player object to check eligibility
        if (!$player->canRookieOption($seasonPhase)) {
            return [
                'valid' => false,
                'error' => 'Sorry, ' . $player->position . ' ' . $player->name . ' is not eligible for a rookie option. Only draft picks are eligible for rookie options, and the option must be exercised before the final season of their rookie contract is underway.'
            ];
        }
        
        // Get final year salary using PlayerContractValidator logic via Player object
        $finalYearSalary = $player->getFinalYearRookieContractSalary();
        
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
