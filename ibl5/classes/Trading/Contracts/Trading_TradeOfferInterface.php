<?php

/**
 * Trading_TradeOfferInterface - Trade offer creation and management
 *
 * Handles the creation of new trade offers including validation,
 * salary cap checking, database insertion, and notifications.
 */
interface Trading_TradeOfferInterface
{
    /**
     * Create a new trade offer
     *
     * Validates trade data, checks salary caps, inserts trade records,
     * and sends notifications to the receiving team.
     *
     * @param array $tradeData Trade data from form submission containing:
     *                         - 'offeringTeam' (string): Name of team making offer
     *                         - 'listeningTeam' (string): Name of team receiving offer
     *                         - 'switchCounter' (int): Index where partner team items begin
     *                         - 'fieldsCounter' (int): Total number of item fields
     *                         - 'check' (array): Checkbox states for each item
     *                         - 'index' (array): Item IDs (player PIDs or pick IDs)
     *                         - 'type' (array): Item types (0=pick, 1=player)
     *                         - 'contract' (array): Contract amounts for salary calc
     *                         - 'userSendsCash' (array): Cash amounts user sends (indexed 1-6)
     *                         - 'partnerSendsCash' (array): Cash amounts partner sends (indexed 1-6)
     * @return array Result with keys:
     *               - 'success' (bool): Whether offer was created successfully
     *               - 'error' (string|null): Error message if validation failed
     *               - 'errors' (array|null): Array of cap validation errors
     *               - 'capData' (array|null): Salary cap calculation details
     *               - 'tradeText' (string|null): Description of trade items on success
     *               - 'tradeOfferId' (int|null): ID of created offer on success
     *
     * **Behaviors:**
     * - Validates minimum cash amounts (100 per year minimum)
     * - Validates both teams stay under hard cap post-trade
     * - Creates ibl_trade_info records for each item
     * - Creates ibl_trade_cash records for cash considerations
     * - Sends Discord DM notification to receiving team
     * - Returns early with error details if validation fails
     */
    public function createTradeOffer($tradeData);
}
