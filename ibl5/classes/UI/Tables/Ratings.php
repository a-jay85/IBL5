<?php

namespace UI\Tables;

use Player\Player;

/**
 * Ratings - Displays player ratings table
 */
class Ratings
{
    /**
     * Render the ratings table
     *
     * @param object $db Database connection
     * @param iterable $data Player data (result set or array of Player objects)
     * @param object $team Team object
     * @param string $yr Year filter (empty for current season)
     * @param object $season Season object
     * @param string $moduleName Module name for styling variations
     * @return string HTML table
     */
    public static function render($db, $data, $team, string $yr, $season, string $moduleName = ""): string
    // TODO: simplify this by refactoring Player initialization logic out of this method
    {
        $playerRows = [];
        $i = 0;
        foreach ($data as $plrRow) {
            if ($yr == "") {
                if ($plrRow instanceof Player) {
                    $player = $plrRow;
                    if ($moduleName == "Next_Sim") {
                        $bgcolor = (($i % 2) == 0) ? "FFFFFF" : "FFFFAA";
                    } elseif ($moduleName == "League_Starters") {
                        $bgcolor = ($player->teamID == $team->teamID) ? "FFFFAA" : "FFFFFF";
                    } else {
                        $bgcolor = (($i % 2) == 0) ? "FFFFFF" : "EEEEEE";
                    }
                } elseif (is_array($data) AND $plrRow instanceof Player) {
                    $player = Player::withPlrRow($db, $plrRow);
                    $bgcolor = (($i % 2) == 0) ? "FFFFFF" : "EEEEEE";
                } elseif (is_array($plrRow)) {
                    $player = Player::withPlrRow($db, $plrRow);
                    $bgcolor = (($i % 2) == 0) ? "FFFFFF" : "EEEEEE";
                } else {
                    continue;
                }

                $firstCharacterOfPlayerName = substr($player->name, 0, 1);
                if ($firstCharacterOfPlayerName == '|') {
                    continue;
                }
            } else {
                $player = Player::withHistoricalPlrRow($db, $plrRow);
                $bgcolor = (($i % 2) == 0) ? "FFFFFF" : "EEEEEE";
            }

            $injuryInfo = $player->getInjuryReturnDate($season->lastSimEndDate);
            if ($injuryInfo != "") {
                $injuryInfo .= " ($player->daysRemainingForInjury days)";
            }

            $playerRows[] = [
                'player' => $player,
                'bgcolor' => $bgcolor,
                'injuryInfo' => $injuryInfo,
                'addSeparator' => (($i % 2) == 0 && $moduleName == "Next_Sim"),
            ];

            $i++;
        }

        ob_start();
        echo \UI\TableStyles::render('ratings', $team->color1, $team->color2);
        ?>
<table style="margin: 0 auto;" class="sortable ratings">
<colgroup span="2"></colgroup><colgroup span="2"></colgroup><colgroup span="6"></colgroup><colgroup span="6"></colgroup><colgroup span="4"></colgroup><colgroup span="4"></colgroup><colgroup span="1"></colgroup>
    <thead style="background-color: #<?= htmlspecialchars($team->color1) ?>;">
        <tr style="background-color: #<?= htmlspecialchars($team->color1) ?>;">
<?php if ($moduleName == "League_Starters"): ?>
            <th>Team</th>
<?php endif; ?>
            <th>Pos</th>
            <th>Player</th>
            <th>Age</th>
            <th class="sep-team"></th>
            <th>2ga</th>
            <th>2g%</th>
            <th class="sep-team"></th>
            <th>fta</th>
            <th>ft%</th>
            <th class="sep-team"></th>
            <th>3ga</th>
            <th>3g%</th>
            <th class="sep-team"></th>
            <th>orb</th>
            <th>drb</th>
            <th>ast</th>
            <th>stl</th>
            <th>tvr</th>
            <th>blk</th>
            <th>foul</th>
            <th class="sep-team"></th>
            <th>oo</th>
            <th>do</th>
            <th>po</th>
            <th>to</th>
            <th class="sep-team"></th>
            <th>od</th>
            <th>dd</th>
            <th>pd</th>
            <th>td</th>
            <th class="sep-team"></th>
            <th>Clu</th>
            <th>Con</th>
            <th class="sep-team"></th>
            <th>Injury Return Date</th>
        </tr>
    </thead>
    <tbody>
<?php foreach ($playerRows as $row):
    $player = $row['player'];
    // Column count: 35 base + 1 optional Team column = 36 max
    $colCount = ($moduleName == "League_Starters") ? 36 : 35;
    if ($row['addSeparator']): ?>
        <tr>
        <td colspan="<?= $colCount ?>" style="background-color: #<?= htmlspecialchars($team->color1) ?>;">
        </td>
        </tr>
<?php endif; ?>
        <tr style="background-color: #<?= $row['bgcolor'] ?>;">
<?php if ($moduleName == "League_Starters"): ?>
            <td><?= htmlspecialchars($player->teamName ?? '') ?></td>
<?php endif; ?>
            <td style="text-align: center;"><?= htmlspecialchars($player->position) ?></td>
            <td><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=<?= (int)$player->playerID ?>"><?= $player->decoratedName ?></a></td>
            <td style="text-align: center;"><?= (int)$player->age ?></td>
            <td class="sep-team"></td>
            <td style="text-align: center;"><?= (int)$player->ratingFieldGoalAttempts ?></td>
            <td style="text-align: center;"><?= (int)$player->ratingFieldGoalPercentage ?></td>
            <td class="sep-weak"></td>
            <td style="text-align: center;"><?= (int)$player->ratingFreeThrowAttempts ?></td>
            <td style="text-align: center;"><?= (int)$player->ratingFreeThrowPercentage ?></td>
            <td class="sep-weak"></td>
            <td style="text-align: center;"><?= (int)$player->ratingThreePointAttempts ?></td>
            <td style="text-align: center;"><?= (int)$player->ratingThreePointPercentage ?></td>
            <td class="sep-team"></td>
            <td style="text-align: center;"><?= (int)$player->ratingOffensiveRebounds ?></td>
            <td style="text-align: center;"><?= (int)$player->ratingDefensiveRebounds ?></td>
            <td style="text-align: center;"><?= (int)$player->ratingAssists ?></td>
            <td style="text-align: center;"><?= (int)$player->ratingSteals ?></td>
            <td style="text-align: center;"><?= (int)$player->ratingTurnovers ?></td>
            <td style="text-align: center;"><?= (int)$player->ratingBlocks ?></td>
            <td style="text-align: center;"><?= (int)$player->ratingFouls ?></td>
            <td class="sep-team"></td>
            <td style="text-align: center;"><?= (int)$player->ratingOutsideOffense ?></td>
            <td style="text-align: center;"><?= (int)$player->ratingDriveOffense ?></td>
            <td style="text-align: center;"><?= (int)$player->ratingPostOffense ?></td>
            <td style="text-align: center;"><?= (int)$player->ratingTransitionOffense ?></td>
            <td class="sep-weak"></td>
            <td style="text-align: center;"><?= (int)$player->ratingOutsideDefense ?></td>
            <td style="text-align: center;"><?= (int)$player->ratingDriveDefense ?></td>
            <td style="text-align: center;"><?= (int)$player->ratingPostDefense ?></td>
            <td style="text-align: center;"><?= (int)$player->ratingTransitionDefense ?></td>
            <td class="sep-team"></td>
            <td style="text-align: center;"><?= (int)$player->ratingClutch ?></td>
            <td style="text-align: center;"><?= (int)$player->ratingConsistency ?></td>
            <td class="sep-team"></td>
            <td style="text-align: center;"><?= htmlspecialchars($row['injuryInfo']) ?></td>
        </tr>
<?php endforeach; ?>
    </tbody>
</table>
        <?php
        return ob_get_clean();
    }
}
