<?php

declare(strict_types=1);

namespace BasketballStats\Tables;

use BasketballStats\StatsFormatter;
use Player\Stats\PlayerStats;
use Player\Player;
use UI\Tables\PlayerRowTransformer;
use Security\HtmlSanitizer;
use Team\Team;

/**
 * Per36Minutes - Displays per-36-minute statistics table
 */
class Per36Minutes
{
    /**
     * Render the per-36-minute statistics table
     *
     * @param \mysqli $db Database connection
     * @param iterable<int, Player|array<string, mixed>> $result Player result set
     * @param Team $team Team object
     * @param string $yr Year filter (empty for current season)
     * @param list<int> $starterPids Starter player IDs
     * @param string $moduleName Module name
     * @param string $ariaLabel Optional aria-label for the table scroll region (empty = no attribute)
     * @return string HTML table
     */
    public static function render(\mysqli $db, $result, Team $team, string $yr, array $starterPids = [], string $moduleName = "", string $ariaLabel = ''): string
    {
        $playerRows = PlayerRowTransformer::resolveWithStats($db, $result, $yr);

        ob_start();
        // "\n" restores the template line break: PHP swallows the newline right after the closing PHP tag.
        // @phpstan-ignore ibl.trustedVariable (openTag() escapes the aria-label and sanitizes the colors)
        echo HtmlSanitizer::trusted(TeamStatsTableContext::openTag($team, $ariaLabel)), "\n";
        ?>
    <thead>
        <tr>
<?= TeamStatsRowHelper::headPrefix($moduleName) ?>
            <th>mpg</th>
            <th class="sep-r-team">36min</th>
            <th>fgm</th>
            <th>fga</th>
            <th>fgp</th>
            <th>ftm</th>
            <th>fta</th>
            <th>ftp</th>
            <th>3gm</th>
            <th>3ga</th>
            <th class="sep-r-team">3gp</th>
            <th>orb</th>
            <th>reb</th>
            <th>ast</th>
            <th>stl</th>
            <th>to</th>
            <th>blk</th>
            <th>pf</th>
            <th>pts</th>
        </tr>
    </thead>
    <tbody>
<?php foreach ($playerRows as $row):
    echo self::renderPlayerRow($row['player'], $row['playerStats'], $starterPids, $moduleName);
endforeach; ?>
    </tbody>
</table>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * One `<tbody>` row: the shared prefix, then minutes per game and the per-36 stat cells.
     *
     * @param list<int> $starterPids
     */
    private static function renderPlayerRow(Player $player, PlayerStats $playerStats, array $starterPids, string $moduleName): string
    {
        $minutes = $playerStats->seasonMinutes;

        return TeamStatsRowHelper::playerRowPrefix($player, $playerStats, $starterPids, $moduleName)
            . '            <td>' . StatsFormatter::formatPerGameAverage($minutes, $playerStats->seasonGamesPlayed) . "</td>\n"
            . '            <td class="sep-r-team">' . StatsFormatter::formatPer36Stat($minutes, $minutes) . "</td>\n"
            . '            <td>' . StatsFormatter::formatPer36Stat($playerStats->seasonFieldGoalsMade, $minutes) . "</td>\n"
            . '            <td>' . StatsFormatter::formatPer36Stat($playerStats->seasonFieldGoalsAttempted, $minutes) . "</td>\n"
            . '            <td class="sep-r-weak">' . StatsFormatter::formatPercentage($playerStats->seasonFieldGoalsMade, $playerStats->seasonFieldGoalsAttempted) . "</td>\n"
            . '            <td>' . StatsFormatter::formatPer36Stat($playerStats->seasonFreeThrowsMade, $minutes) . "</td>\n"
            . '            <td>' . StatsFormatter::formatPer36Stat($playerStats->seasonFreeThrowsAttempted, $minutes) . "</td>\n"
            . '            <td class="sep-r-weak">' . StatsFormatter::formatPercentage($playerStats->seasonFreeThrowsMade, $playerStats->seasonFreeThrowsAttempted) . "</td>\n"
            . '            <td>' . StatsFormatter::formatPer36Stat($playerStats->seasonThreePointersMade, $minutes) . "</td>\n"
            . '            <td>' . StatsFormatter::formatPer36Stat($playerStats->seasonThreePointersAttempted, $minutes) . "</td>\n"
            . '            <td class="sep-r-team">' . StatsFormatter::formatPercentage($playerStats->seasonThreePointersMade, $playerStats->seasonThreePointersAttempted) . "</td>\n"
            . '            <td>' . StatsFormatter::formatPer36Stat($playerStats->seasonOffensiveRebounds, $minutes) . "</td>\n"
            . '            <td>' . StatsFormatter::formatPer36Stat($playerStats->seasonTotalRebounds, $minutes) . "</td>\n"
            . '            <td>' . StatsFormatter::formatPer36Stat($playerStats->seasonAssists, $minutes) . "</td>\n"
            . '            <td>' . StatsFormatter::formatPer36Stat($playerStats->seasonSteals, $minutes) . "</td>\n"
            . '            <td>' . StatsFormatter::formatPer36Stat($playerStats->seasonTurnovers, $minutes) . "</td>\n"
            . '            <td>' . StatsFormatter::formatPer36Stat($playerStats->seasonBlocks, $minutes) . "</td>\n"
            . '            <td>' . StatsFormatter::formatPer36Stat($playerStats->seasonPersonalFouls, $minutes) . "</td>\n"
            . '            <td>' . StatsFormatter::formatPer36Stat($playerStats->seasonPoints, $minutes) . "</td>\n"
            . "        </tr>\n";
    }
}
