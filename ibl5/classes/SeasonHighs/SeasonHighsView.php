<?php

declare(strict_types=1);

namespace SeasonHighs;

use Player\PlayerImageHelper;
use SeasonHighs\Contracts\SeasonHighsServiceInterface;
use SeasonHighs\Contracts\SeasonHighsViewInterface;
use UI\TeamCellHelper;
use Utilities\HtmlSanitizer;

/**
 * View class for rendering season highs page.
 *
 * @phpstan-import-type SeasonHighEntry from SeasonHighsServiceInterface
 * @phpstan-import-type SeasonHighsData from SeasonHighsServiceInterface
 *
 * @see SeasonHighsViewInterface
 */
class SeasonHighsView implements SeasonHighsViewInterface
{
    /**
     * @see SeasonHighsViewInterface::render()
     *
     * @param SeasonHighsData $data
     */
    public function render(string $seasonPhase, array $data): string
    {
        /** @var string $safePhase */
        $safePhase = HtmlSanitizer::safeHtmlOutput($seasonPhase);
        $output = '<h2 class="ibl-title">Season Highs</h2>';
        $output .= $this->renderPlayerHighs($safePhase, $data['playerHighs']);
        $output .= $this->renderTeamHighs($safePhase, $data['teamHighs']);

        return $output;
    }

    /**
     * Render player season highs.
     *
     * @param string $seasonPhase Season phase (already HTML-escaped)
     * @param array<string, list<SeasonHighEntry>> $playerHighs Player highs data
     * @return string HTML output
     */
    private function renderPlayerHighs(string $seasonPhase, array $playerHighs): string
    {
        $output = '<h2 class="ibl-table-title">Players\' ' . $seasonPhase . ' Highs</h2>';
        $output .= '<div class="ibl-grid ibl-grid--3col">';

        foreach ($playerHighs as $statName => $stats) {
            $output .= $this->renderStatTable($statName, $stats, true);
        }

        $output .= '</div>';
        return $output;
    }

    /**
     * Render team season highs.
     *
     * @param string $seasonPhase Season phase (already HTML-escaped)
     * @param array<string, list<SeasonHighEntry>> $teamHighs Team highs data
     * @return string HTML output
     */
    private function renderTeamHighs(string $seasonPhase, array $teamHighs): string
    {
        $output = '<h2 class="ibl-table-title">Teams\' ' . $seasonPhase . ' Highs</h2>';
        $output .= '<div class="ibl-grid ibl-grid--3col">';

        foreach ($teamHighs as $statName => $stats) {
            $output .= $this->renderStatTable($statName, $stats, false);
        }

        $output .= '</div>';
        return $output;
    }

    /**
     * Render a single stat table.
     *
     * @param string $statName Stat name
     * @param list<SeasonHighEntry> $stats Stat data
     * @param bool $isPlayerStats Whether this is a player stats table (adds Team column)
     * @return string HTML table
     */
    private function renderStatTable(string $statName, array $stats, bool $isPlayerStats = false): string
    {
        /** @var string $safeName */
        $safeName = HtmlSanitizer::safeHtmlOutput($statName);

        // Use 5 columns for player stats (with Team), 4 columns for team stats
        $colCount = $isPlayerStats ? 5 : 4;
        $output = '<div class="stat-table-wrapper">
        <table class="ibl-data-table stat-table">
            <thead>
                <tr><th colspan="' . $colCount . '">' . $safeName . '</th></tr>
            </thead>
            <tbody>';

        foreach ($stats as $index => $row) {
            $rank = $index + 1;
            /** @var string $name */
            $name = HtmlSanitizer::safeHtmlOutput($row['name']);
            /** @var string $date */
            $date = HtmlSanitizer::safeHtmlOutput($row['date']);
            $value = $row['value'];
            $tid = 0;
            $teamCell = '';
            $isTeamStat = false;

            // Link player names to their profile page when pid is available
            if (isset($row['pid'])) {
                $pid = $row['pid'];
                $playerThumbnail = PlayerImageHelper::renderThumbnail($pid);
                /** @var string $name */
                $name = "<a href=\"modules.php?name=Player&amp;pa=showpage&amp;pid={$pid}\">{$playerThumbnail}{$name}</a>";

                // Build team cell for player stats
                if ($isPlayerStats) {
                    $tid = $row['tid'] ?? 0;
                    $teamCell = TeamCellHelper::renderTeamCellOrFreeAgent(
                        $tid,
                        $row['teamname'] ?? '',
                        $row['color1'] ?? 'FFFFFF',
                        $row['color2'] ?? '000000',
                        '',
                        'FA',
                    );
                }
            } elseif (isset($row['teamid'])) {
                // Style team names with colored cell for team stats
                $isTeamStat = true;
            }

            // Link dates to box score when boxId is available
            if (isset($row['boxId'])) {
                $boxId = (int) $row['boxId'];
                /** @var string $date */
                $date = "<a href=\"./ibl/IBL/box{$boxId}.htm\">{$date}</a>";
            }

            // Render row differently for team stats (styled team cell) vs player stats
            if ($isTeamStat) {
                $teamId = (int) ($row['teamid'] ?? 0);
                $teamStatCell = TeamCellHelper::renderTeamCell($teamId, $row['name'], $row['color1'] ?? 'FFFFFF', $row['color2'] ?? '000000');

                $output .= "<tr data-team-id=\"{$teamId}\">"
                    . "<td class=\"rank-cell\">{$rank}</td>"
                    . $teamStatCell
                    . "<td class=\"date-cell\">{$date}</td>"
                    . "<td class=\"value-cell\">{$value}</td>"
                    . '</tr>';
            } else {
                $output .= "<tr data-team-id=\"{$tid}\">
    <td class=\"rank-cell\">{$rank}</td>
    <td class=\"name-cell\">{$name}</td>
    {$teamCell}
    <td class=\"date-cell\">{$date}</td>
    <td class=\"value-cell\">{$value}</td>
</tr>";
            }
        }

        $output .= '</tbody></table></div>';
        return $output;
    }
}
