<?php

declare(strict_types=1);

namespace TransactionHistory;

use TransactionHistory\Contracts\TransactionHistoryViewInterface;
use Utilities\HtmlSanitizer;

/**
 * View class for rendering the Transaction History page.
 *
 * Uses the IBL design system (.ibl-data-table, .ibl-filter-form, etc.)
 * with category-specific badge colors for transaction types.
 *
 * @see TransactionHistoryViewInterface
 */
class TransactionHistoryView implements TransactionHistoryViewInterface
{
    /**
     * @see TransactionHistoryViewInterface::render()
     */
    public function render(array $data): string
    {
        $output = $this->renderTitle();
        $output .= $this->renderFilterForm($data);

        if (count($data['transactions']) > 0) {
            $output .= '<div class="table-scroll-wrapper">';
            $output .= '<div class="table-scroll-container">';
            $output .= $this->renderTable($data['transactions'], $data['categories']);
            $output .= '</div></div>';
        } else {
            $output .= $this->renderEmptyState();
        }

        return $output;
    }

    private function renderTitle(): string
    {
        return '<h2 class="ibl-title">Transaction History</h2>';
    }

    /**
     * Render the filter form with Category, Year, and Month dropdowns.
     */
    private function renderFilterForm(array $data): string
    {
        $categories = $data['categories'];
        $availableYears = $data['availableYears'];
        $monthNames = $data['monthNames'];
        $selectedCategory = (int) $data['selectedCategory'];
        $selectedYear = (int) $data['selectedYear'];
        $selectedMonth = (int) $data['selectedMonth'];

        ob_start();
        ?>
<form method="get" action="modules.php" class="ibl-filter-form">
    <input type="hidden" name="name" value="Transaction_History">
    <div class="ibl-filter-form__row">
        <div class="ibl-filter-form__group">
            <label class="ibl-filter-form__label">Category:</label>
            <select name="cat">
                <option value="0">All Categories</option>
                <?php foreach ($categories as $catId => $catName): ?>
                    <option value="<?= (int) $catId ?>"<?= $selectedCategory === (int) $catId ? ' selected' : '' ?>><?= HtmlSanitizer::safeHtmlOutput($catName) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="ibl-filter-form__group">
            <label class="ibl-filter-form__label">Year:</label>
            <select name="year">
                <option value="0">All Years</option>
                <?php foreach ($availableYears as $year): ?>
                    <option value="<?= (int) $year ?>"<?= $selectedYear === (int) $year ? ' selected' : '' ?>><?= (int) $year ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="ibl-filter-form__group">
            <label class="ibl-filter-form__label">Month:</label>
            <select name="month">
                <option value="0">All Months</option>
                <?php foreach ($monthNames as $num => $name): ?>
                    <option value="<?= (int) $num ?>"<?= $selectedMonth === (int) $num ? ' selected' : '' ?>><?= HtmlSanitizer::safeHtmlOutput($name) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="ibl-filter-form__submit">Filter</button>
        <a href="modules.php?name=Transaction_History" class="ibl-btn ibl-btn--ghost ibl-btn--sm txn-reset">Reset</a>
    </div>
</form>
        <?php
        return ob_get_clean();
    }

    /**
     * Render the transactions data table.
     *
     * @param array $transactions Transaction rows from repository
     * @param array<int, string> $categories Category ID to label map
     */
    private function renderTable(array $transactions, array $categories): string
    {
        ob_start();
        ?>
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
            $date = date('M j, Y', strtotime($row['time']));
            ?>
            <tr>
                <td class="date-cell"><?= HtmlSanitizer::safeHtmlOutput($date) ?></td>
                <td><span class="txn-badge txn-badge--<?= $catId ?>"><?= HtmlSanitizer::safeHtmlOutput($catName) ?></span></td>
                <td><?= HtmlSanitizer::safeHtmlOutput($row['title']) ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
        <?php
        return ob_get_clean();
    }

    /**
     * Render empty state when no transactions match the filters.
     */
    private function renderEmptyState(): string
    {
        return '<div class="ibl-empty-state">
        <svg class="ibl-empty-state__icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="11" cy="11" r="8"/>
            <line x1="21" y1="21" x2="16.65" y2="16.65"/>
        </svg>
        <p class="ibl-empty-state__text">No transactions found for the selected filters.</p>
    </div>';
    }
}
