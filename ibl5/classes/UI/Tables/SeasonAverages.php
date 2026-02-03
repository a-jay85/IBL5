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
<?php if ($moduleName === "LeagueStarters"): ?>
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
            <th class="sep-team"></th>
            <th>ftm</th>
            <th>fta</th>
            <th>ftp</th>
            <th class="sep-team"></th>
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
<?php if ($moduleName === "LeagueStarters"):
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
    $labelColspan = ($moduleName === "LeagueStarters") ? 3 : 2;
?>
        <tr>
            <td colspan="<?= $labelColspan ?>"><?= htmlspecialchars($team->name) ?> Offense</td>
            <td><?= (int)$teamStats->seasonOffenseGamesPlayed ?></td>
            <td><?= (int)$teamStats->seasonOffenseGamesPlayed ?></td>
            <td></td>
            <td class="sep-team"></td>
            <td><?= $teamStats->seasonOffenseFieldGoalsMadePerGame ?></td>
            <td><?= $teamStats->seasonOffenseFieldGoalsAttemptedPerGame ?></td>
            <td><?= $teamStats->seasonOffenseFieldGoalPercentage ?></td>
            <td class="sep-weak"></td>
            <td><?= $teamStats->seasonOffenseFreeThrowsMadePerGame ?></td>
            <td><?= $teamStats->seasonOffenseFreeThrowsAttemptedPerGame ?></td>
            <td><?= $teamStats->seasonOffenseFreeThrowPercentage ?></td>
            <td class="sep-weak"></td>
            <td><?= $teamStats->seasonOffenseThreePointersMadePerGame ?></td>
            <td><?= $teamStats->seasonOffenseThreePointersAttemptedPerGame ?></td>
            <td><?= $teamStats->seasonOffenseThreePointPercentage ?></td>
            <td class="sep-team"></td>
            <td><?= $teamStats->seasonOffenseOffensiveReboundsPerGame ?></td>
            <td><?= $teamStats->seasonOffenseTotalReboundsPerGame ?></td>
            <td><?= $teamStats->seasonOffenseAssistsPerGame ?></td>
            <td><?= $teamStats->seasonOffenseStealsPerGame ?></td>
            <td><?= $teamStats->seasonOffenseTurnoversPerGame ?></td>
            <td><?= $teamStats->seasonOffenseBlocksPerGame ?></td>
            <td><?= $teamStats->seasonOffensePersonalFoulsPerGame ?></td>
            <td><?= $teamStats->seasonOffensePointsPerGame ?></td>
        </tr>
        <tr>
            <td colspan="<?= $labelColspan ?>"><?= htmlspecialchars($team->name) ?> Defense</td>
            <td><?= (int)$teamStats->seasonDefenseGamesPlayed ?></td>
            <td><?= (int)$teamStats->seasonDefenseGamesPlayed ?></td>
            <td></td>
            <td class="sep-team"></td>
            <td><?= $teamStats->seasonDefenseFieldGoalsMadePerGame ?></td>
            <td><?= $teamStats->seasonDefenseFieldGoalsAttemptedPerGame ?></td>
            <td><?= $teamStats->seasonDefenseFieldGoalPercentage ?></td>
            <td class="sep-weak"></td>
            <td><?= $teamStats->seasonDefenseFreeThrowsMadePerGame ?></td>
            <td><?= $teamStats->seasonDefenseFreeThrowsAttemptedPerGame ?></td>
            <td><?= $teamStats->seasonDefenseFreeThrowPercentage ?></td>
            <td class="sep-weak"></td>
            <td><?= $teamStats->seasonDefenseThreePointersMadePerGame ?></td>
            <td><?= $teamStats->seasonDefenseThreePointersAttemptedPerGame ?></td>
            <td><?= $teamStats->seasonDefenseThreePointPercentage ?></td>
            <td class="sep-team"></td>
            <td><?= $teamStats->seasonDefenseOffensiveReboundsPerGame ?></td>
            <td><?= $teamStats->seasonDefenseTotalReboundsPerGame ?></td>
            <td><?= $teamStats->seasonDefenseAssistsPerGame ?></td>
            <td><?= $teamStats->seasonDefenseStealsPerGame ?></td>
            <td><?= $teamStats->seasonDefenseTurnoversPerGame ?></td>
            <td><?= $teamStats->seasonDefenseBlocksPerGame ?></td>
            <td><?= $teamStats->seasonDefensePersonalFoulsPerGame ?></td>
            <td><?= $teamStats->seasonDefensePointsPerGame ?></td>
        </tr>
<?php endif; ?>
    </tfoot>
</table>
        <?php
        return ob_get_clean();
    }
}
