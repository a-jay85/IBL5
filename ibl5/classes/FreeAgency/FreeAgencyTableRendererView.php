<?php

declare(strict_types=1);

namespace FreeAgency;

use FreeAgency\Contracts\FreeAgencyTableRendererInterface;
use Player\Player;
use UI\TeamCellHelper;
use Security\HtmlSanitizer;
use Team\Team;
use Season\Season;
use Repositories\Contracts\TeamIdentityRepositoryInterface;

/**
 * @phpstan-import-type PlayerRow from \Repositories\Contracts\PlayerLookupRepositoryInterface
 * @phpstan-type CapMetrics array{totalSalaries: array<int, int>, softCapSpace: array<int, int>, hardCapSpace: array<int, int>, rosterSpots: array<int, int>}
 */
final class FreeAgencyTableRendererView implements FreeAgencyTableRendererInterface
{
    private TeamIdentityRepositoryInterface $commonRepo;

    public function __construct(TeamIdentityRepositoryInterface $commonRepo)
    {
        $this->commonRepo = $commonRepo;
    }

    /**
     * Render colgroups for table column organization
     *
     * @return string HTML colgroup elements
     */
    public function renderColgroups(bool $showTeamColumn = true, bool $showOptionsColumn = true): string
    {
        ob_start();
        if ($showTeamColumn && $showOptionsColumn) {
            ?><colgroup span="4"></colgroup><?php // Options, Pos, Player, Team
        } elseif ($showTeamColumn) {
            ?><colgroup span="3"></colgroup><?php // Pos, Player, Team
        } elseif ($showOptionsColumn) {
            ?><colgroup span="3"></colgroup><?php // Options, Pos, Player
        } else {
            ?><colgroup span="2"></colgroup><?php // Pos, Player
        }
        ?><colgroup span="7"></colgroup><colgroup span="7"></colgroup><colgroup span="8"></colgroup><colgroup span="3"></colgroup><colgroup span="6"></colgroup><colgroup span="5"></colgroup>
        <?php // Age,2ga,2g%,fta,ft%,3ga,3g% | orb,drb,ast,stl,tvr,blk,foul | oo,do,po,to,od,dd,pd,td | T,S,I | Yr1-6 | Loy,PFW,PT,Sec,Trd
        return (string) ob_get_clean();
    }

