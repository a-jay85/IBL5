<?php

declare(strict_types=1);

namespace BasketballStats\Tables;

use BasketballStats\StatsFormatter;
use Player\Player;
use Player\Stats\PlayerStats;
use UI\Tables\PlayerRowTransformer;
use Security\HtmlSanitizer;
use Team\Team;

/**
 * SeasonAverages - Displays season averages statistics table
 *
 * @phpstan-import-type TeamOffenseStatsRow from \TeamOffDefStats\Contracts\TeamOffDefStatsRepositoryInterface
 * @phpstan-import-type TeamDefenseStatsRow from \TeamOffDefStats\Contracts\TeamOffDefStatsRepositoryInterface
 */
class SeasonAverages
{
    /**
     * Render the season averages table
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

        $bothStats = TeamStatsTableContext::resolveBothStats($db, $team, $yr);

        ob_start();
        // "\n" restores the template line break: PHP swallows the newline right after the closing PHP tag.
        // @phpstan-ignore ibl.trustedVariable (openTag() escapes the aria-label and sanitizes the colors)
        echo HtmlSanitizer::trusted(TeamStatsTableContext::openTag($team, $ariaLabel)), "\n";
        ?>
    <thead>
        <tr>
<?= TeamStatsRowHelper::headPrefix($moduleName) ?>
            <th class="sep-r-team">min</th>
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
    <tfoot>
<?php if ($bothStats !== null):
    echo self::renderTeamFooterRow($team, 'Offense', $bothStats['offense'], $moduleName);
    echo self::renderTeamFooterRow($team, 'Defense', $bothStats['defense'], $moduleName);
endif; ?>
    </tfoot>
</table>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * One `<tbody>` row: the shared prefix, then the per-game stat cells.
     *
     * @param list<int> $starterPids
     */
    private static function renderPlayerRow(Player $player, PlayerStats $playerStats, array $starterPids, string $moduleName): string
    {
        return TeamStatsRowHelper::playerRowPrefix($player, $playerStats, $starterPids, $moduleName)
            . '            <td class="sep-r-team">' . $playerStats->seasonMinutesPerGame . "</td>\n"
            . '            <td>' . $playerStats->seasonFieldGoalsMadePerGame . "</td>\n"
            . '            <td>' . $playerStats->seasonFieldGoalsAttemptedPerGame . "</td>\n"
            . '            <td class="sep-r-weak">' . $playerStats->seasonFieldGoalPercentage . "</td>\n"
            . '            <td>' . $playerStats->seasonFreeThrowsMadePerGame . "</td>\n"
            . '            <td>' . $playerStats->seasonFreeThrowsAttemptedPerGame . "</td>\n"
            . '            <td class="sep-r-weak">' . $playerStats->seasonFreeThrowPercentage . "</td>\n"
            . '            <td>' . $playerStats->seasonThreePointersMadePerGame . "</td>\n"
            . '            <td>' . $playerStats->seasonThreePointersAttemptedPerGame . "</td>\n"
            . '            <td class="sep-r-team">' . $playerStats->seasonThreePointPercentage . "</td>\n"
            . '            <td>' . $playerStats->seasonOffensiveReboundsPerGame . "</td>\n"
            . '            <td>' . $playerStats->seasonTotalReboundsPerGame . "</td>\n"
            . '            <td>' . $playerStats->seasonAssistsPerGame . "</td>\n"
            . '            <td>' . $playerStats->seasonStealsPerGame . "</td>\n"
            . '            <td>' . $playerStats->seasonTurnoversPerGame . "</td>\n"
            . '            <td>' . $playerStats->seasonBlocksPerGame . "</td>\n"
            . '            <td>' . $playerStats->seasonPersonalFoulsPerGame . "</td>\n"
            . '            <td>' . $playerStats->seasonPointsPerGame . "</td>\n"
            . "        </tr>\n";
    }

    /**
     * One `<tfoot>` row: the shared label cells, then the per-game team stat cells.
     *
     * @param 'Offense'|'Defense' $label
     * @param TeamOffenseStatsRow|TeamDefenseStatsRow $stats
     */
    private static function renderTeamFooterRow(Team $team, string $label, array $stats, string $moduleName): string
    {
        $games = $stats['games'];
        $pts = StatsFormatter::calculatePoints($stats['fgm'], $stats['ftm'], $stats['tgm']);

        return TeamStatsRowHelper::footerLabelCells($team, $label, $games, $moduleName)
            . '            <td>' . StatsFormatter::formatPerGameAverage($stats['fgm'], $games) . "</td>\n"
            . '            <td>' . StatsFormatter::formatPerGameAverage($stats['fga'], $games) . "</td>\n"
            . '            <td class="sep-r-weak">' . StatsFormatter::formatPercentage($stats['fgm'], $stats['fga']) . "</td>\n"
            . '            <td>' . StatsFormatter::formatPerGameAverage($stats['ftm'], $games) . "</td>\n"
            . '            <td>' . StatsFormatter::formatPerGameAverage($stats['fta'], $games) . "</td>\n"
            . '            <td class="sep-r-weak">' . StatsFormatter::formatPercentage($stats['ftm'], $stats['fta']) . "</td>\n"
            . '            <td>' . StatsFormatter::formatPerGameAverage($stats['tgm'], $games) . "</td>\n"
            . '            <td>' . StatsFormatter::formatPerGameAverage($stats['tga'], $games) . "</td>\n"
            . '            <td class="sep-r-team">' . StatsFormatter::formatPercentage($stats['tgm'], $stats['tga']) . "</td>\n"
            . '            <td>' . StatsFormatter::formatPerGameAverage($stats['orb'], $games) . "</td>\n"
            . '            <td>' . StatsFormatter::formatPerGameAverage($stats['reb'], $games) . "</td>\n"
            . '            <td>' . StatsFormatter::formatPerGameAverage($stats['ast'], $games) . "</td>\n"
            . '            <td>' . StatsFormatter::formatPerGameAverage($stats['stl'], $games) . "</td>\n"
            . '            <td>' . StatsFormatter::formatPerGameAverage($stats['tvr'], $games) . "</td>\n"
            . '            <td>' . StatsFormatter::formatPerGameAverage($stats['blk'], $games) . "</td>\n"
            . '            <td>' . StatsFormatter::formatPerGameAverage($stats['pf'], $games) . "</td>\n"
            . '            <td>' . StatsFormatter::formatPerGameAverage($pts, $games) . "</td>\n"
            . "        </tr>\n";
    }
}
