<?php

declare(strict_types=1);

namespace PowerRankings;

use PowerRankings\Contracts\PowerRankingsViewInterface;
use Utilities\HtmlSanitizer;

/**
 * PowerRankingsView - HTML rendering for power rankings
 *
 * Generates HTML table displaying team power rankings.
 *
 * @see PowerRankingsViewInterface For the interface contract
 */
class PowerRankingsView implements PowerRankingsViewInterface
{
    /**
     * @see PowerRankingsViewInterface::render()
     */
    public function render(array $rankings, int $seasonEndingYear): string
    {
        $html = $this->getStyleBlock();
        $html .= $this->renderTitle($seasonEndingYear);
        $html .= $this->renderTableStart();
        $html .= $this->renderTableRows($rankings);
        $html .= '</table>';

        return $html;
    }

    /**
     * Generate CSS styles for the power rankings table
     *
     * @return string CSS style block
     */
    private function getStyleBlock(): string
    {
        return '<style>
            .power-rankings-title {
                text-align: center;
                font-size: 1.2em;
                font-weight: bold;
                margin-bottom: 10px;
            }
            .power-rankings-table {
                width: 500px;
                margin: 0 auto;
                border-collapse: collapse;
            }
            .power-rankings-table td {
                padding: 4px;
            }
            .power-header {
                font-weight: bold;
            }
            .power-row-even {
                background-color: #FFFFFF;
            }
            .power-row-odd {
                background-color: #DDDDDD;
            }
            .power-rank-cell {
                text-align: right;
            }
            .power-logo-cell {
                text-align: center;
            }
            .power-data-cell {
                text-align: center;
            }
        </style>';
    }

    /**
     * Render the title
     *
     * @param int $seasonEndingYear Season ending year
     * @return string HTML for title
     */
    private function renderTitle(int $seasonEndingYear): string
    {
        $startYear = $seasonEndingYear - 1;
        return '<div class="power-rankings-title">' . 
            HtmlSanitizer::safeHtmlOutput($startYear) . '-' . 
            HtmlSanitizer::safeHtmlOutput($seasonEndingYear) . 
            ' IBL Power Rankings</div>';
    }

    /**
     * Render table start with headers
     *
     * @return string HTML for table start
     */
    private function renderTableStart(): string
    {
        return '<table class="power-rankings-table">
            <tr>
                <td class="power-rank-cell power-header">Rank</td>
                <td class="power-logo-cell power-header">Team</td>
                <td class="power-data-cell power-header">Record</td>
                <td class="power-data-cell power-header">Home</td>
                <td class="power-data-cell power-header">Away</td>
                <td class="power-data-cell power-header">Rating</td>
            </tr>';
    }

    /**
     * Render all team rows
     *
     * @param array $rankings Power rankings data
     * @return string HTML for all team rows
     */
    private function renderTableRows(array $rankings): string
    {
        $html = '';
        $rank = 1;

        foreach ($rankings as $team) {
            $html .= $this->renderTeamRow($team, $rank);
            $rank++;
        }

        return $html;
    }

    /**
     * Render a single team row
     *
     * @param array $team Team power ranking data
     * @param int $rank Current rank
     * @return string HTML for team row
     */
    private function renderTeamRow(array $team, int $rank): string
    {
        $rowClass = ($rank % 2 === 0) ? 'power-row-even' : 'power-row-odd';
        $teamId = (int)($team['TeamID'] ?? 0);
        $wins = HtmlSanitizer::safeHtmlOutput($team['win'] ?? 0);
        $losses = HtmlSanitizer::safeHtmlOutput($team['loss'] ?? 0);
        $homeWins = HtmlSanitizer::safeHtmlOutput($team['home_win'] ?? 0);
        $homeLosses = HtmlSanitizer::safeHtmlOutput($team['home_loss'] ?? 0);
        $awayWins = HtmlSanitizer::safeHtmlOutput($team['road_win'] ?? 0);
        $awayLosses = HtmlSanitizer::safeHtmlOutput($team['road_loss'] ?? 0);
        $ranking = HtmlSanitizer::safeHtmlOutput($team['ranking'] ?? 0);

        return '<tr class="' . $rowClass . '">
            <td class="power-rank-cell">' . $rank . '.</td>
            <td class="power-logo-cell">
                <a href="modules.php?name=Team&amp;op=team&amp;teamID=' . $teamId . '">
                    <img src="images/logo/' . $teamId . '.jpg" alt="Team Logo">
                </a>
            </td>
            <td class="power-data-cell"><strong style="font-weight: bold;">' . $wins . '-' . $losses . '</strong></td>
            <td class="power-data-cell">' . $homeWins . '-' . $homeLosses . '</td>
            <td class="power-data-cell">' . $awayWins . '-' . $awayLosses . '</td>
            <td class="power-data-cell"><strong style="font-weight: bold;">' . $ranking . '</strong></td>
        </tr>';
    }
}
