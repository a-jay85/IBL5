<?php

declare(strict_types=1);

namespace TransactionHistory\Contracts;

/**
 * View interface for Transaction History module.
 *
 * Renders the transaction history page with filters and data table.
 *
 * @phpstan-type TransactionRow array{sid: string, catid: string, title: string, time: string}
 * @phpstan-type PageData array{transactions: array<int, TransactionRow>, categories: array<int, string>, availableYears: array<int, int>, monthNames: array<int, string>, selectedCategory: int, selectedYear: int, selectedMonth: int}
 */
interface TransactionHistoryViewInterface
{
    /**
     * Render the complete transaction history page.
     *
     * @param PageData $data Page data assembled by the service
     * @return string HTML output
     */
    public function render(array $data): string;
}
