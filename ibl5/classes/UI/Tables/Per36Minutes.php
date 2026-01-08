<?php

namespace UI\Tables;

use Player\Player;
use Player\PlayerStats;
use BasketballStats\StatsFormatter;

/**
 * Per36Minutes - Displays per-36-minute statistics table
 */
class Per36Minutes
{
    /**
     * Render the per-36-minute statistics table
     *
     * @param object $db Database connection
     * @param iterable $result Player result set
     * @param object $team Team object
     * @param string $yr Year filter (empty for current season)
     * @return string HTML table
     */
    public static function render($db, $result, $team, string $yr): string
    {
        $playerRows = [];
        $i = 0;
        foreach ($result as $plrRow) {
            if ($yr == "") {
                $player = Player::withPlrRow($db, $plrRow);
                $playerStats = PlayerStats::withPlrRow($db, $plrRow);

                $firstCharacterOfPlayerName = substr($player->name, 0, 1);
                if ($firstCharacterOfPlayerName == '|') {
                    continue;
                }
            } else {
                $player = Player::withHistoricalPlrRow($db, $plrRow);
                $playerStats = PlayerStats::withHistoricalPlrRow($db, $plrRow);
            }

            $bgcolor = (($i % 2) == 0) ? "FFFFFF" : "EEEEEE";

            $playerRows[] = [
                'player' => $player,
                'playerStats' => $playerStats,
                'bgcolor' => $bgcolor,
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

            $i++;
        }

        ob_start();
        echo \UI\TableStyles::render('per36', $team->color1, $team->color2);
        ?>
<table style="margin: 0 auto;" class="sortable per36">
    <thead>
        <tr style="background-color: #<?= htmlspecialchars($team->color1) ?>;">
            <th>Pos</th>
            <th colspan="3">Player</th>
            <th>g</th>
            <th>gs</th>
            <th>mpg</th>
            <th>36min</th>
            <th class="sep-team"></th>
            <th>fgm</th>
            <th>fga</th>
            <th>fgp</th>
            <th class="sep-weak"></th>
            <th>ftm</th>
            <th>fta</th>
            <th>ftp</th>
            <th class="sep-weak"></th>
            <th>3gm</th>
            <th>3ga</th>
            <th>3gp</th>
            <th class="sep-team"></th>
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
    $player = $row['player'];
    $playerStats = $row['playerStats'];
?>
        <tr style="background-color: #<?= $row['bgcolor'] ?>;">
            <td><?= htmlspecialchars($player->position) ?></td>
            <td colspan="3"><a href="modules.php?name=Player&amp;pa=showpage&amp;pid=<?= (int)$player->playerID ?>"><?= $player->decoratedName ?></a></td>
            <td style="text-align: center;"><?= (int)$playerStats->seasonGamesPlayed ?></td>
            <td style="text-align: center;"><?= (int)$playerStats->seasonGamesStarted ?></td>
            <td style="text-align: center;"><?= $row['stats_mpg'] ?></td>
            <td style="text-align: center;"><?= $row['stats_per36Min'] ?></td>
            <td class="sep-team"></td>
            <td style="text-align: center;"><?= $row['stats_fgm'] ?></td>
            <td style="text-align: center;"><?= $row['stats_fga'] ?></td>
            <td style="text-align: center;"><?= $row['stats_fgp'] ?></td>
            <td class="sep-weak"></td>
            <td style="text-align: center;"><?= $row['stats_ftm'] ?></td>
            <td style="text-align: center;"><?= $row['stats_fta'] ?></td>
            <td style="text-align: center;"><?= $row['stats_ftp'] ?></td>
            <td class="sep-weak"></td>
            <td style="text-align: center;"><?= $row['stats_tgm'] ?></td>
            <td style="text-align: center;"><?= $row['stats_tga'] ?></td>
            <td style="text-align: center;"><?= $row['stats_tgp'] ?></td>
            <td class="sep-team"></td>
            <td style="text-align: center;"><?= $row['stats_opg'] ?></td>
            <td style="text-align: center;"><?= $row['stats_rpg'] ?></td>
            <td style="text-align: center;"><?= $row['stats_apg'] ?></td>
            <td style="text-align: center;"><?= $row['stats_spg'] ?></td>
            <td style="text-align: center;"><?= $row['stats_tpg'] ?></td>
            <td style="text-align: center;"><?= $row['stats_bpg'] ?></td>
            <td style="text-align: center;"><?= $row['stats_fpg'] ?></td>
            <td style="text-align: center;"><?= $row['stats_ppg'] ?></td>
        </tr>
<?php endforeach; ?>
    </tbody>
</table>
        <?php
        return ob_get_clean();
    }
}
