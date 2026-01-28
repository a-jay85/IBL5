<?php

declare(strict_types=1);

namespace FranchiseHistory;

use FranchiseHistory\Contracts\FranchiseHistoryViewInterface;
use Utilities\HtmlSanitizer;

/**
 * FranchiseHistoryView - HTML rendering for franchise history
 *
 * Generates sortable HTML table displaying franchise history data.
 *
 * @see FranchiseHistoryViewInterface For the interface contract
 */
class FranchiseHistoryView implements FranchiseHistoryViewInterface
{
    /**
     * @see FranchiseHistoryViewInterface::render()
     */
    public function render(array $franchiseData): string
    {
        $html = $this->getStyleBlock();
        $html .= $this->renderTableHeader();
        $html .= $this->renderTableRows($franchiseData);
        $html .= '</tbody></table>';

        return $html;
    }

    /**
     * Generate CSS styles for the franchise history table
     *
     * @return string CSS style block
     */
    private function getStyleBlock(): string
    {
        return ''; // All styles provided by .ibl-data-table
    }

    /**
     * Render table header
     *
     * @return string HTML for table header
     */
    private function renderTableHeader(): string
    {
        return '<table class="sortable ibl-data-table">
            <thead>
            <tr>
                <th>Team</th>
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
     * @param array $franchiseData Array of franchise data
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
     * @param array $team Team franchise data
     * @return string HTML for team row
     */
    private function renderTeamRow(array $team): string
    {
        $teamId = (int)$team['teamid'];
        $color1 = HtmlSanitizer::safeHtmlOutput($team['color1']);
        $color2 = HtmlSanitizer::safeHtmlOutput($team['color2']);
        $teamCity = HtmlSanitizer::safeHtmlOutput($team['team_city']);
        $teamName = HtmlSanitizer::safeHtmlOutput($team['team_name']);

        $html = '<tr>';

        // Team name cell with logo
        $html .= '<td class="ibl-team-cell--colored" style="background-color: #' . $color1 . ';">';
        $html .= '<a href="modules.php?name=Team&amp;op=team&amp;teamID=' . $teamId . '" ';
        $html .= 'class="ibl-team-cell__name" style="color: #' . $color2 . ';">';
        $html .= '<img src="images/logo/new' . $teamId . '.png" alt="" class="ibl-team-cell__logo" width="24" height="24" loading="lazy">';
        $html .= $teamCity . ' ' . $teamName . '</a>';
        $html .= '</td>';

        // All-time stats
        $html .= '<td>' . HtmlSanitizer::safeHtmlOutput($team['totwins']) . '</td>';
        $html .= '<td>' . HtmlSanitizer::safeHtmlOutput($team['totloss']) . '</td>';
        $html .= '<td>' . HtmlSanitizer::safeHtmlOutput($team['winpct']) . '</td>';

        // Last five seasons stats
        $html .= '<td class="last-five-cell">' . HtmlSanitizer::safeHtmlOutput($team['five_season_wins']) . '</td>';
        $html .= '<td class="last-five-cell">' . HtmlSanitizer::safeHtmlOutput($team['five_season_losses']) . '</td>';
        $html .= '<td class="last-five-cell">' . HtmlSanitizer::safeHtmlOutput($team['five_season_winpct']) . '</td>';

        // Titles and playoffs
        $html .= '<td>' . HtmlSanitizer::safeHtmlOutput($team['playoffs']) . '</td>';
        $html .= '<td>' . HtmlSanitizer::safeHtmlOutput($team['heat_titles']) . '</td>';
        $html .= '<td>' . HtmlSanitizer::safeHtmlOutput($team['div_titles']) . '</td>';
        $html .= '<td>' . HtmlSanitizer::safeHtmlOutput($team['conf_titles']) . '</td>';
        $html .= '<td>' . HtmlSanitizer::safeHtmlOutput($team['ibl_titles']) . '</td>';

        $html .= '</tr>';

        return $html;
    }
}
