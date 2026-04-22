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
 * SeasonTotals - Displays season totals statistics table
 *
 * @phpstan-import-type TeamOffenseStatsRow from \TeamOffDefStats\Contracts\TeamOffDefStatsRepositoryInterface
 * @phpstan-import-type TeamDefenseStatsRow from \TeamOffDefStats\Contracts\TeamOffDefStatsRepositoryInterface
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
    echo TeamCellHelper::renderTeamCellOrFreeAgent($player->teamid ?? 0, $player->teamName ?? '', $player->teamColor1 ?? 'FFFFFF', $player->teamColor2 ?? '000000');
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
<?php if ($yr === "" && $bothStats !== null):
    $labelColspan = ($moduleName === "LeagueStarters") ? 3 : 2;
    $off = $bothStats['offense'];
    $def = $bothStats['defense'];
    $offPts = StatsFormatter::calculatePoints($off['fgm'], $off['ftm'], $off['tgm']);
    $defPts = StatsFormatter::calculatePoints($def['fgm'], $def['ftm'], $def['tgm']);
?>
        <tr>
            <td colspan="<?= $labelColspan ?>"><?= HtmlSanitizer::e($team->name) ?> Offense</td>
            <td><?= $off['games'] ?></td>
            <td><?= $off['games'] ?></td>
            <td class="sep-r-team"></td>
            <td><?= StatsFormatter::formatTotal($off['fgm']) ?></td>
            <td class="sep-r-weak"><?= StatsFormatter::formatTotal($off['fga']) ?></td>
            <td><?= StatsFormatter::formatTotal($off['ftm']) ?></td>
            <td class="sep-r-weak"><?= StatsFormatter::formatTotal($off['fta']) ?></td>
            <td><?= StatsFormatter::formatTotal($off['tgm']) ?></td>
            <td class="sep-r-team"><?= StatsFormatter::formatTotal($off['tga']) ?></td>
            <td><?= StatsFormatter::formatTotal($off['orb']) ?></td>
            <td><?= StatsFormatter::formatTotal($off['reb']) ?></td>
            <td><?= StatsFormatter::formatTotal($off['ast']) ?></td>
            <td><?= StatsFormatter::formatTotal($off['stl']) ?></td>
            <td><?= StatsFormatter::formatTotal($off['tvr']) ?></td>
            <td><?= StatsFormatter::formatTotal($off['blk']) ?></td>
            <td><?= StatsFormatter::formatTotal($off['pf']) ?></td>
            <td><?= StatsFormatter::formatTotal($offPts) ?></td>
        </tr>
        <tr>
            <td colspan="<?= $labelColspan ?>"><?= HtmlSanitizer::e($team->name) ?> Defense</td>
            <td><?= $def['games'] ?></td>
            <td><?= $def['games'] ?></td>
            <td class="sep-r-team"></td>
            <td><?= StatsFormatter::formatTotal($def['fgm']) ?></td>
            <td class="sep-r-weak"><?= StatsFormatter::formatTotal($def['fga']) ?></td>
            <td><?= StatsFormatter::formatTotal($def['ftm']) ?></td>
            <td class="sep-r-weak"><?= StatsFormatter::formatTotal($def['fta']) ?></td>
            <td><?= StatsFormatter::formatTotal($def['tgm']) ?></td>
            <td class="sep-r-team"><?= StatsFormatter::formatTotal($def['tga']) ?></td>
            <td><?= StatsFormatter::formatTotal($def['orb']) ?></td>
            <td><?= StatsFormatter::formatTotal($def['reb']) ?></td>
            <td><?= StatsFormatter::formatTotal($def['ast']) ?></td>
            <td><?= StatsFormatter::formatTotal($def['stl']) ?></td>
            <td><?= StatsFormatter::formatTotal($def['tvr']) ?></td>
            <td><?= StatsFormatter::formatTotal($def['blk']) ?></td>
            <td><?= StatsFormatter::formatTotal($def['pf']) ?></td>
            <td><?= StatsFormatter::formatTotal($defPts) ?></td>
        </tr>
<?php endif; ?>
    </tfoot>
</table>
        <?php
        return (string) ob_get_clean();
    }
}
