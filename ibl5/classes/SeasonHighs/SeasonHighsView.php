<?php

declare(strict_types=1);

namespace SeasonHighs;

use SeasonHighs\Contracts\SeasonHighsViewInterface;
use Utilities\HtmlSanitizer;

/**
 * View class for rendering season highs page.
 *
 * @see SeasonHighsViewInterface
 */
class SeasonHighsView implements SeasonHighsViewInterface
{
    /**
     * @see SeasonHighsViewInterface::render()
     */
    public function render(string $seasonPhase, array $data): string
    {
        $output = $this->getStyleBlock();
        $output .= $this->renderPlayerHighs($seasonPhase, $data['playerHighs']);
        $output .= $this->renderTeamHighs($seasonPhase, $data['teamHighs']);

        return $output;
    }

    /**
     * Get the CSS styles for the season highs tables.
     *
     * @return string CSS style block
     */
    private function getStyleBlock(): string
    {
        return '<style>
.season-highs-title {
    font-family: var(--font-display, \'Poppins\', -apple-system, BlinkMacSystemFont, sans-serif);
    font-size: 1.5rem;
    font-weight: 600;
    color: var(--navy-900, #0f172a);
    margin: 0 0 1.5rem 0;
}
.season-highs-container {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1.5rem;
    margin-bottom: 2rem;
}
@media (max-width: 1024px) {
    .season-highs-container {
        grid-template-columns: repeat(2, 1fr);
    }
}
@media (max-width: 640px) {
    .season-highs-container {
        grid-template-columns: 1fr;
    }
}
.stat-table {
    font-family: var(--font-sans, \'Inter\', -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif);
    border-collapse: separate;
    border-spacing: 0;
    border: none;
    border-radius: var(--radius-lg, 0.5rem);
    overflow: hidden;
    box-shadow: var(--shadow-md, 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1));
    width: 100%;
    font-size: 0.75rem;
}
.stat-table thead {
    background: linear-gradient(135deg, var(--navy-800, #1e293b), var(--navy-900, #0f172a));
}
.stat-table th {
    color: white;
    font-family: var(--font-display, \'Poppins\', -apple-system, BlinkMacSystemFont, sans-serif);
    font-weight: 600;
    font-size: 0.6875rem;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    padding: 0.625rem 0.5rem;
    text-align: center;
}
.stat-table th[colspan="4"] {
    font-size: 0.75rem;
    padding: 0.75rem 0.5rem;
}
.stat-table td {
    color: var(--gray-800, #1f2937);
    padding: 0.375rem 0.5rem;
    text-align: center;
}
.stat-table tbody tr {
    transition: background-color 150ms ease;
}
.stat-table tbody tr:nth-child(odd) {
    background-color: white;
}
.stat-table tbody tr:nth-child(even) {
    background-color: var(--gray-50, #f9fafb);
}
.stat-table tbody tr:hover {
    background-color: var(--gray-100, #f3f4f6);
}
.stat-table .rank-cell {
    font-weight: 600;
    color: var(--accent-600, #ea580c);
}
.stat-table .value-cell {
    font-weight: 600;
}
</style>';
    }

    /**
     * Render player season highs.
     *
     * @param string $seasonPhase Season phase
     * @param array $playerHighs Player highs data
     * @return string HTML output
     */
    private function renderPlayerHighs(string $seasonPhase, array $playerHighs): string
    {
        $output = '<h1 class="season-highs-title">Players\' ' . HtmlSanitizer::safeHtmlOutput($seasonPhase) . ' Highs</h1>';
        $output .= '<div class="season-highs-container">';

        foreach ($playerHighs as $statName => $stats) {
            $output .= $this->renderStatTable($statName, $stats);
        }

        $output .= '</div>';
        return $output;
    }

    /**
     * Render team season highs.
     *
     * @param string $seasonPhase Season phase
     * @param array $teamHighs Team highs data
     * @return string HTML output
     */
    private function renderTeamHighs(string $seasonPhase, array $teamHighs): string
    {
        $output = '<h1 class="season-highs-title">Teams\' ' . HtmlSanitizer::safeHtmlOutput($seasonPhase) . ' Highs</h1>';
        $output .= '<div class="season-highs-container">';

        foreach ($teamHighs as $statName => $stats) {
            $output .= $this->renderStatTable($statName, $stats);
        }

        $output .= '</div>';
        return $output;
    }

    /**
     * Render a single stat table.
     *
     * @param string $statName Stat name
     * @param array $stats Stat data
     * @return string HTML table
     */
    private function renderStatTable(string $statName, array $stats): string
    {
        $safeName = HtmlSanitizer::safeHtmlOutput($statName);

        $output = '<table class="stat-table">
            <thead>
                <tr><th colspan="4">' . $safeName . '</th></tr>
            </thead>
            <tbody>';

        foreach ($stats as $index => $row) {
            $rank = $index + 1;
            $name = HtmlSanitizer::safeHtmlOutput($row['name'] ?? '');
            $date = HtmlSanitizer::safeHtmlOutput($row['date'] ?? '');
            $value = (int) ($row['value'] ?? 0);

            $output .= "<tr>
    <td class=\"rank-cell\">{$rank}</td>
    <td>{$name}</td>
    <td>{$date}</td>
    <td class=\"value-cell\">{$value}</td>
</tr>";
        }

        $output .= '</tbody></table>';
        return $output;
    }
}
