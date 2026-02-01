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
     * Styles are now in the design system (existing-components.css).
     *
     * @return string Empty string - styles are centralized
     */
    private function getStyleBlock(): string
    {
        return '<style>
.allstar-table .ibl-player-cell {
    text-align: left;
}
.allstar-table .ibl-player-cell a {
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.ibl-player-photo {
    width: 24px;
    height: 24px;
    object-fit: cover;
    border-radius: 50%;
    flex-shrink: 0;
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
     * @param array<int, array{name: string, pid: int, appearances: int}> $appearances Array of appearance data
     * @return string HTML table rows
     */
    private function renderTableRows(array $appearances): string
    {
        $output = '';

        foreach ($appearances as $row) {
            $name = HtmlSanitizer::safeHtmlOutput($row['name'] ?? '');
            $pid = (int) ($row['pid'] ?? 0);
            $count = (int) ($row['appearances'] ?? 0);
            $playerImage = "images/player/{$pid}.jpg";

            $output .= "<tr>
    <td class=\"ibl-player-cell\"><a href=\"modules.php?name=Player&amp;pa=showpage&amp;pid={$pid}\"><img src=\"{$playerImage}\" alt=\"\" class=\"ibl-player-photo\" width=\"24\" height=\"24\">{$name}</a></td>
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
