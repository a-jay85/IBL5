<?php

declare(strict_types=1);

namespace Injuries;

use Injuries\Contracts\InjuriesViewInterface;
use Player\PlayerImageHelper;
use UI\Components\InjuryDaysLabel;
use UI\TeamCellHelper;
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
     *     returnDate: ?string,
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
     *     returnDate: ?string,
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
        $playerID = $player['playerID'];
        $teamID = $player['teamID'];
        /** @var string $position */
        $position = HtmlSanitizer::safeHtmlOutput($player['position']);
        $daysRemaining = $player['daysRemaining'];
        $returnDate = $player['returnDate'] ?? '';
        $renderedLabel = InjuryDaysLabel::render($daysRemaining, $returnDate);
        $daysLabel = $renderedLabel !== '' ? $renderedLabel : (string) $daysRemaining;

        $playerCell = PlayerImageHelper::renderFlexiblePlayerCell($playerID, $player['name']);
        $teamCell = TeamCellHelper::renderTeamCell($teamID, $player['teamName'], $player['teamColor1'], $player['teamColor2']);

        return "<tr data-team-id=\"{$teamID}\">"
            . "<td>{$position}</td>"
            . $playerCell
            . $teamCell
            . "<td class=\"ibl-stat-highlight\">{$daysLabel}</td>"
            . '</tr>';
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
