<?php

declare(strict_types=1);

namespace Trading\Contracts;

/**
 * TradeOfferRepositoryInterface - Contract for trade offer CRUD operations
 *
 * Handles creation, retrieval, and deletion of trade offers and their items.
 * Extracted from TradingRepositoryInterface to follow single-responsibility principle.
 *
 * @phpstan-type TradeInfoRow array{tradeofferid: int, itemid: int, itemtype: string, trade_from: string, trade_to: string, approval: string, created_at: string, updated_at: string}
 */
interface TradeOfferRepositoryInterface
{
    /**
     * Generate the next trade offer ID using AUTO_INCREMENT
     *
     * Inserts a row into ibl_trade_offers and returns the generated ID.
     * This is atomic and race-condition-free.
     *
     * @return int New trade offer ID
     * @throws \RuntimeException If ID generation fails
     */
    public function generateNextTradeOfferId(): int;

    /**
     * Insert a trade item (player, pick, or cash consideration)
     *
     * @param int $tradeOfferId Trade offer ID
     * @param int $itemId Item ID (player pid, pick pickid, or composite for cash)
     * @param \Trading\TradeItemType $itemType Item type enum
     * @param string $fromTeam Offering team name
     * @param string $toTeam Receiving team name
     * @param string $approvalTeam Team that must approve (typically listening team)
     * @return int Number of rows affected
     */
    public function insertTradeItem(int $tradeOfferId, int $itemId, \Trading\TradeItemType $itemType, string $fromTeam, string $toTeam, string $approvalTeam): int;

    /**
     * Get trade items by offer ID
     *
     * @param int $offerId Trade offer ID
     * @return list<TradeInfoRow> Trade items with itemid, itemtype, from, to fields
     */
    public function getTradesByOfferId(int $offerId): array;

    /**
     * Get trade items by offer ID with exclusive row-level lock
     *
     * Identical to getTradesByOfferId() but appends FOR UPDATE to acquire
     * an exclusive lock within the current transaction. Used by TradeProcessor
     * to prevent double-processing of trades via TOCTOU race conditions.
     *
     * Must be called within an active transaction (BEGIN ... COMMIT/ROLLBACK).
     *
     * @param int $offerId Trade offer ID
     * @return list<TradeInfoRow> Trade items with itemid, itemtype, from, to fields
     */
    public function getTradesByOfferIdForUpdate(int $offerId): array;

    /**
     * Get all pending trade offers ordered by offer ID
     *
     * Returns pending (non-completed) rows from ibl_trade_info for the trade
     * review page. Excludes rows with approval='completed' which are preserved
     * only for TRN export.
     *
     * @return list<TradeInfoRow> Trade info rows ordered by tradeofferid ASC
     */
    public function getAllTradeOffers(): array;

    /**
     * Mark trade info rows as completed for a given offer ID.
     *
     * Sets the approval column to 'completed' so the rows are preserved
     * for TRN export while no longer appearing as pending trades.
     *
     * @param int $offerId Trade offer ID
     * @return int Number of rows affected
     */
    public function markTradeInfoCompleted(int $offerId): int;

    /**
     * Delete trade info by offer ID
     *
     * @param int $offerId Trade offer ID
     * @return int Number of rows affected
     */
    public function deleteTradeInfoByOfferId(int $offerId): int;

    /**
     * Delete a trade offer parent row by ID
     *
     * Removes the parent record from ibl_trade_offers.
     * Called by deleteTradeOffer() after child rows are removed.
     *
     * @param int $offerId Trade offer ID
     * @return int Number of rows affected
     */
    public function deleteTradeOfferById(int $offerId): int;

    /**
     * Delete a complete trade offer (info rows + cash rows + parent row)
     *
     * Removes all trade_info, trade_cash, and trade_offers records for a given offer ID.
     * Used when rejecting a trade offer.
     *
     * @param int $offerId Trade offer ID
     * @return void
     */
    public function deleteTradeOffer(int $offerId): void;
}
