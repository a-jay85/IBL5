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
     */
    public function validateNegotiationEligibility(Player $player, string $userTeamName): \Services\ValidationResult;

    /**
     * Check if free agency module is active
     *
     * Contract extensions are not allowed during the free agency period.
     */
    public function validateFreeAgencyNotActive(): \Services\ValidationResult;

    /**
     * Validate renegotiation eligibility without ownership check.
     *
     * Used by admin debug bypass to inspect any player's demands.
     */
    public function validateRenegotiationEligibility(Player $player): \Services\ValidationResult;
}
