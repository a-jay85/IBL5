<?php

declare(strict_types=1);

namespace UI\Tables;

use BasketballStats\StatsFormatter;
use Player\PlayerStats;
use Player\Player;
use Player\PlayerImageHelper;
use UI\TeamCellHelper;
use Utilities\HtmlSanitizer;

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
     * @param \Team $team Team object
     * @param string $yr Year filter (empty for current season)
     * @param list<int> $starterPids Starter player IDs
     * @param string $moduleName Module name
     * @return string HTML table
     */
    public static function render(\mysqli $db, $result, \Team $team, string $yr, array $starterPids = [], string $moduleName = ""): string
    {
        $resolvedRows = PlayerRowTransformer::resolveWithStats($db, $result, $yr);

        /** @var list<array{player: Player, playerStats: PlayerStats, stats_fgm: string, stats_fga: string, stats_fgp: string, stats_ftm: string, stats_fta: string, stats_ftp: string, stats_tgm: string, stats_tga: string, stats_tgp: string, stats_mpg: string, stats_per36Min: string, stats_opg: string, stats_rpg: string, stats_apg: string, stats_spg: string, stats_tpg: string, stats_bpg: string, stats_fpg: string, stats_ppg: string}> $playerRows */
        $playerRows = [];
        foreach ($resolvedRows as $row) {
            $playerStats = $row['playerStats'];
            $playerRows[] = [
                ...$row,
                'stats_fgm' => StatsFormatter::formatPer36Stat($playerStats->seasonFieldGoalsMade, $playerStats->seasonMinutes),
                'stats_fga' => StatsFormatter::formatPer36Stat($playerStats->seasonFieldGoalsAttempted, $playerStats->seasonMinutes),
                'stats_fgp' => StatsFormatter::formatPercentage($playerStats->seasonFieldGoalsMade, $playerStats->seasonFieldGoalsAttempted),
                'stats_ftm' => StatsFormatter::formatPer36Stat($playerStats->seasonFreeThrowsMade, $playerStats->seasonMinutes),
                'stats_fta' => StatsFormatter::formatPer36Stat($playerStats->seasonFreeThrowsAttempted, $playerStats->seasonMinutes),
                'stats_ftp' => StatsFormatter::formatPercentage($playerStats->seasonFreeThrowsMade, $playerStats->seasonFreeThrowsAttempted),
                'stats_tgm' => StatsFormatter::formatPer36Stat($playerStats->seasonThreePointersMade, $playerStats->seasonMinutes),
                'stats_tga' => StatsFormatter::formatPer36Stat($playerStats->seasonThreePointersAttempted, $playerStats->seasonMinutes),
                'stats_tgp' => StatsFormatter::formatPercentage($playerStats->seasonThreePointersMade, $playerStats->seasonThreePointersAttempted),
                'stats_mpg' => StatsFormatter::formatPerGameAverage($playerStats->seasonMinutes, $playerStats->seasonGamesPlayed),
                'stats_per36Min' => StatsFormatter::formatPer36Stat($playerStats->seasonMinutes, $playerStats->seasonMinutes),
                'stats_opg' => StatsFormatter::formatPer36Stat($playerStats->seasonOffensiveRebounds, $playerStats->seasonMinutes),
                'stats_rpg' => StatsFormatter::formatPer36Stat($playerStats->seasonTotalRebounds, $playerStats->seasonMinutes),
                'stats_apg' => StatsFormatter::formatPer36Stat($playerStats->seasonAssists, $playerStats->seasonMinutes),
                'stats_spg' => StatsFormatter::formatPer36Stat($playerStats->seasonSteals, $playerStats->seasonMinutes),
                'stats_tpg' => StatsFormatter::formatPer36Stat($playerStats->seasonTurnovers, $playerStats->seasonMinutes),
                'stats_bpg' => StatsFormatter::formatPer36Stat($playerStats->seasonBlocks, $playerStats->seasonMinutes),
                'stats_fpg' => StatsFormatter::formatPer36Stat($playerStats->seasonPersonalFouls, $playerStats->seasonMinutes),
                'stats_ppg' => StatsFormatter::formatPer36Stat($playerStats->seasonPoints, $playerStats->seasonMinutes),
            ];
        }

        ob_start();
        ?>
<table class="ibl-data-table team-table responsive-table sortable" style="<?= \UI\TableStyles::inlineVars($team->color1, $team->color2) ?>">
    <thead>
        <tr>
<?php if ($moduleName === "LeagueStarters"): ?>
            <th>Team</th>
<?php endif; ?>
            <th>Pos</th>
            <th class="sticky-col">Player</th>
            <th>g</th>
            <th>gs</th>
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
    /** @var Player $player */
    $player = $row['player'];
    /** @var PlayerStats $playerStats */
    $playerStats = $row['playerStats'];
?>
        <tr>
<?php if ($moduleName === "LeagueStarters"):
    echo TeamCellHelper::renderTeamCellOrFreeAgent($player->teamID ?? 0, $player->teamName ?? '', $player->teamColor1 ?? 'FFFFFF', $player->teamColor2 ?? '000000');
endif; ?>
            <td><?= HtmlSanitizer::e($player->position ?? '') ?></td>
            <?= PlayerImageHelper::renderPlayerCell($player->playerID ?? 0, $player->decoratedName ?? '', $starterPids, $player->nameStatusClass) ?>
            <td><?= $playerStats->seasonGamesPlayed ?></td>
            <td><?= $playerStats->seasonGamesStarted ?></td>
            <td><?= $row['stats_mpg'] ?></td>
            <td class="sep-r-team"><?= $row['stats_per36Min'] ?></td>
            <td><?= $row['stats_fgm'] ?></td>
            <td><?= $row['stats_fga'] ?></td>
            <td class="sep-r-weak"><?= $row['stats_fgp'] ?></td>
            <td><?= $row['stats_ftm'] ?></td>
            <td><?= $row['stats_fta'] ?></td>
            <td class="sep-r-weak"><?= $row['stats_ftp'] ?></td>
            <td><?= $row['stats_tgm'] ?></td>
            <td><?= $row['stats_tga'] ?></td>
            <td class="sep-r-team"><?= $row['stats_tgp'] ?></td>
            <td><?= $row['stats_opg'] ?></td>
            <td><?= $row['stats_rpg'] ?></td>
            <td><?= $row['stats_apg'] ?></td>
            <td><?= $row['stats_spg'] ?></td>
            <td><?= $row['stats_tpg'] ?></td>
            <td><?= $row['stats_bpg'] ?></td>
            <td><?= $row['stats_fpg'] ?></td>
            <td><?= $row['stats_ppg'] ?></td>
        </tr>
<?php endforeach; ?>
    </tbody>
</table>
        <?php
        return (string) ob_get_clean();
    }
}
