<?php

declare(strict_types=1);

namespace Trading\Contracts;

/**
 * TradingRepositoryInterface - Contract for trading database operations
 *
 * Defines methods for accessing and modifying trade-related data in the database.
 * All implementations must use prepared statements for SQL injection protection.
 *
 * @phpstan-import-type PlayerRow from \Services\CommonMysqliRepository
 *
 * @phpstan-type TradeValidationRow array{ordinal: ?int, cy: ?int}
 * @phpstan-type TeamNameRow array{team_name: string}
 * @phpstan-type TeamWithCityRow array{teamid: int, team_name: string, team_city: string, color1: string, color2: string}
 * @phpstan-type TradingPlayerRow array{pos: string, name: string, pid: int, ordinal: ?int, cy: ?int, cy1: ?int, cy2: ?int, cy3: ?int, cy4: ?int, cy5: ?int, cy6: ?int}
 * @phpstan-type TradeInfoRow array{tradeofferid: int, itemid: int, itemtype: string, from: string, to: string, approval: string, created_at: string, updated_at: string}
 * @phpstan-type TradeCashRow array{tradeOfferID: int, sendingTeam: string, receivingTeam: string, cy1: ?int, cy2: ?int, cy3: ?int, cy4: ?int, cy5: ?int, cy6: ?int}
 * @phpstan-type DraftPickRow array{pickid: int, ownerofpick: string, teampick: string, year: string, round: string, notes: ?string, created_at: string, updated_at: string}
 * @phpstan-type CashTransactionData array{teamname: string, year1: int, year2: int, year3: int, year4: int, year5: int, year6: int, row: int}
 * @phpstan-type CashPlayerData array{ordinal: int, pid: int, name: string, tid: int, teamname: string, exp: int, cy: int, cyt: string, cy1: int, cy2: int, cy3: int, cy4: int, cy5: int, cy6: int, retired: int}
 * @phpstan-type TradeAutocounterRow array{counter: int}
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
     * Get trade rows from trade info table
     *
     * @return list<TradeInfoRow> Trade rows
     */
    public function getTradeRows(): array;

    /**
     * Get cash transaction details for a specific team and row
     *
     * @param string $teamName Team name
     * @param int $row Row number
     * @return TradeCashRow|null Cash details or null if not found
     */
    public function getCashDetails(string $teamName, int $row): ?array;

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
     * @param string $newTeamName New team name
     * @param int $newTeamId New team ID
     * @return int Number of rows affected
     */
    public function updatePlayerTeam(int $playerId, string $newTeamName, int $newTeamId): int;

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
     * Clear all trade info
     * 
     * @return int Number of rows affected
     */
    public function clearTradeInfo(): int;

    /**
     * Clear all trade cash data
     * 
     * @return int Number of rows affected
     */
    public function clearTradeCash(): int;

    /**
     * Check if a player ID exists in trade players table
     * 
     * @param int $playerId Player ID
     * @return bool True if exists
     */
    public function playerExistsInTrade(int $playerId): bool;

    /**
     * Insert positive cash transaction (team receiving cash)
     *
     * @param CashTransactionData $data Cash transaction data with keys: teamname, year1-6, row
     * @return int Number of rows affected
     */
    public function insertPositiveCashTransaction(array $data): int;

    /**
     * Insert negative cash transaction (team sending cash)
     *
     * @param CashTransactionData $data Cash transaction data with keys: teamname, year1-6, row
     * @return int Number of rows affected
     */
    public function insertNegativeCashTransaction(array $data): int;

    /**
     * Delete cash transaction for a team and row
     * 
     * @param string $teamName Team name
     * @param int $row Row number
     * @return int Number of rows affected
     */
    public function deleteCashTransaction(string $teamName, int $row): int;

    /**
     * Insert a trade item (player, pick, or cash consideration)
     *
     * @param int $tradeOfferId Trade offer ID
     * @param int $itemId Item ID (player pid, pick pickid, or composite for cash)
     * @param int|string $itemType Item type (1=player, 0=pick, 'cash'=cash)
     * @param string $fromTeam Offering team name
     * @param string $toTeam Receiving team name
     * @param string $approvalTeam Team that must approve (typically listening team)
     * @return int Number of rows affected
     */
    public function insertTradeItem(int $tradeOfferId, int $itemId, $itemType, string $fromTeam, string $toTeam, string $approvalTeam): int;

    /**
     * Get trade items by offer ID
     *
     * @param int $offerId Trade offer ID
     * @return list<TradeInfoRow> Trade items with itemid, itemtype, from, to fields
     */
    public function getTradesByOfferId(int $offerId): array;

    /**
     * Get cash transaction by offer ID and sending team
     *
     * @param int $offerId Trade offer ID
     * @param string $sendingTeam Sending team name
     * @return TradeCashRow|null Cash details with cy1-cy6 fields, or null if not found
     */
    public function getCashTransactionByOffer(int $offerId, string $sendingTeam): ?array;

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
     * @return int Number of rows affected
     */
    public function updateDraftPickOwnerById(int $pickId, string $newOwner): int;

    /**
     * Insert trade into queue for deferred execution
     *
     * Stores structured data for safe execution via prepared statements.
     *
     * @param string $operationType Type of operation ('player_transfer' or 'pick_transfer')
     * @param array<string, int|string> $params Operation parameters (e.g., player_id, team_name, team_id)
     * @param string $tradeLine Trade description text
     * @return int Number of rows affected
     */
    public function insertTradeQueue(string $operationType, array $params, string $tradeLine): int;

    /**
     * Get all queued trade operations
     *
     * @return list<array{id: int, operation_type: string, params: string, tradeline: string}> Queue entries
     */
    public function getQueuedTrades(): array;

    /**
     * Execute a queued player transfer
     *
     * @param int $playerId Player ID
     * @param string $teamName New team name
     * @param int $teamId New team ID
     * @return int Number of affected rows
     */
    public function executeQueuedPlayerTransfer(int $playerId, string $teamName, int $teamId): int;

    /**
     * Execute a queued pick transfer
     *
     * @param int $pickId Pick ID
     * @param string $newOwner New owner team name
     * @return int Number of affected rows
     */
    public function executeQueuedPickTransfer(int $pickId, string $newOwner): int;

    /**
     * Delete a queued trade entry by ID
     *
     * @param int $queueId Queue entry ID
     * @return int Number of affected rows
     */
    public function deleteQueuedTrade(int $queueId): int;

    /**
     * Clear all queued trades (used at start of preseason)
     *
     * @return int Number of affected rows
     */
    public function clearTradeQueue(): int;

    /**
     * Delete trade info by offer ID
     * 
     * @param int $offerId Trade offer ID
     * @return int Number of rows affected
     */
    public function deleteTradeInfoByOfferId(int $offerId): int;

    /**
     * Delete trade cash by offer ID
     * 
     * @param int $offerId Trade offer ID
     * @return int Number of rows affected
     */
    public function deleteTradeCashByOfferId(int $offerId): int;

    /**
     * Get the last inserted ID
     *
     * @return int Last insert ID
     */
    public function getLastInsertId(): int;

    /**
     * Get team players eligible for trading display
     *
     * Returns active (non-retired) players for a team, ordered by ordinal.
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
     * @param string $teamName Team name (ownerofpick value)
     * @return list<DraftPickRow> Draft pick rows
     */
    public function getTeamDraftPicksForTrading(string $teamName): array;

    /**
     * Get all trade offers ordered by offer ID
     *
     * Returns all rows from ibl_trade_info for the trade review page.
     *
     * @return list<TradeInfoRow> Trade info rows ordered by tradeofferid ASC
     */
    public function getAllTradeOffers(): array;

    /**
     * Delete a complete trade offer (info rows + cash rows)
     *
     * Removes all trade_info and trade_cash records for a given offer ID.
     * Used when rejecting a trade offer.
     *
     * @param int $offerId Trade offer ID
     * @return void
     */
    public function deleteTradeOffer(int $offerId): void;

    /**
     * Count active roster players for a team (excludes retired and cash placeholders)
     *
     * @param string $teamName Team name
     * @return int Number of active players on the team's roster
     */
    public function getTeamPlayerCount(string $teamName): int;

    /**
     * Get all teams with city, name, colors and ID for trading UI
     *
     * @return list<TeamWithCityRow> Team rows ordered by city
     */
    public function getAllTeamsWithCity(): array;
}
