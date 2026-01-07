<?php

namespace UI\Tables;

use Player\Player;
use Player\PlayerStats;

/**
 * SeasonAverages - Displays season averages statistics table
 */
class SeasonAverages
{
    /**
     * Render the season averages table
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
            ];

            $i++;
        }

        $teamStats = \TeamStats::withTeamName($db, $team->name);

        ob_start();
        echo \UI\TableStyles::render('season-avg', $team->color1, $team->color2);
        ?>
<table style="margin: 0 auto;" class="sortable season-avg">
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
            <td style="text-align: center;"><?= $playerStats->seasonMinutesPerGame ?></td>
            <td class="sep-team"></td>
            <td style="text-align: center;"><?= $playerStats->seasonFieldGoalsMadePerGame ?></td>
            <td style="text-align: center;"><?= $playerStats->seasonFieldGoalsAttemptedPerGame ?></td>
            <td style="text-align: center;"><?= $playerStats->seasonFieldGoalPercentage ?></td>
            <td class="sep-weak"></td>
            <td style="text-align: center;"><?= $playerStats->seasonFreeThrowsMadePerGame ?></td>
            <td style="text-align: center;"><?= $playerStats->seasonFreeThrowsAttemptedPerGame ?></td>
            <td style="text-align: center;"><?= $playerStats->seasonFreeThrowPercentage ?></td>
            <td class="sep-weak"></td>
            <td style="text-align: center;"><?= $playerStats->seasonThreePointersMadePerGame ?></td>
            <td style="text-align: center;"><?= $playerStats->seasonThreePointersAttemptedPerGame ?></td>
            <td style="text-align: center;"><?= $playerStats->seasonThreePointPercentage ?></td>
            <td class="sep-team"></td>
            <td style="text-align: center;"><?= $playerStats->seasonOffensiveReboundsPerGame ?></td>
            <td style="text-align: center;"><?= $playerStats->seasonTotalReboundsPerGame ?></td>
            <td style="text-align: center;"><?= $playerStats->seasonAssistsPerGame ?></td>
            <td style="text-align: center;"><?= $playerStats->seasonStealsPerGame ?></td>
            <td style="text-align: center;"><?= $playerStats->seasonTurnoversPerGame ?></td>
            <td style="text-align: center;"><?= $playerStats->seasonBlocksPerGame ?></td>
            <td style="text-align: center;"><?= $playerStats->seasonPersonalFoulsPerGame ?></td>
            <td style="text-align: center;"><?= $playerStats->seasonPointsPerGame ?></td>
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
            <td style="text-align: center;"><b><?= $teamStats->seasonOffenseFieldGoalsMadePerGame ?></b></td>
            <td style="text-align: center;"><b><?= $teamStats->seasonOffenseFieldGoalsAttemptedPerGame ?></b></td>
            <td style="text-align: center;"><b><?= $teamStats->seasonOffenseFieldGoalPercentage ?></b></td>
            <td class="sep-weak"></td>
            <td style="text-align: center;"><b><?= $teamStats->seasonOffenseFreeThrowsMadePerGame ?></b></td>
            <td style="text-align: center;"><b><?= $teamStats->seasonOffenseFreeThrowsAttemptedPerGame ?></b></td>
            <td style="text-align: center;"><b><?= $teamStats->seasonOffenseFreeThrowPercentage ?></b></td>
            <td class="sep-weak"></td>
            <td style="text-align: center;"><b><?= $teamStats->seasonOffenseThreePointersMadePerGame ?></b></td>
            <td style="text-align: center;"><b><?= $teamStats->seasonOffenseThreePointersAttemptedPerGame ?></b></td>
            <td style="text-align: center;"><b><?= $teamStats->seasonOffenseThreePointPercentage ?></b></td>
            <td class="sep-team"></td>
            <td style="text-align: center;"><b><?= $teamStats->seasonOffenseOffensiveReboundsPerGame ?></b></td>
            <td style="text-align: center;"><b><?= $teamStats->seasonOffenseTotalReboundsPerGame ?></b></td>
            <td style="text-align: center;"><b><?= $teamStats->seasonOffenseAssistsPerGame ?></b></td>
            <td style="text-align: center;"><b><?= $teamStats->seasonOffenseStealsPerGame ?></b></td>
            <td style="text-align: center;"><b><?= $teamStats->seasonOffenseTurnoversPerGame ?></b></td>
            <td style="text-align: center;"><b><?= $teamStats->seasonOffenseBlocksPerGame ?></b></td>
            <td style="text-align: center;"><b><?= $teamStats->seasonOffensePersonalFoulsPerGame ?></b></td>
            <td style="text-align: center;"><b><?= $teamStats->seasonOffensePointsPerGame ?></b></td>
        </tr>
        <tr>
            <td colspan="4"><b><?= htmlspecialchars($team->name) ?> Defense</b></td>
            <td style="text-align: center;"><b><?= (int)$teamStats->seasonDefenseGamesPlayed ?></b></td>
            <td style="text-align: center;"><b><?= (int)$teamStats->seasonDefenseGamesPlayed ?></b></td>
            <td></td>
            <td class="sep-team"></td>
            <td style="text-align: center;"><b><?= $teamStats->seasonDefenseFieldGoalsMadePerGame ?></b></td>
            <td style="text-align: center;"><b><?= $teamStats->seasonDefenseFieldGoalsAttemptedPerGame ?></b></td>
            <td style="text-align: center;"><b><?= $teamStats->seasonDefenseFieldGoalPercentage ?></b></td>
            <td class="sep-weak"></td>
            <td style="text-align: center;"><b><?= $teamStats->seasonDefenseFreeThrowsMadePerGame ?></b></td>
            <td style="text-align: center;"><b><?= $teamStats->seasonDefenseFreeThrowsAttemptedPerGame ?></b></td>
            <td style="text-align: center;"><b><?= $teamStats->seasonDefenseFreeThrowPercentage ?></b></td>
            <td class="sep-weak"></td>
            <td style="text-align: center;"><b><?= $teamStats->seasonDefenseThreePointersMadePerGame ?></b></td>
            <td style="text-align: center;"><b><?= $teamStats->seasonDefenseThreePointersAttemptedPerGame ?></b></td>
            <td style="text-align: center;"><b><?= $teamStats->seasonDefenseThreePointPercentage ?></b></td>
            <td class="sep-team"></td>
            <td style="text-align: center;"><b><?= $teamStats->seasonDefenseOffensiveReboundsPerGame ?></b></td>
            <td style="text-align: center;"><b><?= $teamStats->seasonDefenseTotalReboundsPerGame ?></b></td>
            <td style="text-align: center;"><b><?= $teamStats->seasonDefenseAssistsPerGame ?></b></td>
            <td style="text-align: center;"><b><?= $teamStats->seasonDefenseStealsPerGame ?></b></td>
            <td style="text-align: center;"><b><?= $teamStats->seasonDefenseTurnoversPerGame ?></b></td>
            <td style="text-align: center;"><b><?= $teamStats->seasonDefenseBlocksPerGame ?></b></td>
            <td style="text-align: center;"><b><?= $teamStats->seasonDefensePersonalFoulsPerGame ?></b></td>
            <td style="text-align: center;"><b><?= $teamStats->seasonDefensePointsPerGame ?></b></td>
        </tr>
<?php endif; ?>
    </tfoot>
</table>
        <?php
        return ob_get_clean();
    }
}
