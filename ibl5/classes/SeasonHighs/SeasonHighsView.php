<?php

declare(strict_types=1);

namespace SeasonHighs;

use SeasonHighs\Contracts\SeasonHighsViewInterface;
use Player\PlayerImageHelper;
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
        $output = '<h2 class="ibl-title">Season Highs</h2>';
        $output .= $this->renderPlayerHighs($seasonPhase, $data['playerHighs']);
        $output .= $this->renderTeamHighs($seasonPhase, $data['teamHighs']);

        return $output;
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
            $output .= $this->renderStatTable($statName, $stats, true);
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
            $output .= $this->renderStatTable($statName, $stats, false);
        }

        $output .= '</div>';
        return $output;
    }

    /**
     * Render a single stat table.
     *
     * @param string $statName Stat name
     * @param array $stats Stat data
     * @param bool $isPlayerStats Whether this is a player stats table (adds Team column)
     * @return string HTML table
     */
    private function renderStatTable(string $statName, array $stats, bool $isPlayerStats = false): string
    {
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
            $name = HtmlSanitizer::safeHtmlOutput($row['name'] ?? '');
            $date = HtmlSanitizer::safeHtmlOutput($row['date'] ?? '');
            $value = (int) ($row['value'] ?? 0);
            $teamCell = '';
            $isTeamStat = false;

            // Link player names to their profile page when pid is available
            if (isset($row['pid'])) {
                $pid = (int) $row['pid'];
                $playerThumbnail = PlayerImageHelper::renderThumbnail($pid);
                $name = "<a href=\"modules.php?name=Player&amp;pa=showpage&amp;pid={$pid}\">{$playerThumbnail}{$name}</a>";

                // Build team cell for player stats
                if ($isPlayerStats) {
                    $tid = (int) ($row['tid'] ?? 0);
                    $teamName = HtmlSanitizer::safeHtmlOutput($row['teamname'] ?? '');
                    $color1 = HtmlSanitizer::safeHtmlOutput($row['color1'] ?? 'FFFFFF');
                    $color2 = HtmlSanitizer::safeHtmlOutput($row['color2'] ?? '000000');

                    if ($tid === 0) {
                        $teamCell = '<td>FA</td>';
                    } else {
                        $teamCell = "<td class=\"ibl-team-cell--colored\" style=\"background-color: #{$color1};\">
        <a href=\"modules.php?name=Team&amp;op=team&amp;teamID={$tid}\" class=\"ibl-team-cell__name\" style=\"color: #{$color2};\">
            <img src=\"images/logo/new{$tid}.png\" alt=\"\" class=\"ibl-team-cell__logo\" width=\"24\" height=\"24\" loading=\"lazy\">
            <span class=\"ibl-team-cell__text\">{$teamName}</span>
        </a>
    </td>";
                    }
                }
            } elseif (isset($row['teamid'])) {
                // Style team names with colored cell for team stats
                $teamId = (int) $row['teamid'];
                $color1 = HtmlSanitizer::safeHtmlOutput($row['color1'] ?? 'FFFFFF');
                $color2 = HtmlSanitizer::safeHtmlOutput($row['color2'] ?? '000000');

                // For team stats, we'll use a special flag to render differently
                $isTeamStat = true;
            }

            // Link dates to box score when boxId is available
            if (isset($row['boxId'])) {
                $boxId = (int) $row['boxId'];
                $date = "<a href=\"./ibl/IBL/box{$boxId}.htm\">{$date}</a>";
            }

            // Render row differently for team stats (styled team cell) vs player stats
            if ($isTeamStat) {
                $teamId = (int) $row['teamid'];
                $color1 = HtmlSanitizer::safeHtmlOutput($row['color1'] ?? 'FFFFFF');
                $color2 = HtmlSanitizer::safeHtmlOutput($row['color2'] ?? '000000');

                $output .= "<tr>
    <td class=\"rank-cell\">{$rank}</td>
    <td class=\"ibl-team-cell--colored\" style=\"background-color: #{$color1};\">
        <a href=\"modules.php?name=Team&amp;op=team&amp;teamID={$teamId}\" class=\"ibl-team-cell__name\" style=\"color: #{$color2};\">
            <img src=\"images/logo/new{$teamId}.png\" alt=\"\" class=\"ibl-team-cell__logo\" width=\"24\" height=\"24\" loading=\"lazy\">
            <span class=\"ibl-team-cell__text\">{$name}</span>
        </a>
    </td>
    <td class=\"date-cell\">{$date}</td>
    <td class=\"value-cell\">{$value}</td>
</tr>";
            } else {
                $output .= "<tr>
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
