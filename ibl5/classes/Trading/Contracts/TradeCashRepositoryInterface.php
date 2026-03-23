<?php

declare(strict_types=1);

namespace Trading\Contracts;

/**
 * TradeCashRepositoryInterface - Contract for cash transaction database operations
 *
 * Defines methods for accessing and modifying cash-related trade data.
 * Extracted from the original TradingRepositoryInterface to follow single-responsibility principle.
 *
 * @phpstan-type TradeCashRow array{tradeOfferID: int, sendingTeam: string, receivingTeam: string, cy1: ?int, cy2: ?int, cy3: ?int, cy4: ?int, cy5: ?int, cy6: ?int}
 * @phpstan-type CashPlayerData array{ordinal: int, pid: int, name: string, tid: int, exp: int, cy: int, cyt: int, cy1: int, cy2: int, cy3: int, cy4: int, cy5: int, cy6: int, retired: int}
 * @phpstan-type TradingPlayerRow array{pos: string, name: string, pid: int, ordinal: ?int, cy: ?int, cy1: ?int, cy2: ?int, cy3: ?int, cy4: ?int, cy5: ?int, cy6: ?int}
 */
interface TradeCashRepositoryInterface
{
    /**
     * Get cash transaction by offer ID and sending team
     *
     * @param int $offerId Trade offer ID
     * @param string $sendingTeam Sending team name
     * @return TradeCashRow|null Cash details with cy1-cy6 fields, or null if not found
     */
    public function getCashTransactionByOffer(int $offerId, string $sendingTeam): ?array;

    /**
     * Insert a cash player record (positive or negative cash transaction)
     *
     * @param CashPlayerData $data Associative array with keys: ordinal, pid, name, tid, exp, cy, cyt, cy1-cy6, retired
     * @return int Number of affected rows
     */
    public function insertCashPlayerRecord(array $data): int;

    /**
     * Insert cash trade offer into ibl_trade_cash
     *
     * @param int $tradeOfferId Trade offer ID
     * @param string $sendingTeam Sending team name
     * @param string $receivingTeam Receiving team name
     * @param int $cy1 Cash year 1
     * @param int $cy2 Cash year 2
     * @param int $cy3 Cash year 3
     * @param int $cy4 Cash year 4
     * @param int $cy5 Cash year 5
     * @param int $cy6 Cash year 6
     * @return int Number of affected rows
     */
    public function insertCashTradeOffer(int $tradeOfferId, string $sendingTeam, string $receivingTeam, int $cy1, int $cy2, int $cy3, int $cy4, int $cy5, int $cy6): int;

    /**
     * Get cash placeholder records for a team's salary calculation
     *
     * Returns cash transaction records (names starting with '|') that affect
     * a team's salary cap totals but should not appear in the trading roster.
     *
     * @param int $teamId Team ID
     * @return list<TradingPlayerRow> Cash placeholder rows with contract year data
     */
    public function getTeamCashRecordsForSalary(int $teamId): array;

    /**
     * Clear all trade cash data
     *
     * @return int Number of rows affected
     */
    public function clearTradeCash(): int;

    /**
     * Get cash transactions for multiple offer IDs in a single query
     *
     * @param list<int> $offerIds Trade offer IDs to look up
     * @return array<string, TradeCashRow> Cash rows keyed by "{offerId}:{sendingTeam}"
     */
    public function getCashTransactionsByOfferIds(array $offerIds): array;

    /**
     * Delete trade cash by offer ID
     *
     * @param int $offerId Trade offer ID
     * @return int Number of rows affected
     */
    public function deleteTradeCashByOfferId(int $offerId): int;
}
