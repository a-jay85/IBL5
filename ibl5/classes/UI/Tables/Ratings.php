<?php

declare(strict_types=1);

namespace UI\Tables;

use Player\Player;
use Player\PlayerImageHelper;
use UI\Components\TooltipLabel;
use UI\TeamCellHelper;
use Utilities\HtmlSanitizer;
use Team\Team;
use Season\Season;

/**
 * Ratings - Displays player ratings table
 */
class Ratings
{
    /**
     * Render the ratings table
     *
     * @param \mysqli $db Database connection
     * @param iterable<int, \Player\Player|array<string, mixed>> $data Player data
     * @param Team $team Team object
     * @param string $yr Year filter (empty for current season)
     * @param Season $season Season object
     * @param string $moduleName Module name for styling variations
     * @param list<int> $starterPids Starter player IDs
     * @return string HTML table
     */
    public static function render($db, $data, $team, string $yr, $season, string $moduleName = "", array $starterPids = []): string
    {
        $players = PlayerRowTransformer::resolvePlayers($db, $data, $yr);

        ob_start();
        ?>
<table class="ibl-data-table team-table responsive-table sortable" style="<?= \UI\TableStyles::inlineVars($team->color1, $team->color2) ?>">
    <thead>
        <tr>
<?php if ($moduleName === "LeagueStarters"): ?>
            <th>Team</th>
<?php endif; ?>
            <th class="sticky-col">Player</th>
            <th>Pos</th>
            <th class="sep-r-team">Age</th>
            <th>2ga</th>
            <th>2g%</th>
            <th>fta</th>
            <th>ft%</th>
            <th>3ga</th>
            <th class="sep-r-team">3g%</th>
            <th>orb</th>
            <th>drb</th>
            <th>ast</th>
            <th>stl</th>
            <th>tvr</th>
            <th>blk</th>
            <th class="sep-r-team">foul</th>
            <th>oo</th>
            <th>do</th>
            <th>po</th>
            <th>to</th>
            <th>od</th>
            <th>dd</th>
            <th>pd</th>
            <th class="sep-r-team">td</th>
            <th>Clu</th>
            <th class="sep-r-team">Con</th>
            <th>Days Injured</th>
        </tr>
    </thead>
    <tbody>
<?php foreach ($players as $player): ?>
        <tr<?php if ($moduleName === "LeagueStarters"): ?> data-team-id="<?= $player->teamid ?? 0 ?>"<?php endif; ?>>
<?php if ($moduleName === "LeagueStarters"):
    echo TeamCellHelper::renderTeamCellOrFreeAgent($player->teamid ?? 0, $player->teamName ?? '', $player->teamColor1 ?? 'FFFFFF', $player->teamColor2 ?? '000000');
endif; ?>
            <?= PlayerImageHelper::renderPlayerCell((int)$player->playerID, $player->decoratedName ?? '', $starterPids, $player->nameStatusClass) ?>
            <td><?= HtmlSanitizer::e($player->position ?? '') ?></td>
            <td class="sep-r-team"><?= (int)$player->age ?></td>
            <td><?= (int)$player->ratingFieldGoalAttempts ?></td>
            <td class="sep-r-weak"><?= (int)$player->ratingFieldGoalPercentage ?></td>
            <td><?= (int)$player->ratingFreeThrowAttempts ?></td>
            <td class="sep-r-weak"><?= (int)$player->ratingFreeThrowPercentage ?></td>
            <td><?= (int)$player->ratingThreePointAttempts ?></td>
            <td class="sep-r-team"><?= (int)$player->ratingThreePointPercentage ?></td>
            <td><?= (int)$player->ratingOffensiveRebounds ?></td>
            <td><?= (int)$player->ratingDefensiveRebounds ?></td>
            <td><?= (int)$player->ratingAssists ?></td>
            <td><?= (int)$player->ratingSteals ?></td>
            <td><?= (int)$player->ratingTurnovers ?></td>
            <td><?= (int)$player->ratingBlocks ?></td>
            <td class="sep-r-team"><?= (int)$player->ratingFouls ?></td>
            <td><?= (int)$player->ratingOutsideOffense ?></td>
            <td><?= (int)$player->ratingDriveOffense ?></td>
            <td><?= (int)$player->ratingPostOffense ?></td>
            <td class="sep-r-weak"><?= (int)$player->ratingTransitionOffense ?></td>
            <td><?= (int)$player->ratingOutsideDefense ?></td>
            <td><?= (int)$player->ratingDriveDefense ?></td>
            <td><?= (int)$player->ratingPostDefense ?></td>
            <td class="sep-r-team"><?= (int)$player->ratingTransitionDefense ?></td>
            <td><?= (int)$player->ratingClutch ?></td>
            <td class="sep-r-team"><?= (int)$player->ratingConsistency ?></td>
            <?php
                $injDays = (int) $player->daysRemainingForInjury;
                $injReturn = $player->getInjuryReturnDate($season->lastSimEndDate);
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
