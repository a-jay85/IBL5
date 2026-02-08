<?php

declare(strict_types=1);

namespace AllStarAppearances;

use AllStarAppearances\Contracts\AllStarAppearancesViewInterface;
use Player\PlayerImageHelper;

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
        $output = $this->renderTitle();
        $output .= $this->renderTableStart();
        $output .= $this->renderTableRows($appearances);
        $output .= $this->renderTableEnd();

        return $output;
    }

    /**
     * Render the page title.
     *
     * @return string HTML title
     */
    private function renderTitle(): string
    {
        return '<h2 class="ibl-title">All-Star Appearances</h2>';
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
     * @param array<int, array{name: string, appearances: int, pid?: int}> $appearances Array of appearance data
     * @return string HTML table rows
     */
    private function renderTableRows(array $appearances): string
    {
        $output = '';

        foreach ($appearances as $row) {
            $pid = (int) ($row['pid'] ?? 0);
            $count = (int) ($row['appearances'] ?? 0);
            $playerCell = PlayerImageHelper::renderFlexiblePlayerCell($pid, $row['name'] ?? '');

            $output .= '<tr>'
                . $playerCell
                . "<td>{$count}</td>"
                . '</tr>';
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
