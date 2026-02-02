<?php

declare(strict_types=1);

namespace UI\Tables;

use Player\Player;
use Player\PlayerImageHelper;
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
<?php if ($yr == ""):
    $labelColspan = ($moduleName === "League_Starters") ? 3 : 2;
?>
        <tr>
            <td colspan="<?= $labelColspan ?>"><b><?= htmlspecialchars($team->name) ?> Offense</b></td>
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
            <td colspan="<?= $labelColspan ?>"><b><?= htmlspecialchars($team->name) ?> Defense</b></td>
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
