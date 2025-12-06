<?php

declare(strict_types=1);

namespace Extension\Contracts;

/**
 * ExtensionOfferEvaluatorInterface - Contract for extension offer evaluation
 * 
 * Defines the evaluation logic for contract extension offers. Calculates whether
 * a player will accept or reject an extension based on offer value, team factors,
 * and player preferences.
 * 
 * @package Extension\Contracts
 */
interface ExtensionOfferEvaluatorInterface
{
    /**
     * Calculates the total value, years, and average per year of an offer
     * 
     * Analyzes an offer array to determine its total value and structure.
     * 
     * @param array{year1: int, year2: int, year3: int, year4?: int, year5?: int} $offer
     *        Contract offer with yearly salary amounts in thousands
     * @return array{total: int, years: int, averagePerYear: float} Analysis result:
     *         - 'total': int - Sum of all years
     *         - 'years': int - Number of years (3, 4, or 5)
     *         - 'averagePerYear': float - Average salary per year
     * 
     * IMPORTANT BEHAVIORS:
     *  - If year5 == 0: 4 years
     *  - If year4 == 0: 3 years
     *  - Otherwise: 5 years
     */
    public function calculateOfferValue(array $offer): array;

    /**
     * Calculates the winner modifier based on team wins/losses and player preference
     * 
     * Players who value winning adjust their demands based on team performance.
     * Formula: 0.000153 * (wins - losses) * (player_winner - 1)
     * 
     * @param array{wins: int, losses: int} $teamFactors Team performance data
     * @param array{winner: int} $playerPreferences Player's "play for winner" preference (1-5 scale)
     * @return float Modifier contribution (positive for winning teams with winner-preferring players)
     */
    public function calculateWinnerModifier(array $teamFactors, array $playerPreferences): float;

    /**
     * Calculates the tradition modifier based on franchise history and player preference
     * 
     * Players who value tradition adjust their demands based on franchise success.
     * Formula: 0.000153 * (tradition_wins - tradition_losses) * (player_tradition - 1)
     * 
     * @param array{tradition_wins: int, tradition_losses: int} $teamFactors Franchise history data
     * @param array{tradition: int} $playerPreferences Player's tradition preference (1-5 scale)
     * @return float Modifier contribution
     */
    public function calculateTraditionModifier(array $teamFactors, array $playerPreferences): float;

    /**
     * Calculates the loyalty modifier based on player's loyalty rating
     * 
     * Loyal players are more likely to accept hometown discounts.
     * Formula: 0.025 * (player_loyalty - 1)
     * 
     * @param array{loyalty: int} $playerPreferences Player's loyalty rating (1-5 scale)
     * @return float Modifier contribution (0 to 0.1 range)
     */
    public function calculateLoyaltyModifier(array $playerPreferences): float;

    /**
     * Calculates the playing time modifier based on money committed at position
     * 
     * Players who value playing time discount for teams with less committed money
     * at their position (indicating more opportunity).
     * Formula: -0.0025 * (money_committed / 100) * (player_playingtime - 1)
     * 
     * @param array{money_committed_at_position: int} $teamFactors Salary at player's position
     * @param array{playing_time: int} $playerPreferences Player's playing time preference (1-5 scale)
     * @return float Modifier contribution
     */
    public function calculatePlayingTimeModifier(array $teamFactors, array $playerPreferences): float;

    /**
     * Calculates the combined modifier from all factors
     * 
     * Base modifier is 1.0, then adds all individual modifiers.
     * 
     * @param array{wins: int, losses: int, tradition_wins: int, tradition_losses: int, money_committed_at_position: int} $teamFactors
     *        Team information including wins, losses, tradition, position money
     * @param array{winner: int, tradition: int, loyalty: int, playing_time: int} $playerPreferences
     *        Player preferences for evaluation
     * @return float Combined modifier (typically 0.8 to 1.2 range)
     * 
     * IMPORTANT BEHAVIORS:
     *  - Base: 1.0
     *  - + winnerModifier
     *  - + traditionModifier
     *  - + loyaltyModifier
     *  - + playingTimeModifier
     */
    public function calculateCombinedModifier(array $teamFactors, array $playerPreferences): float;

    /**
     * Evaluates whether a player will accept an extension offer
     * 
     * Main evaluation method that determines offer acceptance based on
     * adjusted offer value vs player demands.
     * 
     * @param array{year1: int, year2: int, year3: int, year4?: int, year5?: int} $offer
     *        Contract offer with yearly salary amounts in thousands
     * @param array{year1: int, year2: int, year3: int, year4?: int, year5?: int} $demands
     *        Player's demands with yearly salary amounts in thousands
     * @param array{wins: int, losses: int, tradition_wins: int, tradition_losses: int, money_committed_at_position: int} $teamFactors
     *        Team information for modifier calculation
     * @param array{winner: int, tradition: int, loyalty: int, playing_time: int} $playerPreferences
     *        Player preferences for modifier calculation
     * @return array{accepted: bool, offerValue: float, demandValue: float, modifier: float, offerYears: int}
     *         Evaluation result:
     *         - 'accepted': bool - Whether player accepts the offer
     *         - 'offerValue': float - Adjusted offer value (avg * modifier)
     *         - 'demandValue': float - Player's demand value (avg per year)
     *         - 'modifier': float - Combined modifier applied
     *         - 'offerYears': int - Number of years in offer
     * 
     * IMPORTANT BEHAVIORS:
     *  - Accepted if: (offerAvgPerYear * modifier) >= demandAvgPerYear
     */
    public function evaluateOffer(array $offer, array $demands, array $teamFactors, array $playerPreferences): array;

    /**
     * Calculates player demands based on player value
     * 
     * Helper method for testing - generates demand array from player value.
     * 
     * @param int $playerValue Player's value rating
     * @return array{total: int, years: int} Demands array:
     *         - 'total': int - Total demand (playerValue * 5)
     *         - 'years': int - Demand years (always 5)
     */
    public function calculatePlayerDemands(int $playerValue): array;
}
