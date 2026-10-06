<?php

declare(strict_types=1);

namespace Trading\Contracts;

use Validation\ValidationResult;
use Validation\ValidationResultWithContext;

/**
 * TradeValidatorInterface - Trade validation rules
 *
 * Validates trade legality including minimum cash amounts, salary cap
 * compliance, and player tradability status.
 * 
 * @package Trading\Contracts
 */
interface TradeValidatorInterface
{
    /**
     * Validate minimum cash amounts in a trade
     *
     * Ensures that any non-zero cash sent meets the minimum threshold of 100
     * per year. Zero amounts are allowed (no cash being sent).
     *
     * @param array<int, int> $userSendsCash Cash amounts sent by user team (indexed 1-6)
     * @param array<int, int> $partnerSendsCash Cash amounts sent by partner team (indexed 1-6)
     * @return ValidationResult failure() carries the single legacy message
     *
     * IMPORTANT BEHAVIORS:
     *  - Filters out zero/empty values before checking minimum
     *  - Returns invalid if ANY non-zero value is below 100
     *  - Validates user and partner cash separately with specific error messages
     *  - Empty cash arrays are valid (no cash being sent)
     */
    public function validateMinimumCashAmounts(array $userSendsCash, array $partnerSendsCash): ValidationResult;

    /**
     * Validate post-trade salary cap totals for both teams
     *
     * Calculates what each team's salary would be after the trade and
     * verifies neither exceeds the hard cap (League::HARD_CAP_MAX).
     *
     * @param array{userCurrentSeasonCapTotal?: int, partnerCurrentSeasonCapTotal?: int, userCapSentToPartner?: int, partnerCapSentToUser?: int} $tradeData
     *        Pre-calculated cap data
     * @return ValidationResultWithContext<array{userPostTradeCapTotal: int, partnerPostTradeCapTotal: int}>
     *         Totals/parties are read via getContext(); messages via getErrorMessages().
     *
     * IMPORTANT BEHAVIORS:
     *  - User post-trade = current - sent + received
     *  - Partner post-trade = current - sent + received
     *  - Returns separate errors for each team exceeding cap
     *  - Both errors can be returned if both teams exceed cap
     */
    public function validateSalaryCaps(array $tradeData): ValidationResultWithContext;

    /**
     * Check if a player can be traded
     *
     * Verifies that a player is in a tradeable state based on their
     * contract status and waiver status.
     *
     * @param int $playerId Player ID to check
     * @return bool True if player can be traded, false otherwise
     *
     * IMPORTANT BEHAVIORS:
     *  - Returns false if player not found in database
     *  - Returns false if player has 0 salary (cy = 0)
     *  - Returns false if player is waived (ordinal > JsbConstants::WAIVERS_ORDINAL)
     *  - Returns true only if player has contract AND is not waived
     */
    public function canPlayerBeTraded(int $playerId): bool;

    /**
     * Validate that neither team exceeds the maximum roster size after the trade
     *
     * @param int $userTeamId User's team ID
     * @param int $partnerTeamId Partner's team ID
     * @param int $userPlayersSent Number of players user is sending
     * @param int $partnerPlayersSent Number of players partner is sending
     * @return ValidationResult
     */
    public function validateRosterLimits(
        int $userTeamId,
        int $partnerTeamId,
        int $userPlayersSent,
        int $partnerPlayersSent
    ): ValidationResult;

    /**
     * Validate post-trade salary caps for an arbitrary set of parties (2 or 3+ teams)
     *
     * Generalizes {@see self::validateSalaryCaps()} from a fixed user/partner pair
     * to N parties. Each party supplies its current-season cap total plus the cap it
     * sends and receives in this trade; post-trade total is `current - sent + received`,
     * checked against League::HARD_CAP_MAX.
     *
     * @param list<array{teamName: string, currentSeasonCapTotal: int, capSent: int, capReceived: int}> $partyCapDeltas
     * @return ValidationResultWithContext<list<array{teamName: string, postTradeCapTotal: int, overCap: bool}>>
     */
    public function validateSalaryCapsForParties(array $partyCapDeltas): ValidationResultWithContext;

    /**
     * Validate post-trade roster limits for an arbitrary set of parties (2 or 3+ teams)
     *
     * Generalizes {@see self::validateRosterLimits()} from a fixed user/partner pair
     * to N parties. Each party's post-trade roster is `current - sent + received`,
     * checked against Team::ROSTER_SPOTS_MAX. Current roster size per party is read
     * via the form repository's getTeamPlayerCount().
     *
     * @param list<array{teamId: int, teamName: string, playersSent: int, playersReceived: int}> $partyRosterDeltas
     * @return ValidationResultWithContext<list<array{teamName: string, postTradeRoster: int, overLimit: bool}>>
     */
    public function validateRosterLimitsForParties(array $partyRosterDeltas): ValidationResultWithContext;

    /**
     * Get cash considerations for current season based on phase
     *
     * Determines which year's cash values to use for cap calculations
     * based on the current season phase. During offseason phases
     * (Playoffs, Draft, Free Agency), uses next year's values.
     *
     * @param array<int, int> $userSendsCash Cash sent by user team (indexed 1-6)
     * @param array<int, int> $partnerSendsCash Cash sent by partner team (indexed 1-6)
     * @return array{cashSentToThem: int, cashSentToMe: int} Cash considerations:
     *         - 'cashSentToThem': int - Cash user sends this "effective" season
     *         - 'cashSentToMe': int - Cash partner sends this "effective" season
     *
     * IMPORTANT BEHAVIORS:
     *  - During Playoffs/Draft/Free Agency: Uses index [2] (next year)
     *  - During Regular Season: Uses index [1] (current year)
     *  - Missing values default to 0
     */
    public function getCurrentSeasonCashConsiderations(array $userSendsCash, array $partnerSendsCash): array;
}
