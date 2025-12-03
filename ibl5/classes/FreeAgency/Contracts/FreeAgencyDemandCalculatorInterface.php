<?php

declare(strict_types=1);

namespace FreeAgency\Contracts;

use Player\Player;

/**
 * Interface for calculating player contract demands and perceived values
 * 
 * Calculates how much a player values a contract offer based on their preferences,
 * team performance, and other modifiers. Used to determine if a player will accept an offer.
 */
interface FreeAgencyDemandCalculatorInterface
{
    /**
     * Set a static random factor for testing (overrides actual randomness)
     * 
     * By default, contract evaluations include random variance (-5 to +5%) to simulate
     * negotiation dynamics and player mood. This method allows test code to control
     * the random factor for deterministic testing.
     * 
     * Pass null to re-enable actual random number generation (default behavior).
     * 
     * @param int|null $factor Random factor between -5 and 5 (percentage), or null for actual randomness
     * @return void
     */
    public function setRandomFactor(?int $factor): void;

    /**
     * Calculate perceived value of a contract offer with team-specific modifiers
     * 
     * Takes a flat contract offer and applies modifiers based on:
     * - Team performance (wins/losses this season): Play-for-winner preference
     * - Team tradition (historical wins/losses): Team reputation preference
     * - Loyalty factor: Bonus for staying (if same team) or penalty for leaving
     * - Security factor: Longer contracts increase perceived value
     * - Playing time factor: Lower position salary commitment means more opportunity
     * - Random variance: -5% to +5% for negotiation dynamics
     * 
     * **Return value interpretation**:
     * - 1.0 = Perceived value equals flat offer amount
     * - 1.2 = Player values offer 20% higher (more attractive)
     * - 0.8 = Player values offer 20% lower (less attractive)
     * - Typical range: 0.8-1.2 based on modifiers
     * 
     * **Modifier sources** (from Player object):
     * - freeAgencyPlayForWinner: 1-5 (desire to play for winning team)
     * - freeAgencyTradition: 1-5 (value of team history/reputation)
     * - freeAgencyLoyalty: 1-5 (loyalty to current team)
     * - freeAgencySecurity: 1-5 (desire for contract stability)
     * - freeAgencyPlayingTime: 1-5 (desire for playing time opportunity)
     * 
     * @param int $offerAverage Average salary offered per year (offer total / years)
     * @param string $teamName Offering team name
     * @param Player $player Player object with preferences and attributes
     * @param int $yearsInOffer Number of years in the contract offer
     * 
     * @return float Perceived value multiplier (typically 0.8-1.2)
     *               Multiply offer average by this to get perceived value
     */
    public function calculatePerceivedValue(
        int $offerAverage,
        string $teamName,
        Player $player,
        int $yearsInOffer
    ): float;

    /**
     * Get player's salary demands for all 6 contract years
     * 
     * Returns the "base" demands that a player states at the start of negotiation.
     * These are unadjusted for the offering team's attributes (see calculatePerceivedValue).
     * 
     * The demands typically represent what the player thinks they deserve based on
     * their ratings and experience, before team-specific modifiers are applied.
     * 
     * @param string $playerName Player name (used to look up demands in database)
     * 
     * @return array<int, int> Demands array with keys dem1-dem6, each an integer salary
     *                         Indexed as: dem1, dem2, dem3, dem4, dem5, dem6
     */
    public function getPlayerDemands(string $playerName): array;
}
