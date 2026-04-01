<?php

declare(strict_types=1);

namespace Trading\Contracts;

/**
 * CashTransactionHandlerInterface - Cash transaction management for trades
 *
 * Handles creation and validation of cash transactions within trades,
 * inserting paired positive/negative entries into ibl_cash_considerations.
 */
interface CashTransactionHandlerInterface
{
    /**
     * Calculate contract total years based on cash year data.
     *
     * Determines how many years a cash consideration spans by checking
     * which years have non-zero amounts, starting from year 6 down to year 1.
     *
     * @param array<int, int> $cashYear Array of cash amounts indexed by year (1-6)
     * @return int Total contract years (1-6)
     */
    public function calculateContractTotalYears(array $cashYear): int;

    /**
     * Format cash trade text with per-year season labels.
     *
     * @param array<int, int> $cashYear Cash amounts keyed by year (1-6)
     * @param string $from Name of team sending the cash
     * @param string $to Name of team receiving the cash
     * @param int $seasonEndingYear Season ending year for computing season labels
     * @return string Concatenated per-year trade lines (HTML with <br> separators)
     */
    public static function formatCashTradeText(array $cashYear, string $from, string $to, int $seasonEndingYear): string;

    /**
     * Create cash consideration entries in ibl_cash_considerations.
     *
     * Inserts two records:
     * 1. Positive cash row for the offering team (cap hit)
     * 2. Negative cash row for the listening team (cap relief)
     *
     * @param string $offeringTeamName Name of team sending the cash
     * @param string $listeningTeamName Name of team receiving the cash
     * @param array<int, int> $cashYear Cash amounts indexed by year (1-6)
     * @param int $seasonEndingYear Season ending year for computing season labels
     * @param int|null $tradeOfferId Trade offer ID to link (NULL for manual entries)
     * @return array{success: bool, tradeLine: string}
     */
    public function createCashTransaction(string $offeringTeamName, string $listeningTeamName, array $cashYear, int $seasonEndingYear, ?int $tradeOfferId = null): array;

    /**
     * Insert cash trade data into ibl_trade_cash table (pending trade storage).
     *
     * @param int $tradeOfferId Trade offer ID to associate with
     * @param string $offeringTeamName Name of team sending the cash
     * @param string $listeningTeamName Name of team receiving the cash
     * @param array<int, int> $cashAmounts Cash amounts indexed by year (1-6)
     * @return bool True on successful insert
     */
    public function insertCashTradeData(int $tradeOfferId, string $offeringTeamName, string $listeningTeamName, array $cashAmounts): bool;

    /**
     * Check if any cash is being sent in the trade.
     *
     * @param array<int, int> $cashAmounts Cash amounts indexed by year (1-6)
     * @return bool True if any year has a non-zero amount
     */
    public function hasCashInTrade(array $cashAmounts): bool;
}
