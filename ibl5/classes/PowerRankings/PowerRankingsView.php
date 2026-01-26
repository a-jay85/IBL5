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
        $html .= '<div class="power-rankings-wrapper">';
        $html .= $this->renderTitle($seasonEndingYear);
        $html .= $this->renderTableStart();
        $html .= $this->renderTableRows($rankings);
        $html .= '</tbody></table></div>';

        return $html;
    }

    /**
     * Generate CSS styles for the power rankings table
     *
     * Styles are now in the design system (existing-components.css).
     *
     * @return string Empty string - styles are centralized
     */
    private function getStyleBlock(): string
    {
        return '';
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
        return '<h3 class="power-rankings-title">' .
            HtmlSanitizer::safeHtmlOutput((string)$startYear) . '-' .
            HtmlSanitizer::safeHtmlOutput((string)$seasonEndingYear) .
            ' IBL Power Rankings</h3>';
    }

    /**
     * Render table start with headers
     *
     * @return string HTML for table start
     */
    private function renderTableStart(): string
    {
        return '<table class="ibl-data-table power-rankings-table">
            <thead>
                <tr>
                    <th class="power-rank-cell">Rank</th>
                    <th class="power-logo-cell">Team</th>
                    <th class="power-data-cell">Record</th>
                    <th class="power-data-cell">Home</th>
                    <th class="power-data-cell">Away</th>
                    <th class="power-data-cell">Rating</th>
                </tr>
            </thead>
            <tbody>';
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
        $teamId = (int)($team['TeamID'] ?? 0);
        $wins = HtmlSanitizer::safeHtmlOutput((string)($team['win'] ?? 0));
        $losses = HtmlSanitizer::safeHtmlOutput((string)($team['loss'] ?? 0));
        $homeWins = HtmlSanitizer::safeHtmlOutput((string)($team['home_win'] ?? 0));
        $homeLosses = HtmlSanitizer::safeHtmlOutput((string)($team['home_loss'] ?? 0));
        $awayWins = HtmlSanitizer::safeHtmlOutput((string)($team['road_win'] ?? 0));
        $awayLosses = HtmlSanitizer::safeHtmlOutput((string)($team['road_loss'] ?? 0));
        $ranking = HtmlSanitizer::safeHtmlOutput((string)($team['ranking'] ?? 0));

        return '<tr>
            <td class="power-rank-cell">' . $rank . '.</td>
            <td class="power-logo-cell">
                <a href="modules.php?name=Team&amp;op=team&amp;teamID=' . $teamId . '">
                    <img src="images/logo/' . $teamId . '.jpg" alt="Team Logo" loading="lazy">
                </a>
            </td>
            <td class="power-data-cell"><strong>' . $wins . '-' . $losses . '</strong></td>
            <td class="power-data-cell">' . $homeWins . '-' . $homeLosses . '</td>
            <td class="power-data-cell">' . $awayWins . '-' . $awayLosses . '</td>
            <td class="power-data-cell"><span class="power-rating">' . $ranking . '</span></td>
        </tr>';
    }
}
