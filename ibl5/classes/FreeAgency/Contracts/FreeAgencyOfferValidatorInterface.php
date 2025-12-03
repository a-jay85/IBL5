<?php

declare(strict_types=1);

namespace FreeAgency\Contracts;

/**
 * Interface for validating free agency contract offers
 * 
 * Enforces all CBA constraints and cap rules when validating contract offers.
 * All validation errors are returned in structured format (never thrown as exceptions).
 */
interface FreeAgencyOfferValidatorInterface
{
    /**
     * Validate a complete contract offer
     * 
     * Performs comprehensive validation in this order:
     * 1. First year must be > 0
     * 2. MLE availability check (if using MLE exception)
     * 3. LLE availability check (if using LLE exception)
     * 4. First year must meet or exceed veteran's minimum
     * 5. Hard cap space check for year 1
     * 6. Soft cap space check (if no Bird Rights and not using exceptions)
     * 7. Maximum contract value check (based on years of service)
     * 8. Raise and continuity checks:
     *    - No salary decreases year-over-year
     *    - Raises don't exceed allowed percentage (10% standard, 12.5% with Bird Rights)
     *    - No gaps in contract (once year N is 0, all following years must be 0)
     * 
     * **Important**: MLE/LLE checks require team parameter in constructor.
     * Without team, these checks are skipped (treated as valid).
     * 
     * @param array<string, mixed> $offerData Contract offer details (should include):
     *                                          - offer1-6: Salary for each year
     *                                          - birdYears: Years of Bird Rights with current team (0 if free agent)
     *                                          - offerType: Offer type (0=custom, 1=MLE/year, 2+=LLE/VetMin)
     *                                          - vetmin: Veteran's minimum salary
     *                                          - year1Max: Maximum allowed year 1 salary
     *                                          - amendedCapSpaceYear1: Adjusted soft cap space (original + existing offer)
     * 
     * @return array{valid: bool, error?: string} Result with optional error message
     *                                            - valid=true: Offer passes all checks
     *                                            - valid=false: Offer fails, error contains detailed explanation
     */
    public function validateOffer(array $offerData): array;

    /**
     * Check if a player has already been signed during this free agency period
     * 
     * Returns true if player has a non-zero cy1 contract in the database,
     * indicating they've been signed in the current free agency period.
     * Used to prevent duplicate signings of same player.
     * 
     * @param int $playerId Player ID to check
     * @return bool True if player was already signed this free agency period
     */
    public function isPlayerAlreadySigned(int $playerId): bool;
}
