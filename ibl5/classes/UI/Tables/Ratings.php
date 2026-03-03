<?php

declare(strict_types=1);

namespace UI\Tables;

use Player\Player;
use Player\PlayerImageHelper;
use UI\Components\TooltipLabel;
use UI\TeamCellHelper;

/**
 * Ratings - Displays player ratings table
 *
 * @phpstan-import-type PlayerRow from \Services\CommonMysqliRepository
 */
class Ratings
{
    /**
     * Render the ratings table
     *
     * @param \mysqli $db Database connection
     * @param iterable<int, \Player\Player|array<string, mixed>> $data Player data
     * @param \Team $team Team object
     * @param string $yr Year filter (empty for current season)
     * @param \Season $season Season object
     * @param string $moduleName Module name for styling variations
     * @param list<int> $starterPids Starter player IDs
     * @return string HTML table
     */
    public static function render($db, $data, $team, string $yr, $season, string $moduleName = "", array $starterPids = []): string
    // TODO: simplify this by refactoring Player initialization logic out of this method
    {
        $playerRows = [];
        foreach ($data as $plrRow) {
            if ($yr === "") {
                if ($plrRow instanceof Player) {
                    $player = $plrRow;
                } elseif (is_array($plrRow)) {
                    /** @var PlayerRow $plrRow */
                    $player = Player::withPlrRow($db, $plrRow);
                } else {
                    continue;
                }

                $playerName = $player->name ?? '';
                $firstCharacterOfPlayerName = substr($playerName, 0, 1);
                if ($firstCharacterOfPlayerName === '|') {
                    continue;
                }
            } else {
                if (!is_array($plrRow)) {
                    continue;
                }
                $player = Player::withHistoricalPlrRow($db, $plrRow);
            }

            $injuryReturnDate = $player->getInjuryReturnDate($season->lastSimEndDate);
            $injuryDays = $player->daysRemainingForInjury;

            $playerRows[] = [
                'player' => $player,
                'injuryDays' => $injuryDays,
                'injuryReturnDate' => $injuryReturnDate,
            ];
        }

        ob_start();
        ?>
<table class="ibl-data-table team-table responsive-table sortable" style="<?= \UI\TableStyles::inlineVars($team->color1, $team->color2) ?>">
<colgroup span="2"></colgroup><colgroup span="2"></colgroup><colgroup span="6"></colgroup><colgroup span="6"></colgroup><colgroup span="4"></colgroup><colgroup span="4"></colgroup><colgroup span="1"></colgroup>
    <thead>
        <tr>
<?php if ($moduleName === "LeagueStarters"): ?>
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
?>
        <tr<?php if ($moduleName === "LeagueStarters"): ?> data-team-id="<?= $player->teamID ?? 0 ?>"<?php endif; ?>>
<?php if ($moduleName === "LeagueStarters"):
    echo TeamCellHelper::renderTeamCellOrFreeAgent($player->teamID ?? 0, $player->teamName ?? '', $player->teamColor1 ?? 'FFFFFF', $player->teamColor2 ?? '000000');
endif; ?>
            <?= PlayerImageHelper::renderPlayerCell((int)$player->playerID, $player->decoratedName ?? '', $starterPids, $player->nameStatusClass) ?>
            <td><?= htmlspecialchars($player->position ?? '') ?></td>
            <td><?= (int)$player->age ?></td>
            <td class="sep-team"></td>
            <td><?= (int)$player->ratingFieldGoalAttempts ?></td>
            <td><?= (int)$player->ratingFieldGoalPercentage ?></td>
            <td class="sep-weak"></td>
            <td><?= (int)$player->ratingFreeThrowAttempts ?></td>
            <td><?= (int)$player->ratingFreeThrowPercentage ?></td>
            <td class="sep-weak"></td>
            <td><?= (int)$player->ratingThreePointAttempts ?></td>
            <td><?= (int)$player->ratingThreePointPercentage ?></td>
            <td class="sep-team"></td>
            <td><?= (int)$player->ratingOffensiveRebounds ?></td>
            <td><?= (int)$player->ratingDefensiveRebounds ?></td>
            <td><?= (int)$player->ratingAssists ?></td>
            <td><?= (int)$player->ratingSteals ?></td>
            <td><?= (int)$player->ratingTurnovers ?></td>
            <td><?= (int)$player->ratingBlocks ?></td>
            <td><?= (int)$player->ratingFouls ?></td>
            <td class="sep-team"></td>
            <td><?= (int)$player->ratingOutsideOffense ?></td>
            <td><?= (int)$player->ratingDriveOffense ?></td>
            <td><?= (int)$player->ratingPostOffense ?></td>
            <td><?= (int)$player->ratingTransitionOffense ?></td>
            <td class="sep-weak"></td>
            <td><?= (int)$player->ratingOutsideDefense ?></td>
            <td><?= (int)$player->ratingDriveDefense ?></td>
            <td><?= (int)$player->ratingPostDefense ?></td>
            <td><?= (int)$player->ratingTransitionDefense ?></td>
            <td class="sep-team"></td>
            <td><?= (int)$player->ratingClutch ?></td>
            <td><?= (int)$player->ratingConsistency ?></td>
            <td class="sep-team"></td>
            <?php
                $injDays = (int) $row['injuryDays'];
                $injReturn = $row['injuryReturnDate'];
            ?>
            <td><?= ($injDays > 0 && $injReturn !== '')
                ? TooltipLabel::render((string) $injDays, 'Returns: ' . $injReturn)
                : (string) $injDays ?></td>
        </tr>
<?php endforeach; ?>
    </tbody>
</table>
        <?php
        return (string) ob_get_clean();
    }
}
