<?php

declare(strict_types=1);

namespace Trading\Contracts;

/**
 * TradeCashRepositoryInterface - Contract for cash transaction database operations
 *
 * Defines methods for accessing and modifying cash-related trade data.
 * Extracted from the original TradingRepositoryInterface to follow single-responsibility principle.
 *
 * @phpstan-type TradeCashRow array{tradeOfferID: int, sendingTeam: string, receivingTeam: string, salary_yr1: ?int, salary_yr2: ?int, salary_yr3: ?int, salary_yr4: ?int, salary_yr5: ?int, salary_yr6: ?int}
 */
interface TradeCashRepositoryInterface
{
    /**
     * Get cash transaction by offer ID and sending team
     *
     * @param int $offerId Trade offer ID
     * @param string $sendingTeam Sending team name
     * @return TradeCashRow|null Cash details with salary_yr1-salary_yr6 fields, or null if not found
     */
    public function getCashTransactionByOffer(int $offerId, string $sendingTeam): ?array;

    /**
     * Insert cash trade offer into ibl_trade_cash
     *
     * @param int $tradeOfferId Trade offer ID
     * @param string $sendingTeam Sending team name
     * @param string $receivingTeam Receiving team name
     * @param int $salaryYr1 Salary year 1
     * @param int $salaryYr2 Salary year 2
     * @param int $salaryYr3 Salary year 3
     * @param int $salaryYr4 Salary year 4
     * @param int $salaryYr5 Salary year 5
     * @param int $salaryYr6 Salary year 6
     * @return int Number of affected rows
     */
    public function insertCashTradeOffer(int $tradeOfferId, string $sendingTeam, string $receivingTeam, int $salaryYr1, int $salaryYr2, int $salaryYr3, int $salaryYr4, int $salaryYr5, int $salaryYr6): int;

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
