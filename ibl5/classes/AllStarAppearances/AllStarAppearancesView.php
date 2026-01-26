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
     * Uses consolidated .ibl-data-table with allstar-specific overrides.
     *
     * @return string CSS style block
     */
    private function getStyleBlock(): string
    {
        return '<style>
/* All-star specific overrides */
.allstar-table {
    max-width: 500px;
}
.allstar-table td:last-child {
    font-weight: 600;
    color: var(--accent-600, #ea580c);
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
        return '<h2 class="ibl-table-title">All-Star Appearances</h2>';
    }

    /**
     * Render the start of the table.
     *
     * @return string HTML table start
     */
    private function renderTableStart(): string
    {
        return '<table class="sortable ibl-data-table allstar-table">
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
