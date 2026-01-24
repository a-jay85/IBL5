<?php

declare(strict_types=1);

namespace ContractList;

use ContractList\Contracts\ContractListViewInterface;
use Utilities\HtmlSanitizer;

/**
 * View class for rendering master contract list table.
 *
 * @see ContractListViewInterface
 */
class ContractListView implements ContractListViewInterface
{
    /**
     * @see ContractListViewInterface::render()
     */
    public function render(array $data): string
    {
        $output = $this->getStyleBlock();
        $output .= $this->renderTitle();
        $output .= $this->renderTableStart();
        $output .= $this->renderTableRows($data['contracts']);
        $output .= $this->renderCapTotals($data['capTotals']);
        $output .= $this->renderAvgCaps($data['avgCaps']);
        $output .= $this->renderTableEnd();

        return $output;
    }

    /**
     * Get the CSS styles for the contract list table.
     *
     * @return string CSS style block
     */
    private function getStyleBlock(): string
    {
        return '<style>
.contract-title {
    font-family: var(--font-display, \'Poppins\', -apple-system, BlinkMacSystemFont, sans-serif);
    font-size: 1.5rem;
    font-weight: 600;
    color: var(--navy-900, #0f172a);
    text-align: center;
    margin: 0 0 1.5rem 0;
}
.contract-table {
    font-family: var(--font-sans, \'Inter\', -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif);
    border-collapse: separate;
    border-spacing: 0;
    border: none;
    border-radius: var(--radius-lg, 0.5rem);
    overflow: hidden;
    box-shadow: var(--shadow-md, 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1));
    width: 100%;
    margin: 0 auto;
    font-size: 0.75rem;
}
.contract-table thead {
    background: linear-gradient(135deg, var(--navy-800, #1e293b), var(--navy-900, #0f172a));
}
.contract-table th {
    color: white;
    font-family: var(--font-display, \'Poppins\', -apple-system, BlinkMacSystemFont, sans-serif);
    font-weight: 600;
    font-size: 0.625rem;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    padding: 0.625rem 0.5rem;
    text-align: center;
}
.contract-table td {
    color: var(--gray-800, #1f2937);
    padding: 0.375rem 0.5rem;
    text-align: center;
}
.contract-table tbody tr {
    transition: background-color 150ms ease;
}
.contract-table tbody tr:nth-child(odd) {
    background-color: white;
}
.contract-table tbody tr:nth-child(even) {
    background-color: var(--gray-50, #f9fafb);
}
.contract-table tbody tr:hover {
    background-color: var(--gray-100, #f3f4f6);
}
.contract-table .divider {
    background-color: var(--navy-900, #0f172a);
    width: 3px;
    padding: 0;
}
.contract-table .totals-row {
    background-color: var(--accent-100, #ffedd5) !important;
    font-weight: 600;
}
.contract-table .totals-row:hover {
    background-color: var(--accent-200, #fed7aa) !important;
}
</style>';
    }

    /**
     * Render the page title.
     *
     * @return string HTML title
     */
    private function renderTitle(): string
    {
        return '<h2 class="contract-title">Master Contract List</h2>';
    }

    /**
     * Render the start of the table.
     *
     * @return string HTML table start
     */
    private function renderTableStart(): string
    {
        return '<table class="sortable contract-table">
            <thead>
                <tr>
                    <th>Pos</th>
                    <th colspan="3">Player</th>
                    <th>Bird</th>
                    <th>Year1</th>
                    <th>Year2</th>
                    <th>Year3</th>
                    <th>Year4</th>
                    <th>Year5</th>
                    <th>Year6</th>
                    <th class="divider"></th>
                    <th>Team</th>
                </tr>
            </thead>
            <tbody>';
    }

    /**
     * Render all contract rows.
     *
     * @param array $contracts Array of contract data
     * @return string HTML table rows
     */
    private function renderTableRows(array $contracts): string
    {
        $output = '';

        foreach ($contracts as $contract) {
            $name = HtmlSanitizer::safeHtmlOutput($contract['name'] ?? '');
            $pos = HtmlSanitizer::safeHtmlOutput($contract['pos'] ?? '');
            $teamname = HtmlSanitizer::safeHtmlOutput($contract['teamname'] ?? '');
            $bird = HtmlSanitizer::safeHtmlOutput($contract['bird'] ?? '');
            $con1 = (int) ($contract['con1'] ?? 0);
            $con2 = (int) ($contract['con2'] ?? 0);
            $con3 = (int) ($contract['con3'] ?? 0);
            $con4 = (int) ($contract['con4'] ?? 0);
            $con5 = (int) ($contract['con5'] ?? 0);
            $con6 = (int) ($contract['con6'] ?? 0);

            $output .= "<tr>
    <td>{$pos}</td>
    <td colspan=\"3\">{$name}</td>
    <td>{$bird}</td>
    <td>{$con1}</td>
    <td>{$con2}</td>
    <td>{$con3}</td>
    <td>{$con4}</td>
    <td>{$con5}</td>
    <td>{$con6}</td>
    <td class=\"divider\"></td>
    <td>{$teamname}</td>
</tr>";
        }

        return $output;
    }

    /**
     * Render cap totals row.
     *
     * @param array{cap1: float, cap2: float, cap3: float, cap4: float, cap5: float, cap6: float} $capTotals Cap totals
     * @return string HTML table row
     */
    private function renderCapTotals(array $capTotals): string
    {
        return sprintf(
            '<tr class="totals-row">
    <td></td>
    <td colspan="3">Cap Totals</td>
    <td></td>
    <td>%.2f</td>
    <td>%.2f</td>
    <td>%.2f</td>
    <td>%.2f</td>
    <td>%.2f</td>
    <td>%.2f</td>
    <td class="divider"></td>
    <td></td>
</tr>',
            $capTotals['cap1'],
            $capTotals['cap2'],
            $capTotals['cap3'],
            $capTotals['cap4'],
            $capTotals['cap5'],
            $capTotals['cap6']
        );
    }

    /**
     * Render average team cap row.
     *
     * @param array{acap1: float, acap2: float, acap3: float, acap4: float, acap5: float, acap6: float} $avgCaps Average caps
     * @return string HTML table row
     */
    private function renderAvgCaps(array $avgCaps): string
    {
        return sprintf(
            '<tr class="totals-row">
    <td></td>
    <td colspan="3">Average Team Cap</td>
    <td></td>
    <td>%.2f</td>
    <td>%.2f</td>
    <td>%.2f</td>
    <td>%.2f</td>
    <td>%.2f</td>
    <td>%.2f</td>
    <td class="divider"></td>
    <td></td>
</tr>',
            $avgCaps['acap1'],
            $avgCaps['acap2'],
            $avgCaps['acap3'],
            $avgCaps['acap4'],
            $avgCaps['acap5'],
            $avgCaps['acap6']
        );
    }

    /**
     * Render the end of the table.
     *
     * @return string HTML table end
     */
    private function renderTableEnd(): string
    {
        return '</tbody></table>';
    }
}
