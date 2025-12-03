<?php

namespace RookieOption\Contracts;

/**
 * RookieOptionValidatorInterface - Contract for rookie option validation
 * 
 * Defines validation rules for rookie option exercises. Validates player
 * ownership and eligibility based on experience and contract status.
 * 
 * @package RookieOption\Contracts
 */
interface RookieOptionValidatorInterface
{
    /**
     * Validates that the player is on the user's team
     * 
     * Ensures the requesting user owns the player they're trying to exercise
     * a rookie option on.
     * 
     * @param object $player Player object with teamName property
     * @param string $userTeamName The user's team name
     * @return array Validation result:
     *   - 'valid': bool - True if player is on user's team
     *   - 'error': string|null - Error message if validation failed
     * 
     * **Behaviors:**
     * - Delegates to CommonValidator::validatePlayerOwnership()
     * - Case-sensitive team name comparison
     */
    public function validatePlayerOwnership($player, string $userTeamName): array;

    /**
     * Validates rookie option eligibility and returns final year salary if eligible
     * 
     * Checks if a player is eligible for a rookie option exercise and returns
     * their final year salary if they are.
     * 
     * @param object $player Player object with methods:
     *   - canRookieOption(string $phase): bool
     *   - getFinalYearRookieContractSalary(): int
     * @param string $seasonPhase Current season phase (e.g., "Regular Season", "Free Agency")
     * @return array Validation result:
     *   - 'valid': bool - True if eligible for rookie option
     *   - 'error': string|null - Error message if not eligible
     *   - 'finalYearSalary': int - Final year salary (only if valid)
     * 
     * **Eligibility Requirements:**
     * - Must pass player's canRookieOption() check
     * - Must have non-zero final year rookie contract salary
     * 
     * **Error Message Includes:**
     * - Player position and name
     * - Explanation of eligibility requirements
     */
    public function validateEligibilityAndGetSalary($player, string $seasonPhase): array;
}
