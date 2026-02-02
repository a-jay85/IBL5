<?php

declare(strict_types=1);

namespace UI\Tables;

use Player\Player;
use Player\PlayerImageHelper;
use Player\PlayerStats;

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
    public static function render($db, $result, $team, string $yr, array $starterPids = [], string $moduleName = ""): string
    {
        $playerRows = [];
        foreach ($result as $plrRow) {
            if ($yr == "") {
                if ($plrRow instanceof Player) {
                    $player = $plrRow;
                    $playerStats = PlayerStats::withPlayerID($db, (int) $player->playerID);
                } else {
                    $player = Player::withPlrRow($db, $plrRow);
                    $playerStats = PlayerStats::withPlrRow($db, $plrRow);
                }

                $firstCharacterOfPlayerName = substr($player->name, 0, 1);
                if ($firstCharacterOfPlayerName == '|') {
                    continue;
                }
            } else {
                $player = Player::withHistoricalPlrRow($db, $plrRow);
                $playerStats = PlayerStats::withHistoricalPlrRow($db, $plrRow);
            }

            $playerRows[] = [
                'player' => $player,
                'playerStats' => $playerStats,
            ];
        }

        $teamStats = \TeamStats::withTeamName($db, $team->name);

        ob_start();
        ?>
<table class="ibl-data-table team-table responsive-table sortable" style="<?= \UI\TableStyles::inlineVars($team->color1, $team->color2) ?>">
    <thead>
        <tr>
<?php if ($moduleName === "League_Starters"): ?>
            <th>Team</th>
<?php endif; ?>
            <th>Pos</th>
            <th class="sticky-col">Player</th>
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
        <tr>
<?php if ($moduleName === "League_Starters"):
    $teamId = (int) ($player->teamID ?? 0);
    $teamNameStr = htmlspecialchars($player->teamName ?? '');
    $color1 = htmlspecialchars($player->teamColor1 ?? 'FFFFFF');
    $color2 = htmlspecialchars($player->teamColor2 ?? '000000');
    if ($teamId === 0): ?>
            <td>Free Agent</td>
    <?php else: ?>
            <td class="ibl-team-cell--colored" style="background-color: #<?= $color1 ?>;">
        <a href="modules.php?name=Team&amp;op=team&amp;teamID=<?= $teamId ?>" class="ibl-team-cell__name" style="color: #<?= $color2 ?>;">
            <img src="images/logo/new<?= $teamId ?>.png" alt="" class="ibl-team-cell__logo" width="24" height="24" loading="lazy">
            <span class="ibl-team-cell__text"><?= $teamNameStr ?></span>
        </a>
    </td>
    <?php endif; ?>
<?php endif; ?>
            <td><?= htmlspecialchars($player->position) ?></td>
            <?= PlayerImageHelper::renderPlayerCell((int)$player->playerID, $player->decoratedName, $starterPids) ?>
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
<?php if ($yr == ""):
    $labelColspan = ($moduleName === "League_Starters") ? 3 : 2;
?>
        <tr>
            <td colspan="<?= $labelColspan ?>"><b><?= htmlspecialchars($team->name) ?> Offense</b></td>
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
            <td colspan="<?= $labelColspan ?>"><b><?= htmlspecialchars($team->name) ?> Defense</b></td>

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
