<?php

declare(strict_types=1);

namespace FreeAgency\Contracts;

use Player\Player;

/**
 * Interface for calculating player contract demands and perceived values.
 *
 * Calculates how much a player values a contract offer based on their preferences,
 * team performance, and other modifiers. Used to determine if a player will accept an offer.
 *
 * @phpstan-type CalculationResult array{modifier: float, random: int, perceivedValue: float}
 */
interface FreeAgencyDemandCalculatorInterface
{
    /**
     * Set a static random factor for testing (overrides actual randomness).
     *
     * By default, contract evaluations include random variance (-5 to +5%) to simulate
     * negotiation dynamics. Pass null to re-enable actual randomness.
     *
     * @param int|null $factor Random factor between -5 and 5, or null for actual randomness
     */
    public function setRandomFactor(?int $factor): void;

    /**
     * Calculate perceived value of a contract offer with team-specific modifiers.
     *
     * Returns all three components independently so they can be stored in the database:
     * - modifier: float (~0.8-1.2), the combined 5-factor modifier
     * - random: int (-5 to +5), the random variance applied
     * - perceivedValue: float, = offerAverage * modifier * ((100 + random) / 100)
     *
     * The 5 modifier factors (from original freeagentoffer.php):
     * 1. Play-for-winner: 0.000153 * (teamWins - teamLosses) * (playerWinner - 1)
     * 2. Tradition: 0.000153 * (tradWins - tradLosses) * (playerTradition - 1)
     * 3. Loyalty: +/-0.025 * (playerLoyalty - 1), positive if staying with team
     * 4. Security: (0.01 * (yearsInOffer - 1) - 0.025) * (playerSecurity - 1)
     * 5. Playing time: -(0.0025 * positionSalary/100 - 0.025) * (playerPlayingTime - 1)
     *
     * @param int $offerAverage Average salary offered per year
     * @param string $teamName Offering team name
     * @param Player $player Player object with preferences and attributes
     * @param int $yearsInOffer Number of years in the contract offer
     *
     * @return CalculationResult All three components for database storage
     */
    public function calculatePerceivedValue(
        int $offerAverage,
        string $teamName,
        Player $player,
        int $yearsInOffer
    ): array;
}
