<?php

declare(strict_types=1);

namespace Negotiation\Contracts;

use Player\Player;

/**
 * NegotiationDemandCalculatorInterface - Contract demand calculations
 *
 * Calculates contract demands based on player ratings and statistics
 * using market-based analysis to determine fair contract values.
 *
 * @phpstan-type TeamFactors array{wins: int, losses: int, tradition_wins: int, tradition_losses: int, money_committed_at_position: int}
 * @phpstan-type DemandResult array{year1: float|int, year2: float|int, year3: float|int, year4: float|int, year5: float|int, year6: int, years: int, total: float|int, modifier: float}
 * @phpstan-type RatingBreakdown array{name: string, playerValue: int, marketMax: int, rawScore: int}
 * @phpstan-type ModifierBreakdown array{name: string, formula: string, inputs: string, result: float}
 * @phpstan-type DemandsBreakdown array{ratings: list<RatingBreakdown>, totalRawScore: int, baseline: int, adjustedScore: int, avgDemands: float|int, totalDemands: float|int, baseDemands: float|int, maxRaise: float|int, faPreferences: array{playForWinner: int, tradition: int, loyalty: int, playingTime: int}, teamFactors: TeamFactors, modifiers: list<ModifierBreakdown>, totalModifier: float, demands: DemandResult}
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
     * @param TeamFactors $teamFactors Team factors affecting demands
     * @return DemandResult Demand information
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

    /**
     * Calculate demands with full intermediate breakdown for debugging.
     *
     * @param Player $player The player object with ratings and stats
     * @param TeamFactors $teamFactors Team factors affecting demands
     * @return DemandsBreakdown Full breakdown of the calculation
     */
    public function calculateDemandsWithBreakdown(Player $player, array $teamFactors): array;
}
