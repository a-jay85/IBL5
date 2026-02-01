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
        $output = $this->renderTitle();
        $output .= $this->renderTableStart();
        $output .= $this->renderTableRows($injuredPlayers);
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
        return '<h2 class="ibl-title">Injured Players</h2>';
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
        $teamName = HtmlSanitizer::safeHtmlOutput($player['teamName']);
        $color1 = HtmlSanitizer::safeHtmlOutput($player['teamColor1']);
        $color2 = HtmlSanitizer::safeHtmlOutput($player['teamColor2']);
        $playerImage = "images/player/{$playerID}.jpg";

        return "<tr>
    <td>{$position}</td>
    <td class=\"ibl-player-cell\"><a href=\"./modules.php?name=Player&amp;pa=showpage&amp;pid={$playerID}\"><img src=\"{$playerImage}\" alt=\"\" class=\"ibl-player-photo\" width=\"24\" height=\"24\">{$name}</a></td>
    <td class=\"ibl-team-cell--colored\" style=\"background-color: #{$color1};\">
        <a href=\"./modules.php?name=Team&amp;op=team&amp;teamID={$teamID}\" class=\"ibl-team-cell__name\" style=\"color: #{$color2};\">
            <img src=\"images/logo/new{$teamID}.png\" alt=\"\" class=\"ibl-team-cell__logo\" width=\"24\" height=\"24\" loading=\"lazy\">
            <span class=\"ibl-team-cell__text\">{$teamName}</span>
        </a>
    </td>
    <td class=\"ibl-stat-highlight\">{$daysRemaining}</td>
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
