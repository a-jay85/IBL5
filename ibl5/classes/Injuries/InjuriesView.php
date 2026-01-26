<?php

declare(strict_types=1);

namespace Injuries;

use Injuries\Contracts\InjuriesViewInterface;
use Utilities\HtmlSanitizer;

/**
 * View class for rendering injured players table.
 *
 * @see InjuriesViewInterface
 */
class InjuriesView implements InjuriesViewInterface
{
    /**
     * @see InjuriesViewInterface::render()
     */
    public function render(array $injuredPlayers): string
    {
        $output = $this->getStyleBlock();
        $output .= $this->renderTitle();
        $output .= $this->renderTableStart();
        $output .= $this->renderTableRows($injuredPlayers);
        $output .= $this->renderTableEnd();

        return $output;
    }

    /**
     * Get the CSS styles for the injuries table.
     *
     * Uses consolidated .ibl-data-table from design system with minimal overrides.
     *
     * @return string CSS style block
     */
    private function getStyleBlock(): string
    {
        return '<style>
/* Injuries-specific overrides only */
.injuries-table {
    max-width: 600px;
}
.injuries-table .team-cell {
    border-radius: var(--radius-sm, 0.25rem);
    padding: 0.375rem 0.5rem;
}
.injuries-table .team-cell a {
    font-weight: 600;
}
.injuries-table .days-cell {
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
        return '<h2 class="ibl-table-title">Injured Players</h2>';
    }

    /**
     * Render the start of the injuries table.
     *
     * @return string HTML table start
     */
    private function renderTableStart(): string
    {
        return '<table class="sortable ibl-data-table injuries-table">
            <thead>
                <tr>
                    <th>Pos</th>
                    <th>Player</th>
                    <th>Team</th>
                    <th>Days</th>
                </tr>
            </thead>
            <tbody>';
    }

    /**
     * Render all table rows for injured players.
     *
     * @param array<int, array{
     *     playerID: int,
     *     name: string,
     *     position: string,
     *     daysRemaining: int,
     *     teamID: int,
     *     teamCity: string,
     *     teamName: string,
     *     teamColor1: string,
     *     teamColor2: string
     * }> $injuredPlayers Array of injured player data
     * @return string HTML table rows
     */
    private function renderTableRows(array $injuredPlayers): string
    {
        $output = '';

        foreach ($injuredPlayers as $player) {
            $output .= $this->renderPlayerRow($player);
        }

        return $output;
    }

    /**
     * Render a single player row.
     *
     * @param array{
     *     playerID: int,
     *     name: string,
     *     position: string,
     *     daysRemaining: int,
     *     teamID: int,
     *     teamCity: string,
     *     teamName: string,
     *     teamColor1: string,
     *     teamColor2: string
     * } $player Player data array
     * @return string HTML for one player row
     */
    private function renderPlayerRow(array $player): string
    {
        // Sanitize all output for XSS protection
        $playerID = (int) $player['playerID'];
        $teamID = (int) $player['teamID'];
        $name = HtmlSanitizer::safeHtmlOutput($player['name']);
        $position = HtmlSanitizer::safeHtmlOutput($player['position']);
        $daysRemaining = (int) $player['daysRemaining'];
        $teamCity = HtmlSanitizer::safeHtmlOutput($player['teamCity']);
        $teamName = HtmlSanitizer::safeHtmlOutput($player['teamName']);
        $color1 = HtmlSanitizer::safeHtmlOutput($player['teamColor1']);
        $color2 = HtmlSanitizer::safeHtmlOutput($player['teamColor2']);

        return "<tr>
    <td>{$position}</td>
    <td><a href=\"./modules.php?name=Player&amp;pa=showpage&amp;pid={$playerID}\">{$name}</a></td>
    <td class=\"team-cell\" style=\"background-color: #{$color1};\">
        <a href=\"./modules.php?name=Team&amp;op=team&amp;teamID={$teamID}\" style=\"color: #{$color2};\">{$teamCity} {$teamName}</a>
    </td>
    <td class=\"days-cell\">{$daysRemaining}</td>
</tr>";
    }

    /**
     * Render the end of the injuries table.
     *
     * @return string HTML table end
     */
    private function renderTableEnd(): string
    {
        return '</tbody></table>';
    }
}
