<?php

declare(strict_types=1);

namespace UI\Tables;

use Player\Player;
use Player\PlayerImageHelper;
use Player\PlayerStats;
use BasketballStats\StatsFormatter;

/**
 * Per36Minutes - Displays per-36-minute statistics table
 *
 * @phpstan-import-type PlayerRow from \Services\CommonMysqliRepository
 */
class Per36Minutes
{
    /**
     * Render the per-36-minute statistics table
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
                if (!is_array($plrRow)) {
                    continue;
                }
                $player = Player::withHistoricalPlrRow($db, $plrRow);
                /** @var PlayerStats $playerStats */
                $playerStats = PlayerStats::withHistoricalPlrRow($db, $plrRow);
            }

            $playerRows[] = [
                'player' => $player,
                'playerStats' => $playerStats,
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
        }

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
            <th>mpg</th>
            <th>36min</th>
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
    /** @var Player $player */
    $player = $row['player'];
    /** @var PlayerStats $playerStats */
    $playerStats = $row['playerStats'];
?>
        <tr>
<?php if ($moduleName === "LeagueStarters"):
    $teamId = $player->teamID ?? 0;
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
            <td><?= htmlspecialchars($player->position ?? '') ?></td>
            <?= PlayerImageHelper::renderPlayerCell($player->playerID ?? 0, $player->decoratedName ?? '', $starterPids) ?>
            <td style="text-align: center;"><?= $playerStats->seasonGamesPlayed ?></td>
            <td style="text-align: center;"><?= $playerStats->seasonGamesStarted ?></td>
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
        return (string) ob_get_clean();
    }
}
