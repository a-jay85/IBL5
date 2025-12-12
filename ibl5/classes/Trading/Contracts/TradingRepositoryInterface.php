<?php

declare(strict_types=1);

namespace Trading\Contracts;

/**
 * TradingRepositoryInterface - Contract for trading database operations
 * 
 * Defines methods for accessing and modifying trade-related data in the database.
 * All implementations must use prepared statements for SQL injection protection.
 */
interface TradingRepositoryInterface
{
    /**
     * Get player data for trade validation
     * 
     * @param int $playerId Player ID
     * @return array|null Player data with 'ordinal' and 'cy' fields, or null if not found
     */
    public function getPlayerForTradeValidation(int $playerId): ?array;

    /**
     * Get all teams for UI display
     * 
     * @return array<array> List of teams with 'team_name' field
     */
    public function getAllTeams(): array;

    /**
     * Get trade rows from trade info table
     * 
     * @return array<array> Trade rows
     */
    public function getTradeRows(): array;

    /**
     * Get cash transaction details for a specific team and row
     * 
     * @param string $teamName Team name
     * @param int $row Row number
     * @return array|null Cash details or null if not found
     */
    public function getCashDetails(string $teamName, int $row): ?array;

    /**
     * Get players involved in a trade
     * 
     * @param string $teamName Team name
     * @param int $row Row number
     * @return array<array> Player data
     */
    public function getTradePlayers(string $teamName, int $row): array;

    /**
     * Get draft picks involved in a trade
     * 
     * @param string $teamName Team name
     * @param int $row Row number
     * @return array<array> Draft pick data
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
     * @param array $data Cash transaction data with keys: teamname, year1-6, row
     * @return int Number of rows affected
     */
    public function insertPositiveCashTransaction(array $data): int;

    /**
     * Insert negative cash transaction (team sending cash)
     * 
     * @param array $data Cash transaction data with keys: teamname, year1-6, row
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
     * @return array<array> Trade items with itemid, itemtype, from, to fields
     */
    public function getTradesByOfferId(int $offerId): array;

    /**
     * Get cash transaction by offer ID and sending team
     * 
     * @param int $offerId Trade offer ID
     * @param string $sendingTeam Sending team name
     * @return array|null Cash details with cy1-cy6 fields, or null if not found
     */
    public function getCashTransactionByOffer(int $offerId, string $sendingTeam): ?array;

    /**
     * Get draft pick by pick ID
     * 
     * @param int $pickId Pick ID
     * @return array|null Pick data with year, teampick, round, notes fields, or null if not found
     */
    public function getDraftPickById(int $pickId): ?array;

    /**
     * Get player by player ID
     * 
     * @param int $playerId Player ID
     * @return array|null Player data with pos, name fields, or null if not found
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
     * @param string $query SQL query to execute later
     * @param string $tradeLine Trade description text
     * @return int Number of rows affected
     */
    public function insertTradeQueue(string $query, string $tradeLine): int;

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
}
