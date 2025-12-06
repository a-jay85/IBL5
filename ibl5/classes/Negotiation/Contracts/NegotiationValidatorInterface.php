<?php

declare(strict_types=1);

namespace Negotiation\Contracts;

use Player\Player;

/**
 * NegotiationValidatorInterface - Negotiation eligibility validation
 *
 * Validates eligibility and business rules for contract negotiations.
 * Reuses PlayerContractValidator for contract-specific checks.
 */
interface NegotiationValidatorInterface
{
    /**
     * Validate if a player can negotiate a contract
     *
     * Checks ownership and contract renegotiation eligibility.
     *
     * @param Player $player The player to check
     * @param string $userTeamName The user's team name for ownership validation
     * @return array Validation result with keys:
     *               - 'valid' (bool): True if player can negotiate
     *               - 'error' (string|null): Error message if validation failed
     *
     * **Validations Performed:**
     * 1. Player ownership - Player must be on user's team
     * 2. Contract eligibility - Player must be able to renegotiate
     *
     * **Behaviors:**
     * - Uses CommonValidator for ownership check
     * - Uses PlayerContractValidator::canRenegotiateContract() for contract check
     * - Creates PlayerData object from Player for contract validator
     * - Returns first error encountered (does not aggregate)
     */
    public function validateNegotiationEligibility(Player $player, string $userTeamName): array;

    /**
     * Check if free agency module is active
     *
     * Contract extensions are not allowed during the free agency period.
     *
     * @param string $prefix Database table prefix (e.g., 'nuke')
     * @return array Validation result with keys:
     *               - 'valid' (bool): True if free agency is NOT active
     *               - 'error' (string|null): Error message if free agency is active
     *
     * **Behaviors:**
     * - Queries {prefix}_modules table for 'Free_Agency' module status
     * - Returns invalid if module.active = 1
     * - Returns valid if module not found or active = 0
     */
    public function validateFreeAgencyNotActive(string $prefix): array;
}
