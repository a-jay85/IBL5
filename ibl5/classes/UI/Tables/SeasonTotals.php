<?php

declare(strict_types=1);

namespace UI\Tables;

use Player\Player;
use Player\PlayerImageHelper;
use Player\PlayerStats;
use UI\TeamCellHelper;
use Utilities\HtmlSanitizer;
use Team\Team;
use Season\Season;

/**
 * SeasonTotals - Displays season totals statistics table
 */
class SeasonTotals
{
    /**
     * Render the season totals table
     *
     * @param \mysqli $db Database connection
     * @param iterable<int, Player|array<string, mixed>> $result Player result set
     * @param Team $team Team object
     * @param string $yr Year filter (empty for current season)
     * @param list<int> $starterPids Starter player IDs
     * @param string $moduleName Module name
     * @return string HTML table
     */
    public static function render(\mysqli $db, $result, Team $team, string $yr, array $starterPids = [], string $moduleName = ""): string
    {
        $playerRows = PlayerRowTransformer::resolveWithStats($db, $result, $yr);

        $season = new Season($db);
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
            <th class="sep-r-team">min</th>
            <th>fgm</th>
            <th>fga</th>
            <th>ftm</th>
            <th>fta</th>
            <th>3gm</th>
            <th class="sep-r-team">3ga</th>
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
            <td><?= HtmlSanitizer::e($player->position ?? '') ?></td>
            <?= PlayerImageHelper::renderPlayerCell($player->playerID ?? 0, $player->decoratedName ?? '', $starterPids, $player->nameStatusClass) ?>
            <td><?= $playerStats->seasonGamesPlayed ?></td>
            <td><?= $playerStats->seasonGamesStarted ?></td>
            <td class="sep-r-team"><?= $playerStats->seasonMinutes ?></td>
            <td><?= $playerStats->seasonFieldGoalsMade ?></td>
            <td class="sep-r-weak"><?= $playerStats->seasonFieldGoalsAttempted ?></td>
            <td><?= $playerStats->seasonFreeThrowsMade ?></td>
            <td class="sep-r-weak"><?= $playerStats->seasonFreeThrowsAttempted ?></td>
            <td><?= $playerStats->seasonThreePointersMade ?></td>
            <td class="sep-r-team"><?= $playerStats->seasonThreePointersAttempted ?></td>
            <td><?= $playerStats->seasonOffensiveRebounds ?></td>
            <td><?= $playerStats->seasonTotalRebounds ?></td>
            <td><?= $playerStats->seasonAssists ?></td>
            <td><?= $playerStats->seasonSteals ?></td>
            <td><?= $playerStats->seasonTurnovers ?></td>
            <td><?= $playerStats->seasonBlocks ?></td>
            <td><?= $playerStats->seasonPersonalFouls ?></td>
            <td><?= $playerStats->seasonPoints ?></td>
        </tr>
<?php endforeach; ?>
    </tbody>
    <tfoot>
<?php if ($yr === ""):
    $labelColspan = ($moduleName === "LeagueStarters") ? 3 : 2;
?>
        <tr>
            <td colspan="<?= $labelColspan ?>"><?= HtmlSanitizer::e($team->name) ?> Offense</td>
            <td><?= $teamStats->seasonOffenseGamesPlayed ?></td>
            <td><?= $teamStats->seasonOffenseGamesPlayed ?></td>
            <td class="sep-r-team"></td>
            <td><?= $teamStats->seasonOffenseTotalFieldGoalsMade ?></td>
            <td class="sep-r-weak"><?= $teamStats->seasonOffenseTotalFieldGoalsAttempted ?></td>
            <td><?= $teamStats->seasonOffenseTotalFreeThrowsMade ?></td>
            <td class="sep-r-weak"><?= $teamStats->seasonOffenseTotalFreeThrowsAttempted ?></td>
            <td><?= $teamStats->seasonOffenseTotalThreePointersMade ?></td>
            <td class="sep-r-team"><?= $teamStats->seasonOffenseTotalThreePointersAttempted ?></td>
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
            <td colspan="<?= $labelColspan ?>"><?= HtmlSanitizer::e($team->name) ?> Defense</td>
            <td><?= $teamStats->seasonDefenseGamesPlayed ?></td>
            <td><?= $teamStats->seasonDefenseGamesPlayed ?></td>
            <td class="sep-r-team"></td>
            <td><?= $teamStats->seasonDefenseTotalFieldGoalsMade ?></td>
            <td class="sep-r-weak"><?= $teamStats->seasonDefenseTotalFieldGoalsAttempted ?></td>
            <td><?= $teamStats->seasonDefenseTotalFreeThrowsMade ?></td>
            <td class="sep-r-weak"><?= $teamStats->seasonDefenseTotalFreeThrowsAttempted ?></td>
            <td><?= $teamStats->seasonDefenseTotalThreePointersMade ?></td>
            <td class="sep-r-team"><?= $teamStats->seasonDefenseTotalThreePointersAttempted ?></td>
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
