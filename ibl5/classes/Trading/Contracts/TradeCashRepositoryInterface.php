<?php

declare(strict_types=1);

namespace Trading\Contracts;

/**
 * TradeCashRepositoryInterface - Contract for cash transaction database operations
 *
 * Defines methods for accessing and modifying cash-related trade data.
 * Extracted from TradingRepositoryInterface to follow single-responsibility principle.
 *
 * @phpstan-type TradeCashRow array{tradeOfferID: int, sendingTeam: string, receivingTeam: string, cy1: ?int, cy2: ?int, cy3: ?int, cy4: ?int, cy5: ?int, cy6: ?int}
 * @phpstan-type CashTransactionData array{teamname: string, year1: int, year2: int, year3: int, year4: int, year5: int, year6: int, row: int}
 * @phpstan-type CashPlayerData array{ordinal: int, pid: int, name: string, tid: int, teamname: string, exp: int, cy: int, cyt: int, cy1: int, cy2: int, cy3: int, cy4: int, cy5: int, cy6: int, retired: int}
 * @phpstan-type TradingPlayerRow array{pos: string, name: string, pid: int, ordinal: ?int, cy: ?int, cy1: ?int, cy2: ?int, cy3: ?int, cy4: ?int, cy5: ?int, cy6: ?int}
 */
interface TradeCashRepositoryInterface
{
    /**
     * Get cash transaction details for a specific team and row
     *
     * @param string $teamName Team name
     * @param int $row Row number
     * @return TradeCashRow|null Cash details or null if not found
     */
    public function getCashDetails(string $teamName, int $row): ?array;

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
     * @param CashPlayerData $data Associative array with keys: ordinal, pid, name, tid, teamname, exp, cy, cyt, cy1-cy6, retired
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
     * Delete trade cash by offer ID
     *
     * @param int $offerId Trade offer ID
     * @return int Number of rows affected
     */
    public function deleteTradeCashByOfferId(int $offerId): int;
}
