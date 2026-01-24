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
     * @return string CSS style block
     */
    private function getStyleBlock(): string
    {
        return '<style>
/* Power Rankings Card Container */
.power-rankings-wrapper {
    background: white;
    border-radius: var(--radius-xl, 0.75rem);
    overflow: hidden;
    box-shadow: var(--shadow-md, 0 4px 6px -1px rgb(0 0 0 / 0.1));
    border: 1px solid var(--gray-100, #f3f4f6);
    max-width: 600px;
    margin: 0 auto 1.5rem;
}

/* Title */
.power-rankings-title {
    background: linear-gradient(135deg, var(--navy-800, #1e293b), var(--navy-900, #0f172a));
    padding: 0.875rem 1rem;
    margin: 0;
    font-family: var(--font-display, \'Poppins\', -apple-system, BlinkMacSystemFont, sans-serif);
    font-size: 0.9375rem;
    font-weight: 600;
    color: white;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    text-align: center;
}

/* Table */
.power-rankings-table {
    font-family: var(--font-sans, \'Inter\', -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif);
    border-collapse: collapse;
    width: 100%;
}

/* Header */
.power-rankings-table thead {
    background: var(--gray-50, #f9fafb);
    border-bottom: 1px solid var(--gray-200, #e5e7eb);
}

.power-rankings-table th {
    color: var(--gray-600, #4b5563);
    font-family: var(--font-display, \'Poppins\', -apple-system, BlinkMacSystemFont, sans-serif);
    font-weight: 600;
    font-size: 0.6875rem;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    padding: 0.625rem 0.5rem;
    text-align: center;
}

/* Data cells */
.power-rankings-table td {
    color: var(--gray-800, #1f2937);
    font-size: 0.75rem;
    padding: 0.625rem 0.5rem;
    text-align: center;
    border-bottom: 1px solid var(--gray-100, #f3f4f6);
}

/* Row styling */
.power-rankings-table tbody tr {
    transition: background-color 150ms ease;
}
.power-rankings-table tbody tr:hover {
    background-color: var(--gray-50, #f9fafb);
}

/* Specific cells */
.power-rank-cell {
    font-weight: 600;
    color: var(--navy-700, #334155);
    width: 40px;
}

.power-logo-cell {
    width: 50px;
}

.power-logo-cell img {
    width: 32px;
    height: 32px;
    object-fit: contain;
    border-radius: var(--radius-sm, 0.25rem);
}

.power-data-cell {
    text-align: center;
}

/* Links */
.power-rankings-table a {
    color: var(--gray-800, #1f2937);
    text-decoration: none;
    transition: opacity 150ms ease;
}
.power-rankings-table a:hover {
    opacity: 0.8;
}

/* Strong values */
.power-rankings-table strong {
    font-weight: 600;
    color: var(--navy-900, #0f172a);
}

/* Rating highlight */
.power-rating {
    font-family: var(--font-display, \'Poppins\', -apple-system, BlinkMacSystemFont, sans-serif);
    font-weight: 700;
    color: var(--accent-500, #f97316);
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
        return '<table class="power-rankings-table">
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
