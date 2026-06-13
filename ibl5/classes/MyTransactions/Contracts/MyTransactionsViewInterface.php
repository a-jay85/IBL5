<?php

declare(strict_types=1);

namespace MyTransactions\Contracts;

/**
 * View interface for the My Team Transactions module.
 *
 * Renders the GM's own team ledger plus outstanding trade offers and FA bids.
 *
 * @phpstan-import-type MyTransactionsPageData from MyTransactionsServiceInterface
 */
interface MyTransactionsViewInterface
{
    /**
     * Render the complete My Team Transactions page.
     *
     * @param MyTransactionsPageData $data Page data assembled by the service
     * @return string HTML output
     */
    public function render(array $data): string;
}
