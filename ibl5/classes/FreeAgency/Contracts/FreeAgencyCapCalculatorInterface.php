<?php

declare(strict_types=1);

namespace FreeAgency\Contracts;

/**
 * Interface for calculating cap space and roster availability
 * 
 * Calculates soft cap space, hard cap space, total committed salaries, and available roster spots
 * for all 6 contract years based on current roster and pending offers.
 */
interface FreeAgencyCapCalculatorInterface
{
    /**
     * Calculate team cap metrics for all contract years
     * 
     * Returns comprehensive cap and roster data:
     * - totalSalaries: Array of committed salaries for years 1-6
     * - softCapSpace: Array of soft cap room for years 1-6 (Soft Cap Max minus total salaries)
     * - hardCapSpace: Array of hard cap room for years 1-6 (Hard Cap Max minus total salaries)
     * - rosterSpots: Array of available roster spots for years 1-6
     * 
     * Calculations include:
     * - All players under contract (via Player::getFutureSalaries())
     * - All pending contract offers (offer1-6 from ibl_fa_offers table)
     * - Optional exclusion of one player's pending offer (for amending offers)
     * 
     * The excludeOfferPlayerName parameter allows accurate cap calculations when amending
     * an existing offer. Pass the player name to exclude their current pending offer,
     * and the amended soft cap space will be calculated correctly.
     * 
     * **Roster spot counting rules**:
     * - Players whose name starts with '|' are not counted as roster spots
     * - Only counts players on the same team
     * - Counts a spot for each year the player has non-zero salary
     * 
     * @param string|null $excludeOfferPlayerName Optional player name to exclude from offer calculations
     *                                             Used when amending an offer to calculate accurate cap space
     *                                             Pass null to include all pending offers (default)
     * 
     * @return array<string, mixed> Team cap metrics with keys:
     *                              - totalSalaries: array<int> [0-5] indexed array of committed salaries
     *                              - softCapSpace: array<int> [0-5] indexed array of soft cap room
     *                              - hardCapSpace: array<int> [0-5] indexed array of hard cap room
     *                              - rosterSpots: array<int> [0-5] indexed array of available spots
     *                              
     *                              All arrays are 0-indexed for years 1-6 (index 0 = year 1, etc.)
     *                              All values are integers
     */
    public function calculateTeamCapMetrics(?string $excludeOfferPlayerName = null): array;
}
