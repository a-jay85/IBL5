<?php

declare(strict_types=1);

namespace UI\Tables;

use Player\Player;
use Player\PlayerImageHelper;

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
    public static function render($db, $data, $team, string $yr, $season, string $moduleName = "", array $starterPids = []): string
    // TODO: simplify this by refactoring Player initialization logic out of this method
    {
        $playerRows = [];
        $i = 0;
        foreach ($data as $plrRow) {
            if ($yr == "") {
                if ($plrRow instanceof Player) {
                    $player = $plrRow;
                    if ($moduleName == "Next_Sim") {
                        $isHighlight = (($i % 2) !== 0);
                    } elseif ($moduleName == "League_Starters") {
                        $isHighlight = ($player->teamID == $team->teamID);
                    } else {
                        $isHighlight = false;
                    }
                } elseif (is_array($data) AND $plrRow instanceof Player) {
                    $player = Player::withPlrRow($db, $plrRow);
                    $isHighlight = false;
                } elseif (is_array($plrRow)) {
                    $player = Player::withPlrRow($db, $plrRow);
                    $isHighlight = false;
                } else {
                    continue;
                }

                $firstCharacterOfPlayerName = substr($player->name, 0, 1);
                if ($firstCharacterOfPlayerName == '|') {
                    continue;
                }
            } else {
                $player = Player::withHistoricalPlrRow($db, $plrRow);
                $isHighlight = false;
            }

            $injuryReturnDate = $player->getInjuryReturnDate($season->lastSimEndDate);
            $injuryDays = $player->daysRemainingForInjury;

            $playerRows[] = [
                'player' => $player,
                'isHighlight' => $isHighlight,
                'injuryDays' => $injuryDays,
                'injuryReturnDate' => $injuryReturnDate,
                'addSeparator' => (($i % 2) === 0 && $moduleName === "Next_Sim" && $i > 0),
            ];

            $i++;
        }

        ob_start();
        ?>
<table class="ibl-data-table team-table responsive-table sortable" style="<?= \UI\TableStyles::inlineVars($team->color1, $team->color2) ?>">
<colgroup span="2"></colgroup><colgroup span="2"></colgroup><colgroup span="6"></colgroup><colgroup span="6"></colgroup><colgroup span="4"></colgroup><colgroup span="4"></colgroup><colgroup span="1"></colgroup>
    <thead>
        <tr>
<?php if ($moduleName == "League_Starters"): ?>
            <th>Team</th>
<?php endif; ?>
            <th class="sticky-col">Player</th>
            <th>Pos</th>
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
            <th>Days Injured</th>
        </tr>
    </thead>
    <tbody>
<?php foreach ($playerRows as $row):
    $player = $row['player'];
    // Column count: 35 base + 1 optional Team column = 36 max
    $colCount = ($moduleName == "League_Starters") ? 36 : 35;
    if ($row['addSeparator']): ?>
        <tr class="ratings-separator">
        <td colspan="<?= $colCount ?>" style="background-color: var(--team-color-primary); height: 3px; padding: 0;">
        </td>
        </tr>
<?php endif; ?>
        <tr<?= $row['isHighlight'] ? ' class="ratings-highlight"' : '' ?>>
<?php if ($moduleName == "League_Starters"):
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
            <td class="sticky-col ibl-player-cell<?= in_array((int)$player->playerID, $starterPids, true) ? ' is-starter' : '' ?>" style="white-space: nowrap;"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=<?= (int)$player->playerID ?>"><?= PlayerImageHelper::renderThumbnail((int)$player->playerID) ?><?= $player->decoratedName ?></a></td>
            <td style="text-align: center;"><?= htmlspecialchars($player->position) ?></td>
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
            <td style="text-align: center;"><?php if ($row['injuryDays'] > 0): ?><span class="injury-days-tooltip" title="Returns: <?= htmlspecialchars($row['injuryReturnDate']) ?>" tabindex="0"><?= (int)$row['injuryDays'] ?></span><?php endif; ?></td>
        </tr>
<?php endforeach; ?>
    </tbody>
</table>
        <?php
        return ob_get_clean();
    }
}
