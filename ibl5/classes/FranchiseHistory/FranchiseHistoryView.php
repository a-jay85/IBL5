<?php

declare(strict_types=1);

namespace FranchiseHistory;

use FranchiseHistory\Contracts\FranchiseHistoryRepositoryInterface;
use FranchiseHistory\Contracts\FranchiseHistoryViewInterface;
use UI\TeamCellHelper;
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
                <th>All-Time<br>Record</th>
                <th>Last Five<br>Seasons</th>
                <th>All-Time<br>Playoffs Record</th>
                <th>All-Time<br>HEAT Record</th>
                <th>Playoff<br>Berths</th>
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

        $html = '<tr data-team-id="' . $teamId . '">';
        $html .= TeamCellHelper::renderTeamCell($teamId, $team['team_name'], $team['color1'], $team['color2'], 'sticky-col');

        // All-time record
        $allTimeWins = (int)$team['totwins'];
        $allTimeLosses = (int)$team['totloss'];
        /** @var string $allTimeWinpct */
        $allTimeWinpct = HtmlSanitizer::safeHtmlOutput($team['winpct']);
        $html .= '<td style="white-space: nowrap;" sorttable_customkey="' . $allTimeWinpct . '">' . $allTimeWins . '-' . $allTimeLosses . ' (' . $allTimeWinpct . ')</td>';

        // Last five seasons record
        $fiveSeasonWins = (int)$team['five_season_wins'];
        $fiveSeasonLosses = (int)$team['five_season_losses'];
        /** @var string $fiveSeasonWinpct */
        $fiveSeasonWinpct = HtmlSanitizer::safeHtmlOutput($team['five_season_winpct'] ?? '');
        $html .= '<td style="white-space: nowrap;" sorttable_customkey="' . $fiveSeasonWinpct . '">' . $fiveSeasonWins . '-' . $fiveSeasonLosses . ' (' . $fiveSeasonWinpct . ')</td>';

        // Record columns
        $playoffWins = (int)$team['playoff_total_wins'];
        $playoffLosses = (int)$team['playoff_total_losses'];
        /** @var string $playoffWinpct */
        $playoffWinpct = HtmlSanitizer::safeHtmlOutput($team['playoff_winpct']);
        $html .= '<td style="white-space: nowrap;" sorttable_customkey="' . $playoffWinpct . '">' . $playoffWins . '-' . $playoffLosses . ' (' . $playoffWinpct . ')</td>';
        $heatWins = (int)$team['heat_total_wins'];
        $heatLosses = (int)$team['heat_total_losses'];
        /** @var string $heatWinpct */
        $heatWinpct = HtmlSanitizer::safeHtmlOutput($team['heat_winpct']);
        $html .= '<td style="white-space: nowrap;" sorttable_customkey="' . $heatWinpct . '">' . $heatWins . '-' . $heatLosses . ' (' . $heatWinpct . ')</td>';

        // Titles and playoff berths
        $html .= '<td>' . (int)$team['playoffs'] . '</td>';
        $html .= '<td>' . $team['heat_titles'] . '</td>';
        $html .= '<td>' . $team['div_titles'] . '</td>';
        $html .= '<td>' . $team['conf_titles'] . '</td>';
        $html .= '<td>' . $team['ibl_titles'] . '</td>';

        $html .= '</tr>';

        return $html;
    }
}
