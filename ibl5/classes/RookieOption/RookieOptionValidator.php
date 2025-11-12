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
        // Check eligibility: must pass canRookieOption check AND have non-zero final year salary
        $canRookieOption = $player->canRookieOption($seasonPhase);
        $finalYearSalary = $canRookieOption ? $player->getFinalYearRookieContractSalary() : 0;
        
        if (!$canRookieOption || $finalYearSalary === 0) {
            return [
                'valid' => false,
                'error' => $this->getIneligibilityError($player)
            ];
        }
        
        return [
            'valid' => true,
            'finalYearSalary' => $finalYearSalary
        ];
    }

    /**
     * Returns the ineligibility error message for a player
     * 
     * @param object $player Player object with position and name properties
     * @return string Formatted error message
     */
    private function getIneligibilityError($player): string
    {
        return 'Sorry, ' . $player->position . ' ' . $player->name . ' is not eligible for a rookie option.' . "\n\n" .
        'Only first or second round draft picks with 3 or fewer years of experience are eligible for rookie options, and the option must be exercised before the final season of their rookie contract is underway.';
    }
}
