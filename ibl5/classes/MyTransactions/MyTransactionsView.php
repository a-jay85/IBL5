<?php

declare(strict_types=1);

namespace MyTransactions;

use MyTransactions\Contracts\MyTransactionsViewInterface;
use Security\HtmlSanitizer;
use TransactionHistory\TransactionHistoryService;

/**
 * Renders the My Team Transactions page.
 *
 * Reuses the IBL design system and mirrors TransactionHistoryView's ledger table
 * (.ibl-data-table, .txn-badge--{catid}). Read-only — no forms, no CSRF surface.
 *
 * @phpstan-import-type MyTransactionsPageData from \MyTransactions\Contracts\MyTransactionsServiceInterface
 * @phpstan-import-type PendingTrade from \MyTransactions\Contracts\MyTransactionsServiceInterface
 * @phpstan-import-type TransactionRow from \TransactionHistory\Contracts\TransactionHistoryViewInterface
 * @phpstan-import-type TeamOfferRow from \FreeAgency\Contracts\FreeAgencyRepositoryInterface
 *
 * @see MyTransactionsViewInterface
 */
class MyTransactionsView implements MyTransactionsViewInterface
{
    /**
     * @see MyTransactionsViewInterface::render()
     *
     * @param MyTransactionsPageData $data
     */
    public function render(array $data): string
    {
        $output = '<h2 class="ibl-title">My Team Transactions</h2>';

        if ($data['hasTeam'] !== true) {
            return $output . $this->renderEmptyState('You are not assigned a team.');
        }

        $output .= $this->renderPendingTrades($data['pendingTrades']);
        $output .= $this->renderPendingFaBids($data['pendingFaBids']);
        $output .= $this->renderLedger($data['transactions']);

        return $output;
    }

    /**
     * @param list<PendingTrade> $pendingTrades
     */
    private function renderPendingTrades(array $pendingTrades): string
    {
        $heading = '<h3 class="ibl-title">Outstanding Trade Offers</h3>';
        if ($pendingTrades === []) {
            return $heading . $this->renderEmptyState('No outstanding trade offers.');
        }

        ob_start();
        ?>
<table class="ibl-data-table responsive-table">
    <thead>
        <tr>
            <th>Trade With</th>
            <th>Status</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($pendingTrades as $trade): ?>
            <tr>
                <td><a href="modules.php?name=Trading&amp;op=reviewtrade"><?= HtmlSanitizer::e($trade['oppositeTeam']) ?></a></td>
                <td><?= HtmlSanitizer::e($trade['approval']) ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
        <?php
        return $heading . (string) ob_get_clean();
    }

    /**
     * @param list<TeamOfferRow> $pendingFaBids
     */
    private function renderPendingFaBids(array $pendingFaBids): string
    {
        $heading = '<h3 class="ibl-title">Outstanding Free-Agent Bids</h3>';
        if ($pendingFaBids === []) {
            return $heading . $this->renderEmptyState('No outstanding free-agent bids.');
        }

        ob_start();
        ?>
<table class="ibl-data-table responsive-table">
    <thead>
        <tr>
            <th>Player</th>
            <th>Offer (per year)</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($pendingFaBids as $bid): ?>
            <?php
            $years = [$bid['offer1'], $bid['offer2'], $bid['offer3'], $bid['offer4'], $bid['offer5'], $bid['offer6']];
            $years = array_filter($years, static fn (int $v): bool => $v > 0);
            $offerLabel = $years === [] ? '—' : implode(' / ', array_map('strval', $years));
            ?>
            <tr>
                <td><?= HtmlSanitizer::e($bid['name']) ?></td>
                <td><?= HtmlSanitizer::e($offerLabel) ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
        <?php
        return $heading . (string) ob_get_clean();
    }

    /**
     * Render the transaction ledger, mirroring TransactionHistoryView's table.
     *
     * @param array<int, TransactionRow> $transactions
     */
    private function renderLedger(array $transactions): string
    {
        $categories = TransactionHistoryService::CATEGORIES;

        $heading = '<h3 class="ibl-title">Transaction History</h3>';
        if ($transactions === []) {
            return $heading . $this->renderEmptyState('No transactions found for your team.');
        }

        ob_start();
        ?>
<div class="table-scroll-wrapper">
<div class="table-scroll-container" tabindex="0" role="region" aria-label="My team transaction history">
<table class="ibl-data-table responsive-table txn-table">
    <thead>
        <tr>
            <th>Date</th>
            <th>Type</th>
            <th>Transaction</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($transactions as $row): ?>
            <?php
            $catId = (int) $row['catid'];
            $catName = $categories[$catId] ?? 'Unknown';
            $timestamp = strtotime($row['time']);
            $date = date('M j, Y', $timestamp !== false ? $timestamp : 0);
            ?>
            <tr>
                <td class="date-cell"><?= HtmlSanitizer::e($date) ?></td>
                <td><span class="txn-badge txn-badge--<?= HtmlSanitizer::e($catId) ?>"><?= HtmlSanitizer::e($catName) ?></span></td>
                <td><a href="modules.php?name=News&amp;file=article&amp;sid=<?= (int) $row['sid'] ?>"><?= HtmlSanitizer::e($row['title']) ?></a></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
</div></div>
        <?php
        return $heading . (string) ob_get_clean();
    }

    private function renderEmptyState(string $message): string
    {
        return '<div class="ibl-empty-state">
        <p class="ibl-empty-state__text">' . HtmlSanitizer::e($message) . '</p>
    </div>';
    }
}
