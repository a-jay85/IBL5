<?php

namespace UI\Tables;

use Player\Player;

/**
 * SeasonTotals - Displays season totals statistics table
 */
class SeasonTotals
{
    /**
     * Render the season totals table
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
                $playerStats = \PlayerStats::withPlrRow($db, $plrRow);

                $firstCharacterOfPlayerName = substr($player->name, 0, 1);
                if ($firstCharacterOfPlayerName == '|') {
                    continue;
                }
            } else {
                $player = Player::withHistoricalPlrRow($db, $plrRow);
                $playerStats = \PlayerStats::withHistoricalPlrRow($db, $plrRow);
            }

            $bgcolor = (($i % 2) == 0) ? "FFFFFF" : "EEEEEE";

            $playerRows[] = [
                'player' => $player,
                'playerStats' => $playerStats,
                'bgcolor' => $bgcolor,
            ];

            $i++;
        }

        $teamStats = \TeamStats::withTeamName($db, $team->name);

        ob_start();
        echo \UI\TableStyles::render('season-totals', $team->color1, $team->color2);
        ?>
<table style="margin: 0 auto;" class="sortable season-totals">
    <thead>
        <tr style="background-color: #<?= htmlspecialchars($team->color1) ?>;">
            <th>Pos</th>
            <th colspan="3">Player</th>
            <th>g</th>
            <th>gs</th>
            <th>min</th>
            <th class="sep-team"></th>
            <th>fgm</th>
            <th>fga</th>
            <th class="sep-weak"></th>
            <th>ftm</th>
            <th>fta</th>
            <th class="sep-weak"></th>
            <th>3gm</th>
            <th>3ga</th>
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
            <td colspan="3"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=<?= (int)$player->playerID ?>"><?= $player->decoratedName ?></a></td>
            <td style="text-align: center;"><?= (int)$playerStats->seasonGamesPlayed ?></td>
            <td style="text-align: center;"><?= (int)$playerStats->seasonGamesStarted ?></td>
            <td style="text-align: center;"><?= (int)$playerStats->seasonMinutes ?></td>
            <td class="sep-team"></td>
            <td style="text-align: center;"><?= (int)$playerStats->seasonFieldGoalsMade ?></td>
            <td style="text-align: center;"><?= (int)$playerStats->seasonFieldGoalsAttempted ?></td>
            <td class="sep-weak"></td>
            <td style="text-align: center;"><?= (int)$playerStats->seasonFreeThrowsMade ?></td>
            <td style="text-align: center;"><?= (int)$playerStats->seasonFreeThrowsAttempted ?></td>
            <td class="sep-weak"></td>
            <td style="text-align: center;"><?= (int)$playerStats->seasonThreePointersMade ?></td>
            <td style="text-align: center;"><?= (int)$playerStats->seasonThreePointersAttempted ?></td>
            <td class="sep-team"></td>
            <td style="text-align: center;"><?= (int)$playerStats->seasonOffensiveRebounds ?></td>
            <td style="text-align: center;"><?= (int)$playerStats->seasonTotalRebounds ?></td>
            <td style="text-align: center;"><?= (int)$playerStats->seasonAssists ?></td>
            <td style="text-align: center;"><?= (int)$playerStats->seasonSteals ?></td>
            <td style="text-align: center;"><?= (int)$playerStats->seasonTurnovers ?></td>
            <td style="text-align: center;"><?= (int)$playerStats->seasonBlocks ?></td>
            <td style="text-align: center;"><?= (int)$playerStats->seasonPersonalFouls ?></td>
            <td style="text-align: center;"><?= (int)$playerStats->seasonPoints ?></td>
        </tr>
<?php endforeach; ?>
    </tbody>
    <tfoot>
<?php if ($yr == ""): ?>
        <tr>
            <td colspan="4"><b><?= htmlspecialchars($team->name) ?> Offense</b></td>
            <td style="text-align: center;"><b><?= (int)$teamStats->seasonOffenseGamesPlayed ?></b></td>
            <td style="text-align: center;"><b><?= (int)$teamStats->seasonOffenseGamesPlayed ?></b></td>
            <td></td>
            <td class="sep-team"></td>
            <td style="text-align: center;"><b><?= (int)$teamStats->seasonOffenseTotalFieldGoalsMade ?></b></td>
            <td style="text-align: center;"><b><?= (int)$teamStats->seasonOffenseTotalFieldGoalsAttempted ?></b></td>
            <td class="sep-weak"></td>
            <td style="text-align: center;"><b><?= (int)$teamStats->seasonOffenseTotalFreeThrowsMade ?></b></td>
            <td style="text-align: center;"><b><?= (int)$teamStats->seasonOffenseTotalFreeThrowsAttempted ?></b></td>
            <td class="sep-weak"></td>
            <td style="text-align: center;"><b><?= (int)$teamStats->seasonOffenseTotalThreePointersMade ?></b></td>
            <td style="text-align: center;"><b><?= (int)$teamStats->seasonOffenseTotalThreePointersAttempted ?></b></td>
            <td class="sep-team"></td>
            <td style="text-align: center;"><b><?= (int)$teamStats->seasonOffenseTotalOffensiveRebounds ?></b></td>
            <td style="text-align: center;"><b><?= (int)$teamStats->seasonOffenseTotalRebounds ?></b></td>
            <td style="text-align: center;"><b><?= (int)$teamStats->seasonOffenseTotalAssists ?></b></td>
            <td style="text-align: center;"><b><?= (int)$teamStats->seasonOffenseTotalSteals ?></b></td>
            <td style="text-align: center;"><b><?= (int)$teamStats->seasonOffenseTotalTurnovers ?></b></td>
            <td style="text-align: center;"><b><?= (int)$teamStats->seasonOffenseTotalBlocks ?></b></td>
            <td style="text-align: center;"><b><?= (int)$teamStats->seasonOffenseTotalPersonalFouls ?></b></td>
            <td style="text-align: center;"><b><?= (int)$teamStats->seasonOffenseTotalPoints ?></b></td>
        </tr>
        <tr>
            <td colspan="4"><b><?= htmlspecialchars($team->name) ?> Defense</b></td>
            <td style="text-align: center;"><b><?= (int)$teamStats->seasonDefenseGamesPlayed ?></b></td>
            <td style="text-align: center;"><b><?= (int)$teamStats->seasonDefenseGamesPlayed ?></b></td>
            <td></td>
            <td class="sep-team"></td>
            <td style="text-align: center;"><b><?= (int)$teamStats->seasonDefenseTotalFieldGoalsMade ?></b></td>
            <td style="text-align: center;"><b><?= (int)$teamStats->seasonDefenseTotalFieldGoalsAttempted ?></b></td>
            <td class="sep-weak"></td>
            <td style="text-align: center;"><b><?= (int)$teamStats->seasonDefenseTotalFreeThrowsMade ?></b></td>
            <td style="text-align: center;"><b><?= (int)$teamStats->seasonDefenseTotalFreeThrowsAttempted ?></b></td>
            <td class="sep-weak"></td>
            <td style="text-align: center;"><b><?= (int)$teamStats->seasonDefenseTotalThreePointersMade ?></b></td>
            <td style="text-align: center;"><b><?= (int)$teamStats->seasonDefenseTotalThreePointersAttempted ?></b></td>
            <td class="sep-team"></td>
            <td style="text-align: center;"><b><?= (int)$teamStats->seasonDefenseTotalOffensiveRebounds ?></b></td>
            <td style="text-align: center;"><b><?= (int)$teamStats->seasonDefenseTotalRebounds ?></b></td>
            <td style="text-align: center;"><b><?= (int)$teamStats->seasonDefenseTotalAssists ?></b></td>
            <td style="text-align: center;"><b><?= (int)$teamStats->seasonDefenseTotalSteals ?></b></td>
            <td style="text-align: center;"><b><?= (int)$teamStats->seasonDefenseTotalTurnovers ?></b></td>
            <td style="text-align: center;"><b><?= (int)$teamStats->seasonDefenseTotalBlocks ?></b></td>
            <td style="text-align: center;"><b><?= (int)$teamStats->seasonDefenseTotalPersonalFouls ?></b></td>
            <td style="text-align: center;"><b><?= (int)$teamStats->seasonDefenseTotalPoints ?></b></td>
        </tr>
<?php endif; ?>
    </tfoot>
</table>
        <?php
        return ob_get_clean();
    }
}
