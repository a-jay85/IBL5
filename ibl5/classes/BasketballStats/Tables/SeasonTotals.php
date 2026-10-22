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
 * SeasonTotals - Displays season totals statistics table
 *
 * @phpstan-import-type TeamOffenseStatsRow from \TeamOffDefStats\Contracts\TeamOffDefStatsRepositoryInterface
 * @phpstan-import-type TeamDefenseStatsRow from \TeamOffDefStats\Contracts\TeamOffDefStatsRepositoryInterface
 */
class SeasonTotals
{
    /**
     * Render the season totals table
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
            <th>ftm</th>
            <th>fta</th>
            <th>3gm</th>
            <th class="sep-r-team">3ga</th>
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
     * One `<tbody>` row: the shared prefix, then the season-total stat cells.
     *
     * @param list<int> $starterPids
     */
    private static function renderPlayerRow(Player $player, PlayerStats $playerStats, array $starterPids, string $moduleName): string
    {
        return TeamStatsRowHelper::playerRowPrefix($player, $playerStats, $starterPids, $moduleName)
            . '            <td class="sep-r-team">' . $playerStats->seasonMinutes . "</td>\n"
            . '            <td>' . $playerStats->seasonFieldGoalsMade . "</td>\n"
            . '            <td class="sep-r-weak">' . $playerStats->seasonFieldGoalsAttempted . "</td>\n"
            . '            <td>' . $playerStats->seasonFreeThrowsMade . "</td>\n"
            . '            <td class="sep-r-weak">' . $playerStats->seasonFreeThrowsAttempted . "</td>\n"
            . '            <td>' . $playerStats->seasonThreePointersMade . "</td>\n"
            . '            <td class="sep-r-team">' . $playerStats->seasonThreePointersAttempted . "</td>\n"
            . '            <td>' . $playerStats->seasonOffensiveRebounds . "</td>\n"
            . '            <td>' . $playerStats->seasonTotalRebounds . "</td>\n"
            . '            <td>' . $playerStats->seasonAssists . "</td>\n"
            . '            <td>' . $playerStats->seasonSteals . "</td>\n"
            . '            <td>' . $playerStats->seasonTurnovers . "</td>\n"
            . '            <td>' . $playerStats->seasonBlocks . "</td>\n"
            . '            <td>' . $playerStats->seasonPersonalFouls . "</td>\n"
            . '            <td>' . $playerStats->seasonPoints . "</td>\n"
            . "        </tr>\n";
    }

    /**
     * One `<tfoot>` row: the shared label cells, then the season-total team stat cells.
     *
     * @param 'Offense'|'Defense' $label
     * @param TeamOffenseStatsRow|TeamDefenseStatsRow $stats
     */
    private static function renderTeamFooterRow(Team $team, string $label, array $stats, string $moduleName): string
    {
        $pts = StatsFormatter::calculatePoints($stats['fgm'], $stats['ftm'], $stats['tgm']);

        return TeamStatsRowHelper::footerLabelCells($team, $label, $stats['games'], $moduleName)
            . '            <td>' . StatsFormatter::formatTotal($stats['fgm']) . "</td>\n"
            . '            <td class="sep-r-weak">' . StatsFormatter::formatTotal($stats['fga']) . "</td>\n"
            . '            <td>' . StatsFormatter::formatTotal($stats['ftm']) . "</td>\n"
            . '            <td class="sep-r-weak">' . StatsFormatter::formatTotal($stats['fta']) . "</td>\n"
            . '            <td>' . StatsFormatter::formatTotal($stats['tgm']) . "</td>\n"
            . '            <td class="sep-r-team">' . StatsFormatter::formatTotal($stats['tga']) . "</td>\n"
            . '            <td>' . StatsFormatter::formatTotal($stats['orb']) . "</td>\n"
            . '            <td>' . StatsFormatter::formatTotal($stats['reb']) . "</td>\n"
            . '            <td>' . StatsFormatter::formatTotal($stats['ast']) . "</td>\n"
            . '            <td>' . StatsFormatter::formatTotal($stats['stl']) . "</td>\n"
            . '            <td>' . StatsFormatter::formatTotal($stats['tvr']) . "</td>\n"
            . '            <td>' . StatsFormatter::formatTotal($stats['blk']) . "</td>\n"
            . '            <td>' . StatsFormatter::formatTotal($stats['pf']) . "</td>\n"
            . '            <td>' . StatsFormatter::formatTotal($pts) . "</td>\n"
            . "        </tr>\n";
    }
}
