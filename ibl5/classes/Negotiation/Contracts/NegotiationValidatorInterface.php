<?php

declare(strict_types=1);

namespace Negotiation\Contracts;

use Player\Player;

/**
 * NegotiationValidatorInterface - Negotiation eligibility validation
 *
 * Validates eligibility and business rules for contract negotiations.
 * Reuses PlayerContractValidator for contract-specific checks.
 *
 * @phpstan-type ValidationResult array{valid: bool, error?: string|null}
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
     * @return ValidationResult Validation result
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
     * @return ValidationResult Validation result
     *
     * **Behaviors:**
     * - Queries nuke_modules table for 'FreeAgency' module status
     * - Returns invalid if module.active = 1
     * - Returns valid if module not found or active = 0
     */
    public function validateFreeAgencyNotActive(): array;
}
