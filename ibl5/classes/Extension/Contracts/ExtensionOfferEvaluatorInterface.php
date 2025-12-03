<?php

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
     * @param array $offer Array with keys: year1, year2, year3, year4, year5
     * @return array Analysis result:
     *   - 'total': int - Sum of all years
     *   - 'years': int - Number of years (3, 4, or 5)
     *   - 'averagePerYear': float - Average salary per year
     * 
     * **Year Counting:**
     * - If year5 == 0: 4 years
     * - If year4 == 0: 3 years
     * - Otherwise: 5 years
     */
    public function calculateOfferValue($offer);

    /**
     * Calculates the winner modifier based on team wins/losses and player preference
     * 
     * Players who value winning adjust their demands based on team performance.
     * Formula: 0.000153 * (wins - losses) * (player_winner - 1)
     * 
     * @param array $teamFactors Array with keys:
     *   - 'wins': int - Current season wins
     *   - 'losses': int - Current season losses
     * @param array $playerPreferences Array with keys:
     *   - 'winner': int - Player's "play for winner" preference (1-5 scale)
     * @return float Modifier contribution (positive for winning teams with winner-preferring players)
     */
    public function calculateWinnerModifier($teamFactors, $playerPreferences);

    /**
     * Calculates the tradition modifier based on franchise history and player preference
     * 
     * Players who value tradition adjust their demands based on franchise success.
     * Formula: 0.000153 * (tradition_wins - tradition_losses) * (player_tradition - 1)
     * 
     * @param array $teamFactors Array with keys:
     *   - 'tradition_wins': int - Historical average wins
     *   - 'tradition_losses': int - Historical average losses
     * @param array $playerPreferences Array with keys:
     *   - 'tradition': int - Player's tradition preference (1-5 scale)
     * @return float Modifier contribution
     */
    public function calculateTraditionModifier($teamFactors, $playerPreferences);

    /**
     * Calculates the loyalty modifier based on player's loyalty rating
     * 
     * Loyal players are more likely to accept hometown discounts.
     * Formula: 0.025 * (player_loyalty - 1)
     * 
     * @param array $playerPreferences Array with keys:
     *   - 'loyalty': int - Player's loyalty rating (1-5 scale)
     * @return float Modifier contribution (0 to 0.1 range)
     */
    public function calculateLoyaltyModifier($playerPreferences);

    /**
     * Calculates the playing time modifier based on money committed at position
     * 
     * Players who value playing time discount for teams with less committed money
     * at their position (indicating more opportunity).
     * Formula: -(.0025 * money_committed / 100 - 0.025) * (player_playingtime - 1)
     * 
     * @param array $teamFactors Array with keys:
     *   - 'money_committed_at_position': int - Total salary at player's position
     * @param array $playerPreferences Array with keys:
     *   - 'playing_time': int - Player's playing time preference (1-5 scale)
     * @return float Modifier contribution
     */
    public function calculatePlayingTimeModifier($teamFactors, $playerPreferences);

    /**
     * Calculates the combined modifier from all factors
     * 
     * Base modifier is 1.0, then adds all individual modifiers.
     * 
     * @param array $teamFactors Team information including wins, losses, tradition, position money
     * @param array $playerPreferences Player preferences (winner, tradition, loyalty, playing_time)
     * @return float Combined modifier (typically 0.8 to 1.2 range)
     * 
     * **Modifier Composition:**
     * - Base: 1.0
     * - + winnerModifier
     * - + traditionModifier
     * - + loyaltyModifier
     * - + playingTimeModifier
     */
    public function calculateCombinedModifier($teamFactors, $playerPreferences);

    /**
     * Evaluates whether a player will accept an extension offer
     * 
     * Main evaluation method that determines offer acceptance based on
     * adjusted offer value vs player demands.
     * 
     * @param array $offer Offer array with year1-year5
     * @param array $demands Player's demands array with year1-year5
     * @param array $teamFactors Team information for modifier calculation
     * @param array $playerPreferences Player preferences for modifier calculation
     * @return array Evaluation result:
     *   - 'accepted': bool - Whether player accepts the offer
     *   - 'offerValue': float - Adjusted offer value (avg * modifier)
     *   - 'demandValue': float - Player's demand value (avg per year)
     *   - 'modifier': float - Combined modifier applied
     *   - 'offerYears': int - Number of years in offer
     * 
     * **Acceptance Logic:**
     * - Accepted if: (offerAvgPerYear * modifier) >= demandAvgPerYear
     */
    public function evaluateOffer($offer, $demands, $teamFactors, $playerPreferences);

    /**
     * Calculates player demands based on player value
     * 
     * Helper method for testing - generates demand array from player value.
     * 
     * @param int $playerValue Player's value rating
     * @return array Demands array with:
     *   - 'total': int - Total demand (playerValue * 5)
     *   - 'years': int - Demand years (always 5)
     */
    public function calculatePlayerDemands($playerValue);
}
