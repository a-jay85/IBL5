<?php

declare(strict_types=1);

namespace Trading\Contracts;

/**
 * TradingRepositoryInterface - Contract for core trade offer CRUD database operations
 *
 * Defines methods for accessing and modifying trade offer data in the database.
 * All implementations must use prepared statements for SQL injection protection.
 *
 * Cash transaction methods are in TradeCashRepositoryInterface.
 * Queue/execution methods are in TradeExecutionRepositoryInterface.
 *
 * @phpstan-import-type PlayerRow from \Services\CommonMysqliRepository
 *
 * @phpstan-type TradeValidationRow array{ordinal: ?int, cy: ?int}
 * @phpstan-type TeamNameRow array{team_name: string}
 * @phpstan-type TeamWithCityRow array{teamid: int, team_name: string, team_city: string, color1: string, color2: string}
 * @phpstan-type TradingPlayerRow array{pos: string, name: string, pid: int, ordinal: ?int, cy: ?int, cy1: ?int, cy2: ?int, cy3: ?int, cy4: ?int, cy5: ?int, cy6: ?int}
 * @phpstan-type TradeInfoRow array{tradeofferid: int, itemid: int, itemtype: string, trade_from: string, trade_to: string, approval: string, created_at: string, updated_at: string}
 * @phpstan-type DraftPickRow array{pickid: int, ownerofpick: string, teampick: string, year: string, round: string, notes: ?string, created_at: string, updated_at: string}
 * @phpstan-type TradingDraftPickRow array{pickid: int, ownerofpick: string, teampick: string, teampick_id: int, year: string, round: string, notes: ?string, created_at: string, updated_at: string}
 */
interface TradingRepositoryInterface
{
    /**
     * Get player data for trade validation
     *
     * @param int $playerId Player ID
     * @return TradeValidationRow|null Player data with 'ordinal' and 'cy' fields, or null if not found
     */
    public function getPlayerForTradeValidation(int $playerId): ?array;

    /**
     * Get all teams for UI display
     *
     * @return list<TeamNameRow> List of teams with 'team_name' field
     */
    public function getAllTeams(): array;

    /**
     * Get players involved in a trade
     *
     * @param string $teamName Team name
     * @param int $row Row number
     * @return list<array<string, mixed>> Player data from ibl_trade_players
     */
    public function getTradePlayers(string $teamName, int $row): array;

    /**
     * Get draft picks involved in a trade
     *
     * @param string $teamName Team name
     * @param int $row Row number
     * @return list<array<string, mixed>> Draft pick data from ibl_trade_picks
     */
    public function getTradePicks(string $teamName, int $row): array;

    /**
     * Update player's team after trade
     *
     * @param int $playerId Player ID
     * @param int $newTeamId New team ID
     * @return int Number of rows affected
     */
    public function updatePlayerTeam(int $playerId, int $newTeamId): int;

    /**
     * Update draft pick ownership after trade
     *
     * @param int $year Draft year
     * @param int $pick Draft pick number
     * @param string $newOwner New owner team name
     * @return int Number of rows affected
     */
    public function updateDraftPickOwner(int $year, int $pick, string $newOwner): int;

    /**
     * Check if a player ID exists in trade players table
     *
     * @param int $playerId Player ID
     * @return bool True if exists
     */
    public function playerExistsInTrade(int $playerId): bool;

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
     * Get draft pick by pick ID
     *
     * @param int $pickId Pick ID
     * @return DraftPickRow|null Pick data with year, teampick, round, notes fields, or null if not found
     */
    public function getDraftPickById(int $pickId): ?array;

    /**
     * Get player by player ID
     *
     * @param int $playerId Player ID
     * @return PlayerRow|null Full player data, or null if not found
     */
    public function getPlayerById(int $playerId): ?array;

    /**
     * Get multiple players by their IDs in a single query
     *
     * @param list<int> $playerIds Player IDs to look up
     * @return array<int, PlayerRow> Player rows keyed by pid
     */
    public function getPlayersByIds(array $playerIds): array;

    /**
     * Get multiple draft picks by their IDs in a single query
     *
     * @param list<int> $pickIds Pick IDs to look up
     * @return array<int, DraftPickRow> Pick rows keyed by pickid
     */
    public function getDraftPicksByIds(array $pickIds): array;

    /**
     * Check if a player ID exists in the database
     *
     * @param int $playerId Player ID to check
     * @return bool True if player exists, false otherwise
     */
    public function playerIdExists(int $playerId): bool;

    /**
     * Update draft pick owner
     *
     * @param int $pickId Pick ID
     * @param string $newOwner New owner team name
     * @param int $newOwnerId New owner team ID
     * @return int Number of rows affected
     */
    public function updateDraftPickOwnerById(int $pickId, string $newOwner, int $newOwnerId): int;

    /**
     * Delete trade info by offer ID
     *
     * @param int $offerId Trade offer ID
     * @return int Number of rows affected
     */
    public function deleteTradeInfoByOfferId(int $offerId): int;

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
     * Get the last inserted ID
     *
     * @return int Last insert ID
     */
    public function getLastInsertId(): int;

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
     * Get team players eligible for trading display
     *
     * Returns active (non-retired) players for a team, ordered by ordinal.
     * Excludes buyout/cash placeholder records whose names start with '|'.
     * Includes position, name, contract year data needed by trade form.
     *
     * @param int $teamId Team ID
     * @return list<TradingPlayerRow> Player rows
     */
    public function getTeamPlayersForTrading(int $teamId): array;

    /**
     * Get team draft picks for trading display
     *
     * Returns all draft picks owned by a team, ordered by year and round.
     *
     * @param int $teamId Team ID (owner_tid value)
     * @return list<TradingDraftPickRow> Draft pick rows with teampick team ID
     */
    public function getTeamDraftPicksForTrading(int $teamId): array;

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
     * Delete a complete trade offer (info rows + cash rows + parent row)
     *
     * Removes all trade_info, trade_cash, and trade_offers records for a given offer ID.
     * Used when rejecting a trade offer.
     *
     * @param int $offerId Trade offer ID
     * @return void
     */
    public function deleteTradeOffer(int $offerId): void;

    /**
     * Count active roster players for a team
     *
     * Excludes retired players, cash placeholders (ordinal >= 100000),
     * and buyout/cash records whose names start with '|'.
     *
     * During the offseason (Playoffs/Draft/Free Agency), also excludes players
     * whose contracts have expired (next-year salary is $0), since these players
     * are effectively free agents even though they remain assigned to the team.
     *
     * @param int $teamId Team ID
     * @param bool $isOffseason Whether to apply offseason contract expiry filtering
     * @return int Number of active players on the team's roster
     */
    public function getTeamPlayerCount(int $teamId, bool $isOffseason = false): int;

    /**
     * Get all teams with city, name, colors and ID for trading UI
     *
     * @return list<TeamWithCityRow> Team rows ordered by city
     */
    public function getAllTeamsWithCity(): array;
}
