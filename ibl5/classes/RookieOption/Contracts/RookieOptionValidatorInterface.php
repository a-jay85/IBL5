<?php

declare(strict_types=1);

namespace RookieOption\Contracts;

use Player\Player;

/**
 * RookieOptionValidatorInterface - Contract for rookie option validation
 *
 * Defines validation rules for rookie option exercises. Validates player
 * ownership and eligibility based on experience and contract status.
 *
 * @phpstan-type EligibilityResult array{valid: bool, error?: string|null, finalYearSalary?: int}
 *
 * @package RookieOption\Contracts
 */
interface RookieOptionValidatorInterface
{
    /**
     * Validates that the player is on the user's team
     *
     * @param Player $player Player object with teamName property
     * @param string $userTeamName The user's team name
     */
    public function validatePlayerOwnership(Player $player, string $userTeamName): \Services\ValidationResult;

    /**
     * Validates rookie option eligibility and returns final year salary if eligible
     *
     * Checks if a player is eligible for a rookie option exercise and returns
     * their final year salary if they are.
     *
     * @param Player $player Player object
     * @param string $seasonPhase Current season phase (e.g., "Regular Season", "Free Agency")
     * @return array{valid: bool, error?: string, finalYearSalary?: int} Validation result
     *
     * **Eligibility Requirements:**
     * - Must pass player's canRookieOption() check
     * - Must have non-zero final year rookie contract salary
     *
     * **Error Message Includes:**
     * - Player position and name
     * - Explanation of eligibility requirements
     */
    public function validateEligibilityAndGetSalary(Player $player, string $seasonPhase): array;
}
