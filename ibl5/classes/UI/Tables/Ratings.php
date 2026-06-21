<?php

declare(strict_types=1);

namespace UI\Tables;

use Player\Player;
use Player\PlayerImageHelper;
use UI\Components\TooltipLabel;
use UI\TeamCellHelper;
use Security\HtmlSanitizer;
use Team\Team;
use Season\Season;
use UI\Contracts\RatingsInterface;

/**
 * Ratings - Displays player ratings table
 */
class Ratings implements RatingsInterface
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
     * @param string $ariaLabel Optional aria-label for the table scroll region (empty = no attribute)
     * @return string HTML table
     */
    public static function render($db, $data, $team, string $yr, $season, string $moduleName = "", array $starterPids = [], string $ariaLabel = ''): string
    {
        $players = PlayerRowTransformer::resolvePlayers($db, $data, $yr);

        ob_start();
        ?>
<table class="ibl-data-table team-table responsive-table sortable"<?= $ariaLabel !== '' ? ' aria-label="' . \Security\HtmlSanitizer::e($ariaLabel) . '"' : '' ?> style="<?= \UI\TableStyles::inlineTeamVars($team->color1, $team->color2) ?>">
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
        <tr<?php if ($moduleName === "LeagueStarters"): ?> data-team-id="<?= $player->getTeamid() ?? 0 ?>"<?php endif; ?>>
<?php if ($moduleName === "LeagueStarters"):
    echo TeamCellHelper::renderTeamCellOrFreeAgent($player->getTeamid() ?? 0, $player->getTeamName() ?? '', $player->getTeamColor1() ?? 'FFFFFF', $player->getTeamColor2() ?? '000000');
endif; ?>
            <?= PlayerImageHelper::renderPlayerCell((int)$player->getPlayerID(), $player->getDecoratedName() ?? '', $starterPids, $player->getNameStatusClass()) ?>
            <td><?= HtmlSanitizer::e($player->getPosition() ?? '') ?></td>
            <td class="sep-r-team"><?= (int)$player->getAge() ?></td>
            <td><?= (int)$player->getRatingFieldGoalAttempts() ?></td>
            <td class="sep-r-weak"><?= (int)$player->getRatingFieldGoalPercentage() ?></td>
            <td><?= (int)$player->getRatingFreeThrowAttempts() ?></td>
            <td class="sep-r-weak"><?= (int)$player->getRatingFreeThrowPercentage() ?></td>
            <td><?= (int)$player->getRatingThreePointAttempts() ?></td>
            <td class="sep-r-team"><?= (int)$player->getRatingThreePointPercentage() ?></td>
            <td><?= (int)$player->getRatingOffensiveRebounds() ?></td>
            <td><?= (int)$player->getRatingDefensiveRebounds() ?></td>
            <td><?= (int)$player->getRatingAssists() ?></td>
            <td><?= (int)$player->getRatingSteals() ?></td>
            <td><?= (int)$player->getRatingTurnovers() ?></td>
            <td><?= (int)$player->getRatingBlocks() ?></td>
            <td class="sep-r-team"><?= (int)$player->getRatingFouls() ?></td>
            <td><?= (int)$player->getRatingOutsideOffense() ?></td>
            <td><?= (int)$player->getRatingDriveOffense() ?></td>
            <td><?= (int)$player->getRatingPostOffense() ?></td>
            <td class="sep-r-weak"><?= (int)$player->getRatingTransitionOffense() ?></td>
            <td><?= (int)$player->getRatingOutsideDefense() ?></td>
            <td><?= (int)$player->getRatingDriveDefense() ?></td>
            <td><?= (int)$player->getRatingPostDefense() ?></td>
            <td class="sep-r-team"><?= (int)$player->getRatingTransitionDefense() ?></td>
            <td><?= (int)$player->getRatingClutch() ?></td>
            <td class="sep-r-team"><?= (int)$player->getRatingConsistency() ?></td>
            <?php
                $injDays = (int) $player->getDaysRemainingForInjury();
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
