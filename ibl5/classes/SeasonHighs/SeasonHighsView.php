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
     * Uses consolidated .ibl-data-table with layout-specific overrides.
     *
     * @return string CSS style block
     */
    private function getStyleBlock(): string
    {
        return ''; // All styles provided by .ibl-grid and .ibl-data-table
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
        $output = '<h1 class="ibl-table-title">Players\' ' . HtmlSanitizer::safeHtmlOutput($seasonPhase) . ' Highs</h1>';
        $output .= '<div class="ibl-grid ibl-grid--3col">';

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
        $output = '<h1 class="ibl-table-title">Teams\' ' . HtmlSanitizer::safeHtmlOutput($seasonPhase) . ' Highs</h1>';
        $output .= '<div class="ibl-grid ibl-grid--3col">';

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

        $output = '<table class="ibl-data-table stat-table">
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
