<?php

declare(strict_types=1);

namespace UI\Tables;

use Player\Player;
use Player\PlayerImageHelper;
use Player\PlayerStats;
use UI\TeamCellHelper;
use Utilities\HtmlSanitizer;
use Team\Team;

/**
 * SeasonAverages - Displays season averages statistics table
 */
class SeasonAverages
{
    /**
     * Render the season averages table
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
            <th class="sep-r-team">min</th>
            <th>fgm</th>
            <th>fga</th>
            <th>fgp</th>
            <th>ftm</th>
            <th>fta</th>
            <th>ftp</th>
            <th>3gm</th>
            <th>3ga</th>
            <th class="sep-r-team">3gp</th>
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
            <td class="sep-r-team"><?= $playerStats->seasonMinutesPerGame ?></td>
            <td><?= $playerStats->seasonFieldGoalsMadePerGame ?></td>
            <td><?= $playerStats->seasonFieldGoalsAttemptedPerGame ?></td>
            <td class="sep-r-weak"><?= $playerStats->seasonFieldGoalPercentage ?></td>
            <td><?= $playerStats->seasonFreeThrowsMadePerGame ?></td>
            <td><?= $playerStats->seasonFreeThrowsAttemptedPerGame ?></td>
            <td class="sep-r-weak"><?= $playerStats->seasonFreeThrowPercentage ?></td>
            <td><?= $playerStats->seasonThreePointersMadePerGame ?></td>
            <td><?= $playerStats->seasonThreePointersAttemptedPerGame ?></td>
            <td class="sep-r-team"><?= $playerStats->seasonThreePointPercentage ?></td>
            <td><?= $playerStats->seasonOffensiveReboundsPerGame ?></td>
            <td><?= $playerStats->seasonTotalReboundsPerGame ?></td>
            <td><?= $playerStats->seasonAssistsPerGame ?></td>
            <td><?= $playerStats->seasonStealsPerGame ?></td>
            <td><?= $playerStats->seasonTurnoversPerGame ?></td>
            <td><?= $playerStats->seasonBlocksPerGame ?></td>
            <td><?= $playerStats->seasonPersonalFoulsPerGame ?></td>
            <td><?= $playerStats->seasonPointsPerGame ?></td>
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
            <td><?= $teamStats->seasonOffenseFieldGoalsMadePerGame ?></td>
            <td><?= $teamStats->seasonOffenseFieldGoalsAttemptedPerGame ?></td>
            <td class="sep-r-weak"><?= $teamStats->seasonOffenseFieldGoalPercentage ?></td>
            <td><?= $teamStats->seasonOffenseFreeThrowsMadePerGame ?></td>
            <td><?= $teamStats->seasonOffenseFreeThrowsAttemptedPerGame ?></td>
            <td class="sep-r-weak"><?= $teamStats->seasonOffenseFreeThrowPercentage ?></td>
            <td><?= $teamStats->seasonOffenseThreePointersMadePerGame ?></td>
            <td><?= $teamStats->seasonOffenseThreePointersAttemptedPerGame ?></td>
            <td class="sep-r-team"><?= $teamStats->seasonOffenseThreePointPercentage ?></td>
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
            <td colspan="<?= $labelColspan ?>"><?= HtmlSanitizer::e($team->name) ?> Defense</td>
            <td><?= $teamStats->seasonDefenseGamesPlayed ?></td>
            <td><?= $teamStats->seasonDefenseGamesPlayed ?></td>
            <td class="sep-r-team"></td>
            <td><?= $teamStats->seasonDefenseFieldGoalsMadePerGame ?></td>
            <td><?= $teamStats->seasonDefenseFieldGoalsAttemptedPerGame ?></td>
            <td class="sep-r-weak"><?= $teamStats->seasonDefenseFieldGoalPercentage ?></td>
            <td><?= $teamStats->seasonDefenseFreeThrowsMadePerGame ?></td>
            <td><?= $teamStats->seasonDefenseFreeThrowsAttemptedPerGame ?></td>
            <td class="sep-r-weak"><?= $teamStats->seasonDefenseFreeThrowPercentage ?></td>
            <td><?= $teamStats->seasonDefenseThreePointersMadePerGame ?></td>
            <td><?= $teamStats->seasonDefenseThreePointersAttemptedPerGame ?></td>
            <td class="sep-r-team"><?= $teamStats->seasonDefenseThreePointPercentage ?></td>
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
        return (string) ob_get_clean();
    }
}
