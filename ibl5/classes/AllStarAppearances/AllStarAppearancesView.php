<?php

declare(strict_types=1);

namespace AllStarAppearances;

use AllStarAppearances\Contracts\AllStarAppearancesViewInterface;
use Utilities\HtmlSanitizer;

/**
 * View class for rendering all-star appearances table.
 *
 * @see AllStarAppearancesViewInterface
 */
class AllStarAppearancesView implements AllStarAppearancesViewInterface
{
    /**
     * @see AllStarAppearancesViewInterface::render()
     */
    public function render(array $appearances): string
    {
        $output = $this->getStyleBlock();
        $output .= $this->renderTitle();
        $output .= $this->renderTableStart();
        $output .= $this->renderTableRows($appearances);
        $output .= $this->renderTableEnd();

        return $output;
    }

    /**
     * Get the CSS styles for the all-star appearances table.
     *
     * @return string CSS style block
     */
    private function getStyleBlock(): string
    {
        return '<style>
.allstar-title {
    font-family: var(--font-display, \'Poppins\', -apple-system, BlinkMacSystemFont, sans-serif);
    font-size: 1.5rem;
    font-weight: 600;
    color: var(--navy-900, #0f172a);
    text-align: center;
    margin: 0 0 1.5rem 0;
}
.allstar-table {
    font-family: var(--font-sans, \'Inter\', -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif);
    border-collapse: separate;
    border-spacing: 0;
    border: none;
    border-radius: var(--radius-lg, 0.5rem);
    overflow: hidden;
    box-shadow: var(--shadow-md, 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1));
    width: 100%;
    max-width: 500px;
    margin: 0 auto;
}
.allstar-table thead {
    background: linear-gradient(135deg, var(--navy-800, #1e293b), var(--navy-900, #0f172a));
}
.allstar-table th {
    color: white;
    font-family: var(--font-display, \'Poppins\', -apple-system, BlinkMacSystemFont, sans-serif);
    font-weight: 600;
    font-size: 1rem;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    padding: 0.75rem 1rem;
    text-align: left;
}
.allstar-table th:last-child {
    text-align: center;
}
.allstar-table td {
    color: var(--gray-800, #1f2937);
    font-size: 1.125rem;
    padding: 0.75rem 1rem;
}
.allstar-table td:last-child {
    text-align: center;
    font-weight: 600;
    color: var(--accent-600, #ea580c);
}
.allstar-table tbody tr {
    transition: background-color 150ms ease;
}
.allstar-table tbody tr:nth-child(odd) {
    background-color: white;
}
.allstar-table tbody tr:nth-child(even) {
    background-color: var(--gray-50, #f9fafb);
}
.allstar-table tbody tr:hover {
    background-color: var(--gray-100, #f3f4f6);
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
        return '<h2 class="allstar-title">All-Star Appearances</h2>';
    }

    /**
     * Render the start of the table.
     *
     * @return string HTML table start
     */
    private function renderTableStart(): string
    {
        return '<table class="sortable allstar-table">
            <thead>
                <tr>
                    <th>Player</th>
                    <th>Appearances</th>
                </tr>
            </thead>
            <tbody>';
    }

    /**
     * Render all table rows.
     *
     * @param array<int, array{name: string, appearances: int}> $appearances Array of appearance data
     * @return string HTML table rows
     */
    private function renderTableRows(array $appearances): string
    {
        $output = '';

        foreach ($appearances as $row) {
            $name = HtmlSanitizer::safeHtmlOutput($row['name'] ?? '');
            $count = (int) ($row['appearances'] ?? 0);

            $output .= "<tr>
    <td>{$name}</td>
    <td>{$count}</td>
</tr>";
        }

        return $output;
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
