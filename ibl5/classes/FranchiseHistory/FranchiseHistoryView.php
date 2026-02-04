<?php

declare(strict_types=1);

namespace FranchiseHistory;

use FranchiseHistory\Contracts\FranchiseHistoryRepositoryInterface;
use FranchiseHistory\Contracts\FranchiseHistoryViewInterface;
use Utilities\HtmlSanitizer;

/**
 * FranchiseHistoryView - HTML rendering for franchise history
 *
 * Generates sortable HTML table displaying franchise history data.
 *
 * @phpstan-import-type FranchiseRow from FranchiseHistoryRepositoryInterface
 *
 * @see FranchiseHistoryViewInterface For the interface contract
 */
class FranchiseHistoryView implements FranchiseHistoryViewInterface
{
    /**
     * @see FranchiseHistoryViewInterface::render()
     *
     * @param array<int, FranchiseRow> $franchiseData
     */
    public function render(array $franchiseData): string
    {
        $html = '';
        $html .= '<h2 class="ibl-title">Franchise History</h2>';
        $html .= '<div class="sticky-scroll-wrapper">';
        $html .= '<div class="sticky-scroll-container">';
        $html .= $this->renderTableHeader();
        $html .= $this->renderTableRows($franchiseData);
        $html .= '</tbody></table>';
        $html .= '</div></div>';

        return $html;
    }

    /**
     * Render table header
     *
     * @return string HTML for table header
     */
    private function renderTableHeader(): string
    {
        return '<table class="sortable ibl-data-table sticky-table">
            <thead>
            <tr>
                <th class="ibl-team-cell--colored sticky-col sticky-corner">Team</th>
                <th>All-Time<br>Wins</th>
                <th>All-Time<br>Losses</th>
                <th>All-Time<br>Pct.</th>
                <th>Last Five<br>Seasons<br>Wins</th>
                <th>Last Five<br>Seasons<br>Losses</th>
                <th>Last Five<br>Seasons<br>Pct.</th>
                <th>Playoffs</th>
                <th>H.E.A.T.<br>Titles</th>
                <th>Div.<br>Titles</th>
                <th>Conf.<br>Titles</th>
                <th>IBL<br>Titles</th>
            </tr>
            </thead>
            <tbody>';
    }

    /**
     * Render all team rows
     *
     * @param array<int, FranchiseRow> $franchiseData Array of franchise data
     * @return string HTML for all team rows
     */
    private function renderTableRows(array $franchiseData): string
    {
        $html = '';

        foreach ($franchiseData as $team) {
            $html .= $this->renderTeamRow($team);
        }

        return $html;
    }

    /**
     * Render a single team row
     *
     * @param FranchiseRow $team Team franchise data
     * @return string HTML for team row
     */
    private function renderTeamRow(array $team): string
    {
        $teamId = (int)$team['teamid'];
        /** @var string $color1 */
        $color1 = HtmlSanitizer::safeHtmlOutput($team['color1']);
        /** @var string $color2 */
        $color2 = HtmlSanitizer::safeHtmlOutput($team['color2']);
        /** @var string $teamName */
        $teamName = HtmlSanitizer::safeHtmlOutput($team['team_name']);

        $html = '<tr data-team-id="' . $teamId . '">';

        // Team name cell with logo - sticky column
        $html .= '<td class="ibl-team-cell--colored sticky-col" style="background-color: #' . $color1 . ';">';
        $html .= '<a href="modules.php?name=Team&amp;op=team&amp;teamID=' . $teamId . '" ';
        $html .= 'class="ibl-team-cell__name" style="color: #' . $color2 . ';">';
        $html .= '<img src="images/logo/new' . $teamId . '.png" alt="" class="ibl-team-cell__logo" width="24" height="24" loading="lazy">';
        $html .= '<span class="ibl-team-cell__text">' . $teamName . '</span></a>';
        $html .= '</td>';

        // All-time stats
        $html .= '<td>' . (int)$team['totwins'] . '</td>';
        $html .= '<td>' . (int)$team['totloss'] . '</td>';
        /** @var string $winpct */
        $winpct = HtmlSanitizer::safeHtmlOutput($team['winpct']);
        $html .= '<td>' . $winpct . '</td>';

        // Last five seasons stats
        $html .= '<td class="last-five-cell">' . (int)$team['five_season_wins'] . '</td>';
        $html .= '<td class="last-five-cell">' . (int)$team['five_season_losses'] . '</td>';
        /** @var string $fiveSeasonWinpct */
        $fiveSeasonWinpct = HtmlSanitizer::safeHtmlOutput($team['five_season_winpct'] ?? '');
        $html .= '<td class="last-five-cell">' . $fiveSeasonWinpct . '</td>';

        // Titles and playoffs
        $html .= '<td>' . (int)$team['playoffs'] . '</td>';
        $html .= '<td>' . $team['heat_titles'] . '</td>';
        $html .= '<td>' . $team['div_titles'] . '</td>';
        $html .= '<td>' . $team['conf_titles'] . '</td>';
        $html .= '<td>' . $team['ibl_titles'] . '</td>';

        $html .= '</tr>';

        return $html;
    }
}