    /**
     * Render table header
     *
     * @param string $title Table title to display in header
     * @param bool $showBirdRightsNote Whether to show the Bird Rights note
     * @param Team $team Team object for name display
     * @return string HTML table header
     */
    public function renderTableHeader(string $title, bool $showBirdRightsNote, Team $team, bool $showTeamColumn = true, bool $showOptionsColumn = true, ?Season $season = null): string
    {
        $fullTitle = $title;

        $colspan = 38 + ($showTeamColumn ? 1 : 0) + ($showOptionsColumn ? 1 : 0);

        // Season year headers (same format as Contracts table)
        $yearHeaders = [];
        if ($season !== null) {
            $baseYear = $season->endingYear;
            if ($season->isOffseasonPhase()) {
                $baseYear++;
            }
            for ($i = 0; $i < 6; $i++) {
                $yearHeaders[] = substr((string) ($baseYear - 1 + $i), -2) . '-' . substr((string) ($baseYear + $i), -2);
            }
        } else {
            $yearHeaders = ['Yr1', 'Yr2', 'Yr3', 'Yr4', 'Yr5', 'Yr6'];
        }

        ob_start();
        ?>
    <thead>
        <tr>
            <th colspan="<?= HtmlSanitizer::e($colspan) ?>">
                <?= HtmlSanitizer::e($fullTitle) ?>
                <?php if ($showBirdRightsNote): ?>
                    <br><small>(Note: * and <em>italicized</em> indicates player has Bird Rights)</small>
                <?php endif; ?>
            </th>
        </tr>
        <tr>
            <?php if ($showOptionsColumn): ?>
            <th><span class="sr-only">Actions</span></th>
            <?php endif; ?>
            <th>Pos</th>
            <th>Player</th>
            <?php if ($showTeamColumn): ?>
            <th class="sep-r-team">Team</th>
            <?php endif; ?>
            <th>Age</th>
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
            <th>T</th>
            <th>S</th>
            <th class="sep-r-team">I</th>
            <th class="col-salary"><?= HtmlSanitizer::e($yearHeaders[0]) ?></th>
            <th class="col-salary"><?= HtmlSanitizer::e($yearHeaders[1]) ?></th>
            <th class="col-salary"><?= HtmlSanitizer::e($yearHeaders[2]) ?></th>
            <th class="col-salary"><?= HtmlSanitizer::e($yearHeaders[3]) ?></th>
            <th class="col-salary"><?= HtmlSanitizer::e($yearHeaders[4]) ?></th>
            <th class="col-salary sep-r-team"><?= HtmlSanitizer::e($yearHeaders[5]) ?></th>
            <th>Loy</th>
            <th>PFW</th>
            <th>PT</th>
            <th>Sec</th>
            <th>Trd</th>
        </tr>
    </thead>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * @param array{color1: string, color2: string}|null $teamColors
     */
    public function renderTeamCell(Player $player, ?array $teamColors = null): string
    {
        $teamId = $player->getTeamid() ?? 0;

        if ($teamId === 0) {
            return '<td>FA</td>';
        }

        $teamName = $player->getTeamName() ?? '';
        if ($teamName === '') {
            $teamName = $this->commonRepo->getTeamnameFromTeamID($teamId) ?? '';
        }

        return TeamCellHelper::renderTeamCellOrFreeAgent(
            $teamId,
            $teamName,
            $teamColors['color1'] ?? 'D4AF37',
            $teamColors['color2'] ?? '1e3a5f',
        );
    }

    /**
     * Render player ratings cells
     *
     * @param Player $player
     * @return string HTML table cells
     */
    public function renderPlayerRatings(Player $player): string
    {
        ob_start();
        ?>
<td><?= HtmlSanitizer::e($player->getRatingFieldGoalAttempts() ?? 0) ?></td>
<td class="sep-r-weak"><?= HtmlSanitizer::e($player->getRatingFieldGoalPercentage() ?? 0) ?></td>
<td><?= HtmlSanitizer::e($player->getRatingFreeThrowAttempts() ?? 0) ?></td>
<td class="sep-r-weak"><?= HtmlSanitizer::e($player->getRatingFreeThrowPercentage() ?? 0) ?></td>
<td><?= HtmlSanitizer::e($player->getRatingThreePointAttempts() ?? 0) ?></td>
<td class="sep-r-team"><?= HtmlSanitizer::e($player->getRatingThreePointPercentage() ?? 0) ?></td>
<td><?= HtmlSanitizer::e($player->getRatingOffensiveRebounds() ?? 0) ?></td>
<td><?= HtmlSanitizer::e($player->getRatingDefensiveRebounds() ?? 0) ?></td>
<td><?= HtmlSanitizer::e($player->getRatingAssists() ?? 0) ?></td>
<td><?= HtmlSanitizer::e($player->getRatingSteals() ?? 0) ?></td>
<td><?= HtmlSanitizer::e($player->getRatingTurnovers() ?? 0) ?></td>
<td><?= HtmlSanitizer::e($player->getRatingBlocks() ?? 0) ?></td>
<td class="sep-r-team"><?= HtmlSanitizer::e($player->getRatingFouls() ?? 0) ?></td>
<td><?= HtmlSanitizer::e($player->getRatingOutsideOffense() ?? 0) ?></td>
<td><?= HtmlSanitizer::e($player->getRatingDriveOffense() ?? 0) ?></td>
<td><?= HtmlSanitizer::e($player->getRatingPostOffense() ?? 0) ?></td>
<td class="sep-r-weak"><?= HtmlSanitizer::e($player->getRatingTransitionOffense() ?? 0) ?></td>
<td><?= HtmlSanitizer::e($player->getRatingOutsideDefense() ?? 0) ?></td>
<td><?= HtmlSanitizer::e($player->getRatingDriveDefense() ?? 0) ?></td>
<td><?= HtmlSanitizer::e($player->getRatingPostDefense() ?? 0) ?></td>
<td class="sep-r-team"><?= HtmlSanitizer::e($player->getRatingTransitionDefense() ?? 0) ?></td>
<td><?= HtmlSanitizer::e($player->getRatingTalent() ?? 0) ?></td>
<td><?= HtmlSanitizer::e($player->getRatingSkill() ?? 0) ?></td>
<td class="sep-r-team"><?= HtmlSanitizer::e($player->getRatingIntangibles() ?? 0) ?></td>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Render player preferences cells
     *
     * @param Player $player
     * @return string HTML table cells
     */
    public function renderPlayerPreferences(Player $player): string
    {
        ob_start();
        ?>
<td><?= HtmlSanitizer::e($player->getFreeAgencyLoyalty() ?? 0) ?></td>
<td><?= HtmlSanitizer::e($player->getFreeAgencyPlayForWinner() ?? 0) ?></td>
<td><?= HtmlSanitizer::e($player->getFreeAgencyPlayingTime() ?? 0) ?></td>
<td><?= HtmlSanitizer::e($player->getFreeAgencySecurity() ?? 0) ?></td>
<td><?= HtmlSanitizer::e($player->getFreeAgencyTradition() ?? 0) ?></td>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Render player demands cells
     *
     * @param array<string, int> $demands
     * @return string HTML table cells
     */
    public function renderPlayerDemands(array $demands): string
    {
        $dem1 = $demands['dem1'] ?? 0;
        $dem2 = $demands['dem2'] ?? 0;
        $dem3 = $demands['dem3'] ?? 0;
        $dem4 = $demands['dem4'] ?? 0;
        $dem5 = $demands['dem5'] ?? 0;
        $dem6 = $demands['dem6'] ?? 0;

        ob_start();
        echo '<td class="col-salary">' . HtmlSanitizer::e($dem1 !== 0 ? $dem1 : '') . '</td>';
        echo '<td class="col-salary">' . HtmlSanitizer::e($dem2 !== 0 ? $dem2 : '') . '</td>';
        echo '<td class="col-salary">' . HtmlSanitizer::e($dem3 !== 0 ? $dem3 : '') . '</td>';
        echo '<td class="col-salary">' . HtmlSanitizer::e($dem4 !== 0 ? $dem4 : '') . '</td>';
        echo '<td class="col-salary">' . HtmlSanitizer::e($dem5 !== 0 ? $dem5 : '') . '</td>';
        echo '<td class="col-salary">' . HtmlSanitizer::e($dem6 !== 0 ? $dem6 : '') . '</td>';
        return (string) ob_get_clean();
    }

    /**
     * Render cap space footer rows
     *
     * @param Team $team Team object
     * @param CapMetrics $capMetrics Cap metrics from service
     * @return string HTML table rows
     */
    public function renderCapSpaceFooter(Team $team, array $capMetrics): string
    {
        $MLEicon = ($team->has_mle === 1) ? "\u{2705}" : "\u{274C}";
        $LLEicon = ($team->has_lle === 1) ? "\u{2705}" : "\u{274C}";

        ob_start();
        ?>
<tr class="cap-footer-row">
    <td colspan="18" class="cap-footer-spacer"></td>
    <td colspan="10" class="cap-footer-label">Soft Cap Space</td>
    <?php foreach ($capMetrics['softCapSpace'] as $capSpace): ?>
        <td class="col-salary"><?= HtmlSanitizer::e($capSpace) ?></td>
    <?php endforeach; ?>
    <td class="cap-footer-spacer"></td>
    <td colspan="2" class="cap-footer-label"><strong>MLE:</strong></td>
    <td><?= HtmlSanitizer::e($MLEicon) ?></td>
    <td class="cap-footer-spacer"></td>
</tr>
<tr class="cap-footer-row">
    <td colspan="18" class="cap-footer-spacer"></td>
    <td colspan="10" class="cap-footer-label">Hard Cap Space</td>
    <?php foreach ($capMetrics['hardCapSpace'] as $capSpace): ?>
        <td class="col-salary"><?= HtmlSanitizer::e($capSpace) ?></td>
    <?php endforeach; ?>
    <td class="cap-footer-spacer"></td>
    <td colspan="2" class="cap-footer-label"><strong>LLE:</strong></td>
    <td><?= HtmlSanitizer::e($LLEicon) ?></td>
    <td class="cap-footer-spacer"></td>
</tr>
<tr class="cap-footer-row">
    <td colspan="18" class="cap-footer-spacer"></td>
    <td colspan="10" class="cap-footer-label">Empty Roster Slots</td>
    <?php foreach ($capMetrics['rosterSpots'] as $spots): ?>
        <td class="col-salary"><?= HtmlSanitizer::e($spots) ?></td>
    <?php endforeach; ?>
    <td colspan="5" class="cap-footer-spacer"></td>
</tr>
        <?php
        return (string) ob_get_clean();
    }
}
