<?php

declare(strict_types=1);

namespace TransactionHistory\Contracts;

/**
 * Repository interface for Transaction History module.
 *
 * Provides methods to query transaction data from the nuke_stories table.
 */
interface TransactionHistoryRepositoryInterface
{
    /**
     * Get distinct years that have transaction records.
     *
     * @return array<int, int> Years in descending order
     */
    public function getAvailableYears(): array;

    /**
     * Get transactions matching the given filters.
     *
     * @param int|null $categoryId Category ID filter (null = all categories)
     * @param int|null $year Year filter (null = all years)
     * @param int|null $month Month filter (null = all months)
     * @return array<int, array{sid: string, catid: string, title: string, time: string}> Transaction rows
     */
    public function getTransactions(?int $categoryId, ?int $year, ?int $month): array;
}
