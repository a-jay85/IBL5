<?php

declare(strict_types=1);

namespace UI\Tables;

use BasketballStats\StatsFormatter;
use Player\Player;
use Player\PlayerImageHelper;
use Player\PlayerStats;
use TeamOffDefStats\TeamOffDefStatsRepository;
use UI\TeamCellHelper;
use Utilities\HtmlSanitizer;
use Team\Team;
use Season\Season;

/**
 * SeasonAverages - Displays season averages statistics table
 *
 * @phpstan-import-type TeamOffenseStatsRow from \TeamOffDefStats\Contracts\TeamOffDefStatsRepositoryInterface
 * @phpstan-import-type TeamDefenseStatsRow from \TeamOffDefStats\Contracts\TeamOffDefStatsRepositoryInterface
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

        $season = new Season($db);
        $offDefRepo = new TeamOffDefStatsRepository($db);
        $bothStats = $offDefRepo->getTeamBothStats($team->name, $season->endingYear);

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
<?php if ($yr === "" && $bothStats !== null):
    $labelColspan = ($moduleName === "LeagueStarters") ? 3 : 2;
    $off = $bothStats['offense'];
    $def = $bothStats['defense'];
    $offGames = $off['games'];
    $defGames = $def['games'];
    $offPts = StatsFormatter::calculatePoints($off['fgm'], $off['ftm'], $off['tgm']);
    $defPts = StatsFormatter::calculatePoints($def['fgm'], $def['ftm'], $def['tgm']);
?>
        <tr>
            <td colspan="<?= $labelColspan ?>"><?= HtmlSanitizer::e($team->name) ?> Offense</td>
            <td><?= $offGames ?></td>
            <td><?= $offGames ?></td>
            <td class="sep-r-team"></td>
            <td><?= StatsFormatter::formatPerGameAverage($off['fgm'], $offGames) ?></td>
            <td><?= StatsFormatter::formatPerGameAverage($off['fga'], $offGames) ?></td>
            <td class="sep-r-weak"><?= StatsFormatter::formatPercentage($off['fgm'], $off['fga']) ?></td>
            <td><?= StatsFormatter::formatPerGameAverage($off['ftm'], $offGames) ?></td>
            <td><?= StatsFormatter::formatPerGameAverage($off['fta'], $offGames) ?></td>
            <td class="sep-r-weak"><?= StatsFormatter::formatPercentage($off['ftm'], $off['fta']) ?></td>
            <td><?= StatsFormatter::formatPerGameAverage($off['tgm'], $offGames) ?></td>
            <td><?= StatsFormatter::formatPerGameAverage($off['tga'], $offGames) ?></td>
            <td class="sep-r-team"><?= StatsFormatter::formatPercentage($off['tgm'], $off['tga']) ?></td>
            <td><?= StatsFormatter::formatPerGameAverage($off['orb'], $offGames) ?></td>
            <td><?= StatsFormatter::formatPerGameAverage($off['reb'], $offGames) ?></td>
            <td><?= StatsFormatter::formatPerGameAverage($off['ast'], $offGames) ?></td>
            <td><?= StatsFormatter::formatPerGameAverage($off['stl'], $offGames) ?></td>
            <td><?= StatsFormatter::formatPerGameAverage($off['tvr'], $offGames) ?></td>
            <td><?= StatsFormatter::formatPerGameAverage($off['blk'], $offGames) ?></td>
            <td><?= StatsFormatter::formatPerGameAverage($off['pf'], $offGames) ?></td>
            <td><?= StatsFormatter::formatPerGameAverage($offPts, $offGames) ?></td>
        </tr>
        <tr>
            <td colspan="<?= $labelColspan ?>"><?= HtmlSanitizer::e($team->name) ?> Defense</td>
            <td><?= $defGames ?></td>
            <td><?= $defGames ?></td>
            <td class="sep-r-team"></td>
            <td><?= StatsFormatter::formatPerGameAverage($def['fgm'], $defGames) ?></td>
            <td><?= StatsFormatter::formatPerGameAverage($def['fga'], $defGames) ?></td>
            <td class="sep-r-weak"><?= StatsFormatter::formatPercentage($def['fgm'], $def['fga']) ?></td>
            <td><?= StatsFormatter::formatPerGameAverage($def['ftm'], $defGames) ?></td>
            <td><?= StatsFormatter::formatPerGameAverage($def['fta'], $defGames) ?></td>
            <td class="sep-r-weak"><?= StatsFormatter::formatPercentage($def['ftm'], $def['fta']) ?></td>
            <td><?= StatsFormatter::formatPerGameAverage($def['tgm'], $defGames) ?></td>
            <td><?= StatsFormatter::formatPerGameAverage($def['tga'], $defGames) ?></td>
            <td class="sep-r-team"><?= StatsFormatter::formatPercentage($def['tgm'], $def['tga']) ?></td>
            <td><?= StatsFormatter::formatPerGameAverage($def['orb'], $defGames) ?></td>
            <td><?= StatsFormatter::formatPerGameAverage($def['reb'], $defGames) ?></td>
            <td><?= StatsFormatter::formatPerGameAverage($def['ast'], $defGames) ?></td>
            <td><?= StatsFormatter::formatPerGameAverage($def['stl'], $defGames) ?></td>
            <td><?= StatsFormatter::formatPerGameAverage($def['tvr'], $defGames) ?></td>
            <td><?= StatsFormatter::formatPerGameAverage($def['blk'], $defGames) ?></td>
            <td><?= StatsFormatter::formatPerGameAverage($def['pf'], $defGames) ?></td>
            <td><?= StatsFormatter::formatPerGameAverage($defPts, $defGames) ?></td>
        </tr>
<?php endif; ?>
    </tfoot>
</table>
        <?php
        return (string) ob_get_clean();
    }
}
