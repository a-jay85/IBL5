<?php

declare(strict_types=1);

namespace UI\Tables;

use Player\Player;
use Player\PlayerImageHelper;
use Player\PlayerStats;
use UI\TeamCellHelper;

/**
 * SeasonTotals - Displays season totals statistics table
 *
 * @phpstan-import-type PlayerRow from \Services\CommonMysqliRepository
 */
class SeasonTotals
{
    /**
     * Render the season totals table
     *
     * @param object $db Database connection
     * @param iterable<int, Player|array<string, mixed>> $result Player result set
     * @param \Team $team Team object
     * @param string $yr Year filter (empty for current season)
     * @param list<int> $starterPids Starter player IDs
     * @param string $moduleName Module name
     * @return string HTML table
     */
    public static function render(object $db, $result, \Team $team, string $yr, array $starterPids = [], string $moduleName = ""): string
    {
        $playerRows = [];
        foreach ($result as $plrRow) {
            if ($yr === "") {
                if ($plrRow instanceof Player) {
                    $player = $plrRow;
                    /** @var PlayerStats $playerStats */
                    $playerStats = PlayerStats::withPlayerID($db, $player->playerID ?? 0);
                } elseif (is_array($plrRow)) {
                    /** @var PlayerRow $plrRow */
                    $player = Player::withPlrRow($db, $plrRow);
                    /** @var PlayerStats $playerStats */
                    $playerStats = PlayerStats::withPlrRow($db, $plrRow);
                } else {
                    continue;
                }

                $playerName = $player->name ?? '';
                $firstCharacterOfPlayerName = substr($playerName, 0, 1);
                if ($firstCharacterOfPlayerName === '|') {
                    continue;
                }
            } else {
                /** @var array<string, mixed> $plrRow */
                $player = Player::withHistoricalPlrRow($db, $plrRow);
                /** @var PlayerStats $playerStats */
                $playerStats = PlayerStats::withHistoricalPlrRow($db, $plrRow);
            }

            $playerRows[] = [
                'player' => $player,
                'playerStats' => $playerStats,
            ];
        }

        /** @var \mysqli $db */
        $season = new \Season($db);
        $teamStats = \TeamStats::withTeamName($db, $team->name, $season->endingYear);

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
            <th class="sep-team"></th>
            <th>ftm</th>
            <th>fta</th>
            <th class="sep-team"></th>
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
    /** @var Player $player */
    $player = $row['player'];
    /** @var PlayerStats $playerStats */
    $playerStats = $row['playerStats'];
?>
        <tr>
<?php if ($moduleName === "LeagueStarters"):
    echo TeamCellHelper::renderTeamCellOrFreeAgent($player->teamID ?? 0, $player->teamName ?? '', $player->teamColor1 ?? 'FFFFFF', $player->teamColor2 ?? '000000');
endif; ?>
            <td><?= htmlspecialchars($player->position ?? '') ?></td>
            <?= PlayerImageHelper::renderPlayerCell($player->playerID ?? 0, $player->decoratedName ?? '', $starterPids) ?>
            <td style="text-align: center;"><?= $playerStats->seasonGamesPlayed ?></td>
            <td style="text-align: center;"><?= $playerStats->seasonGamesStarted ?></td>
            <td style="text-align: center;"><?= $playerStats->seasonMinutes ?></td>
            <td class="sep-team"></td>
            <td style="text-align: center;"><?= $playerStats->seasonFieldGoalsMade ?></td>
            <td style="text-align: center;"><?= $playerStats->seasonFieldGoalsAttempted ?></td>
            <td class="sep-weak"></td>
            <td style="text-align: center;"><?= $playerStats->seasonFreeThrowsMade ?></td>
            <td style="text-align: center;"><?= $playerStats->seasonFreeThrowsAttempted ?></td>
            <td class="sep-weak"></td>
            <td style="text-align: center;"><?= $playerStats->seasonThreePointersMade ?></td>
            <td style="text-align: center;"><?= $playerStats->seasonThreePointersAttempted ?></td>
            <td class="sep-team"></td>
            <td style="text-align: center;"><?= $playerStats->seasonOffensiveRebounds ?></td>
            <td style="text-align: center;"><?= $playerStats->seasonTotalRebounds ?></td>
            <td style="text-align: center;"><?= $playerStats->seasonAssists ?></td>
            <td style="text-align: center;"><?= $playerStats->seasonSteals ?></td>
            <td style="text-align: center;"><?= $playerStats->seasonTurnovers ?></td>
            <td style="text-align: center;"><?= $playerStats->seasonBlocks ?></td>
            <td style="text-align: center;"><?= $playerStats->seasonPersonalFouls ?></td>
            <td style="text-align: center;"><?= $playerStats->seasonPoints ?></td>
        </tr>
<?php endforeach; ?>
    </tbody>
    <tfoot>
<?php if ($yr === ""):
    $labelColspan = ($moduleName === "LeagueStarters") ? 3 : 2;
?>
        <tr>
            <td colspan="<?= $labelColspan ?>"><?= htmlspecialchars($team->name) ?> Offense</td>
            <td><?= $teamStats->seasonOffenseGamesPlayed ?></td>
            <td><?= $teamStats->seasonOffenseGamesPlayed ?></td>
            <td></td>
            <td class="sep-team"></td>
            <td><?= $teamStats->seasonOffenseTotalFieldGoalsMade ?></td>
            <td><?= $teamStats->seasonOffenseTotalFieldGoalsAttempted ?></td>
            <td class="sep-weak"></td>
            <td><?= $teamStats->seasonOffenseTotalFreeThrowsMade ?></td>
            <td><?= $teamStats->seasonOffenseTotalFreeThrowsAttempted ?></td>
            <td class="sep-weak"></td>
            <td><?= $teamStats->seasonOffenseTotalThreePointersMade ?></td>
            <td><?= $teamStats->seasonOffenseTotalThreePointersAttempted ?></td>
            <td class="sep-team"></td>
            <td><?= $teamStats->seasonOffenseTotalOffensiveRebounds ?></td>
            <td><?= $teamStats->seasonOffenseTotalRebounds ?></td>
            <td><?= $teamStats->seasonOffenseTotalAssists ?></td>
            <td><?= $teamStats->seasonOffenseTotalSteals ?></td>
            <td><?= $teamStats->seasonOffenseTotalTurnovers ?></td>
            <td><?= $teamStats->seasonOffenseTotalBlocks ?></td>
            <td><?= $teamStats->seasonOffenseTotalPersonalFouls ?></td>
            <td><?= $teamStats->seasonOffenseTotalPoints ?></td>
        </tr>
        <tr>
            <td colspan="<?= $labelColspan ?>"><?= htmlspecialchars($team->name) ?> Defense</td>
            <td><?= $teamStats->seasonDefenseGamesPlayed ?></td>
            <td><?= $teamStats->seasonDefenseGamesPlayed ?></td>
            <td></td>
            <td class="sep-team"></td>
            <td><?= $teamStats->seasonDefenseTotalFieldGoalsMade ?></td>
            <td><?= $teamStats->seasonDefenseTotalFieldGoalsAttempted ?></td>
            <td class="sep-weak"></td>
            <td><?= $teamStats->seasonDefenseTotalFreeThrowsMade ?></td>
            <td><?= $teamStats->seasonDefenseTotalFreeThrowsAttempted ?></td>
            <td class="sep-weak"></td>
            <td><?= $teamStats->seasonDefenseTotalThreePointersMade ?></td>
            <td><?= $teamStats->seasonDefenseTotalThreePointersAttempted ?></td>
            <td class="sep-team"></td>
            <td><?= $teamStats->seasonDefenseTotalOffensiveRebounds ?></td>
            <td><?= $teamStats->seasonDefenseTotalRebounds ?></td>
            <td><?= $teamStats->seasonDefenseTotalAssists ?></td>
            <td><?= $teamStats->seasonDefenseTotalSteals ?></td>
            <td><?= $teamStats->seasonDefenseTotalTurnovers ?></td>
            <td><?= $teamStats->seasonDefenseTotalBlocks ?></td>
            <td><?= $teamStats->seasonDefenseTotalPersonalFouls ?></td>
            <td><?= $teamStats->seasonDefenseTotalPoints ?></td>
        </tr>
<?php endif; ?>
    </tfoot>
</table>
        <?php
        return (string) ob_get_clean();
    }
}
