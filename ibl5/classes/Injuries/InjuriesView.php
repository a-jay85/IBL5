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
     * @return string CSS style block
     */
    private function getStyleBlock(): string
    {
        return <<<HTML
<style>
    .injuries-title {
        text-align: center;
    }
    .injuries-table {
        border-collapse: collapse;
    }
    .injuries-table th,
    .injuries-table td {
        padding: 4px 8px;
    }
    .injuries-row-even {
        background-color: #FFFFFF;
    }
    .injuries-row-odd {
        background-color: #DDDDDD;
    }
    .team-cell a {
        text-decoration: underline;
    }
</style>
HTML;
    }

    /**
     * Render the page title.
     *
     * @return string HTML title
     */
    private function renderTitle(): string
    {
        return '<h2 class="injuries-title">INJURED PLAYERS</h2>';
    }

    /**
     * Render the start of the injuries table.
     *
     * @return string HTML table start
     */
    private function renderTableStart(): string
    {
        return <<<HTML
<table>
    <tr>
        <td style="vertical-align: top;">
            <table class="sortable injuries-table">
                <tr>
                    <th>Pos</th>
                    <th>Player</th>
                    <th>Team</th>
                    <th>Days Injured</th>
                </tr>
HTML;
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
        $rowIndex = 0;

        foreach ($injuredPlayers as $player) {
            $output .= $this->renderPlayerRow($player, $rowIndex);
            $rowIndex++;
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
     * @param int $rowIndex Row index for alternating colors
     * @return string HTML for one player row
     */
    private function renderPlayerRow(array $player, int $rowIndex): string
    {
        $rowClass = ($rowIndex % 2 === 0) ? 'injuries-row-even' : 'injuries-row-odd';

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

        return <<<HTML
<tr class="{$rowClass}">
    <td>{$position}</td>
    <td><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid={$playerID}">{$name}</a></td>
    <td class="team-cell" style="background-color: #{$color1};">
        <a href="./modules.php?name=Team&amp;op=team&amp;teamID={$teamID}" style="color: #{$color2};">{$teamCity} {$teamName}</a>
    </td>
    <td>{$daysRemaining}</td>
</tr>
HTML;
    }

    /**
     * Render the end of the injuries table.
     *
     * @return string HTML table end
     */
    private function renderTableEnd(): string
    {
        return '</table></table>';
    }
}
