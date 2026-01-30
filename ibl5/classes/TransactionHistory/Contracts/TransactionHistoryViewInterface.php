<?php

declare(strict_types=1);

namespace TransactionHistory\Contracts;

/**
 * View interface for Transaction History module.
 *
 * Renders the transaction history page with filters and data table.
 */
interface TransactionHistoryViewInterface
{
    /**
     * Render the complete transaction history page.
     *
     * @param array{
     *     transactions: array,
     *     categories: array<int, string>,
     *     availableYears: array<int, int>,
     *     monthNames: array<int, string>,
     *     selectedCategory: int,
     *     selectedYear: int,
     *     selectedMonth: int
     * } $data Page data assembled by the service
     * @return string HTML output
     */
    public function render(array $data): string;
}
