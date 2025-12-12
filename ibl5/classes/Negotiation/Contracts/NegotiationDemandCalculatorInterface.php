<?php

declare(strict_types=1);

namespace Negotiation\Contracts;

use Player\Player;

/**
 * NegotiationDemandCalculatorInterface - Contract demand calculations
 *
 * Calculates contract demands based on player ratings and statistics
 * using market-based analysis to determine fair contract values.
 */
interface NegotiationDemandCalculatorInterface
{
    /**
     * Calculate contract demands for a player
     *
     * Uses player ratings, market maximums, and team factors to calculate
     * fair contract demands including yearly amounts and modifiers.
     *
     * @param Player $player The player object with ratings and stats
     * @param array $teamFactors Team factors affecting demands with keys:
     *                           - 'wins' (int): Team current season wins
     *                           - 'losses' (int): Team current season losses
     *                           - 'tradition_wins' (int): Historical average wins
     *                           - 'tradition_losses' (int): Historical average losses
     *                           - 'money_committed_at_position' (int): Salary committed at position
     * @return array Demand information with keys:
     *               - 'year1' through 'year6' (int): Yearly demand amounts
     *               - 'years' (int): Total years demanded (1-6)
     *               - 'total' (int): Sum of all year amounts
     *               - 'modifier' (float): Applied modifier value
     *
     * **Calculation Overview:**
     * 1. Calculate raw score from player ratings vs market maximums
     * 2. Subtract baseline (700) and multiply by factor (3)
     * 3. Apply team factor modifier based on player preferences
     * 4. Build yearly demands with appropriate raises
     *
     * **Modifiers:**
     * - Play for winner: Based on team win/loss record
     * - Tradition: Based on team historical success
     * - Loyalty: Based on player's loyalty preference
     * - Playing time: Based on money committed at position
     *
     * **Behaviors:**
     * - Year 6 is always 0 (extensions max at 5 years)
     * - Uses standard raise percentage without bird rights
     * - Modifier divides base demands (higher modifier = lower demands)
     */
    public function calculateDemands(Player $player, array $teamFactors): array;
}
