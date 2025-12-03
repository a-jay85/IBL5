<?php

/**
 * Trading_TradeValidatorInterface - Trade validation rules
 *
 * Validates trade legality including minimum cash amounts, salary cap
 * compliance, and player tradability status.
 */
interface Trading_TradeValidatorInterface
{
    /**
     * Validate minimum cash amounts in a trade
     *
     * Ensures that any non-zero cash sent meets the minimum threshold of 100
     * per year. Zero amounts are allowed (no cash being sent).
     *
     * @param array $userSendsCash Array of cash amounts sent by user team (indexed 1-6)
     * @param array $partnerSendsCash Array of cash amounts sent by partner team (indexed 1-6)
     * @return array Validation result with keys:
     *               - 'valid' (bool): True if both cash arrays pass validation
     *               - 'error' (string|null): Error message if validation failed
     *
     * **Behaviors:**
     * - Filters out zero/empty values before checking minimum
     * - Returns invalid if ANY non-zero value is below 100
     * - Validates user and partner cash separately with specific error messages
     * - Empty cash arrays are valid (no cash being sent)
     */
    public function validateMinimumCashAmounts($userSendsCash, $partnerSendsCash);

    /**
     * Validate post-trade salary cap totals for both teams
     *
     * Calculates what each team's salary would be after the trade and
     * verifies neither exceeds the hard cap (League::HARD_CAP_MAX).
     *
     * @param array $tradeData Pre-calculated cap data with keys:
     *                         - 'userCurrentSeasonCapTotal' (int): User team's current cap
     *                         - 'partnerCurrentSeasonCapTotal' (int): Partner team's current cap
     *                         - 'userCapSentToPartner' (int): Salary user is sending
     *                         - 'partnerCapSentToUser' (int): Salary partner is sending
     * @return array Validation result with keys:
     *               - 'valid' (bool): True if both teams stay under hard cap
     *               - 'errors' (array): Array of error messages (empty if valid)
     *               - 'userPostTradeCapTotal' (int): User's salary after trade
     *               - 'partnerPostTradeCapTotal' (int): Partner's salary after trade
     *
     * **Calculation:**
     * - User post-trade = current - sent + received
     * - Partner post-trade = current - sent + received
     *
     * **Behaviors:**
     * - Returns separate errors for each team exceeding cap
     * - Both errors can be returned if both teams exceed cap
     */
    public function validateSalaryCaps($tradeData);

    /**
     * Check if a player can be traded
     *
     * Verifies that a player is in a tradeable state based on their
     * contract status and waiver status.
     *
     * @param int $playerId Player ID to check
     * @return bool True if player can be traded, false otherwise
     *
     * **Behaviors:**
     * - Returns false if player not found in database
     * - Returns false if player has 0 salary (cy = 0)
     * - Returns false if player is waived (ordinal > JSB::WAIVERS_ORDINAL)
     * - Returns true only if player has contract AND is not waived
     */
    public function canPlayerBeTraded($playerId);

    /**
     * Get cash considerations for current season based on phase
     *
     * Determines which year's cash values to use for cap calculations
     * based on the current season phase. During offseason phases
     * (Playoffs, Draft, Free Agency), uses next year's values.
     *
     * @param array $userSendsCash Cash sent by user team (indexed 1-6)
     * @param array $partnerSendsCash Cash sent by partner team (indexed 1-6)
     * @return array Cash considerations with keys:
     *               - 'cashSentToThem' (int): Cash user sends this "effective" season
     *               - 'cashSentToMe' (int): Cash partner sends this "effective" season
     *
     * **Behaviors:**
     * - During Playoffs/Draft/Free Agency: Uses index [2] (next year)
     * - During Regular Season: Uses index [1] (current year)
     * - Missing values default to 0
     */
    public function getCurrentSeasonCashConsiderations($userSendsCash, $partnerSendsCash);
}
