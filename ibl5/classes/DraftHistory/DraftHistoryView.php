<?php

declare(strict_types=1);

namespace DraftHistory;

use DraftHistory\Contracts\DraftHistoryViewInterface;
use Utilities\HtmlSanitizer;

/**
 * View class for rendering draft history page.
 *
 * @see DraftHistoryViewInterface
 */
class DraftHistoryView implements DraftHistoryViewInterface
{
    /**
     * @see DraftHistoryViewInterface::render()
     */
    public function render(int $selectedYear, int $startYear, int $endYear, array $draftPicks): string
    {
        $output = $this->getStyleBlock();
        $output .= $this->renderTitleWithYearSelect($startYear, $endYear, $selectedYear);

        if (empty($draftPicks)) {
            $output .= $this->renderNoDataMessage();
        } else {
            $output .= $this->renderTableStart();
            $output .= $this->renderTableRows($draftPicks);
            $output .= $this->renderTableEnd();
        }

        return $output;
    }

    /**
     * Get the CSS styles for the draft history table.
     *
     * Uses consolidated .ibl-data-table with draft-history-specific overrides.
     *
     * @return string CSS style block
     */
    private function getStyleBlock(): string
    {
        return '<style>
.draft-year-select {
    padding: 0.25rem 1.75rem 0.25rem 0.5rem;
    font-size: inherit;
    font-weight: inherit;
    color: inherit;
    border: 1px solid var(--gray-300, #d1d5db);
    border-radius: 0.375rem;
    background-color: white;
    background-image: url("data:image/svg+xml,%3csvg xmlns=\'http://www.w3.org/2000/svg\' fill=\'none\' viewBox=\'0 0 20 20\'%3e%3cpath stroke=\'%236b7280\' stroke-linecap=\'round\' stroke-linejoin=\'round\' stroke-width=\'1.5\' d=\'M6 8l4 4 4-4\'/%3e%3c/svg%3e");
    background-position: right 0.25rem center;
    background-repeat: no-repeat;
    background-size: 1.25em 1.25em;
    appearance: none;
    cursor: pointer;
    vertical-align: baseline;
}
.draft-year-select:hover {
    border-color: var(--gray-400, #9ca3af);
}
.draft-year-select:focus {
    outline: none;
    border-color: var(--navy-500, #1e3a5f);
    box-shadow: 0 0 0 3px rgba(30, 58, 95, 0.1);
}
.draft-history-table .name-cell {
    white-space: nowrap;
    text-align: left;
}
.draft-history-table .name-cell a {
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
@media (max-width: 768px) {
    .draft-history-table .name-cell {
        white-space: normal;
    }
    .draft-history-table th:last-child {
        text-align: center;
    }
}
</style>';
    }

    /**
     * Render the page title with integrated year select dropdown.
     *
     * @param int $startYear First draft year
     * @param int $endYear Last draft year
     * @param int $selectedYear Currently selected year
     * @return string HTML title with dropdown
     */
    private function renderTitleWithYearSelect(int $startYear, int $endYear, int $selectedYear): string
    {
        $output = '<h2 class="ibl-table-title">';
        $output .= '<select id="draft-year-select" class="draft-year-select" onchange="window.location.href=\'./modules.php?name=Draft_History&amp;year=\' + this.value">';

        for ($year = $endYear; $year >= $startYear; $year--) {
            $selected = ($year === $selectedYear) ? ' selected' : '';
            $output .= '<option value="' . $year . '"' . $selected . '>' . $year . '</option>';
        }

        $output .= '</select> Draft</h2>';
        return $output;
    }

    /**
     * Render the no data message.
     *
     * @return string HTML message
     */
    private function renderNoDataMessage(): string
    {
        return '<p class="draft-no-data">Please select a draft year.</p>';
    }

    /**
     * Render the start of the table.
     *
     * @return string HTML table start
     */
    private function renderTableStart(): string
    {
        return '<table class="sortable ibl-data-table draft-history-table responsive-table">
            <thead>
                <tr>
                    <th class="sticky-col-1">Rd</th>
                    <th class="sticky-col-2">Pick</th>
                    <th>Player</th>
                    <th class="ibl-team-cell--colored">Team</th>
                    <th>College</th>
                </tr>
            </thead>
            <tbody>';
    }

    /**
     * Render all table rows.
     *
     * @param array $draftPicks Array of draft pick data
     * @return string HTML table rows
     */
    private function renderTableRows(array $draftPicks): string
    {
        $output = '';

        foreach ($draftPicks as $pick) {
            $pid = (int) ($pick['pid'] ?? 0);
            $name = HtmlSanitizer::safeHtmlOutput($pick['name'] ?? '');
            $round = (int) ($pick['draftround'] ?? 0);
            $pickNo = (int) ($pick['draftpickno'] ?? 0);
            $college = HtmlSanitizer::safeHtmlOutput($pick['college'] ?? '');

            // Team cell styling
            $teamId = (int) ($pick['teamid'] ?? 0);
            $teamCity = HtmlSanitizer::safeHtmlOutput($pick['team_city'] ?? '');
            $teamName = HtmlSanitizer::safeHtmlOutput($pick['draftedby'] ?? '');
            $color1 = HtmlSanitizer::safeHtmlOutput($pick['color1'] ?? 'FFFFFF');
            $color2 = HtmlSanitizer::safeHtmlOutput($pick['color2'] ?? '000000');

            // Handle unknown teams (no match found) gracefully
            if ($teamId === 0) {
                $teamCell = "<td>{$teamName}</td>";
            } else {
                $teamCell = "<td class=\"ibl-team-cell--colored\" style=\"background-color: #{$color1};\">
        <a href=\"./modules.php?name=Team&amp;op=team&amp;teamID={$teamId}\" class=\"ibl-team-cell__name\" style=\"color: #{$color2};\">
            <img src=\"images/logo/new{$teamId}.png\" alt=\"\" class=\"ibl-team-cell__logo\" width=\"24\" height=\"24\" loading=\"lazy\">
            <span class=\"ibl-team-cell__text\">{$teamCity} {$teamName}</span>
        </a>
    </td>";
            }

            $playerImage = "images/player/{$pid}.jpg";

            $output .= "<tr>
    <td class=\"sticky-col-1\">{$round}</td>
    <td class=\"sticky-col-2\">{$pickNo}</td>
    <td class=\"name-cell\"><a href=\"./modules.php?name=Player&amp;pa=showpage&amp;pid={$pid}\"><img src=\"{$playerImage}\" alt=\"\" class=\"ibl-player-photo\" width=\"24\" height=\"24\" loading=\"lazy\">{$name}</a></td>
    {$teamCell}
    <td>{$college}</td>
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
