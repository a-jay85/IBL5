<?php

declare(strict_types=1);

namespace FreeAgency;

use FreeAgency\Contracts\FreeAgencyViewInterface;
use Player\Player;
use Player\PlayerImageHelper;
use Team\Contracts\TeamQueryRepositoryInterface;
use UI\TeamCellHelper;

/**
 * @see FreeAgencyViewInterface
 *
 * @phpstan-import-type PlayerRow from \Services\CommonMysqliRepository
 * @phpstan-import-type CapMetrics from \FreeAgency\Contracts\FreeAgencyViewInterface
 */
class FreeAgencyView implements FreeAgencyViewInterface
{
    private \mysqli $mysqli_db;
    private TeamQueryRepositoryInterface $teamQueryRepo;

    public function __construct(\mysqli $mysqli_db)
    {
        $this->mysqli_db = $mysqli_db;
        $this->teamQueryRepo = new \Team\TeamQueryRepository($mysqli_db);
    }

    /**
     * @see FreeAgencyViewInterface::render()
     *
     * @param array{team: \Team, season: \Season, capMetrics: CapMetrics, allOtherPlayers: list<PlayerRow>} $mainPageData
     */
    public function render(array $mainPageData, ?string $result = null): string
    {
        $team = $mainPageData['team'];
        $season = $mainPageData['season'];
        $capMetrics = $mainPageData['capMetrics'];
        $allOtherPlayers = $mainPageData['allOtherPlayers'];

        ob_start();
        echo $this->renderResultBanner($result);
        ?>
<h2 class="ibl-title">Free Agency</h2>
<img src="images/logo/<?= $team->teamID ?>.jpg" alt="Team Logo" class="team-logo-banner">
<div class="mt-6"></div>
<?= $this->renderPlayersUnderContract($team, $season, $capMetrics) ?>
<div class="mt-6"></div>
<?= $this->renderContractOffers($team, $capMetrics) ?>
<div class="mt-6"></div>
<?= $this->renderTeamFreeAgents($team, $season, $capMetrics) ?>
<?= $this->renderOtherFreeAgents($team, $season, $allOtherPlayers) ?>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Render a result banner from PRG redirect query param
     *
     * @param string|null $result Result code from query parameter
     * @return string HTML alert banner or empty string
     */
    private function renderResultBanner(?string $result): string
    {
        if ($result === null) {
            return '';
        }

        $banners = [
            'offer_success' => ['class' => 'ibl-alert--success', 'message' => 'Your offer is legal and has been saved.'],
            'deleted' => ['class' => 'ibl-alert--info', 'message' => 'Your offer has been deleted.'],
            'already_signed' => ['class' => 'ibl-alert--warning', 'message' => 'This player was previously signed to a team this Free Agency period.'],
            'rookie_option_success' => ['class' => 'ibl-alert--success', 'message' => 'Rookie option has been exercised successfully. The contract update is reflected on the team page.'],
            'email_failed' => ['class' => 'ibl-alert--warning', 'message' => 'Rookie option exercised, but the notification email failed to send. Please notify the commissioner.'],
        ];

        if (!isset($banners[$result])) {
            return '';
        }

        $banner = $banners[$result];
        $safeMessage = \Utilities\HtmlSanitizer::safeHtmlOutput($banner['message']);
        return '<div class="ibl-alert ' . $banner['class'] . '">' . $safeMessage . '</div>';
    }

    /**
     * Render players under contract table
     *
     * @param \Team $team Team object
     * @param \Season $season Season object
     * @param CapMetrics $capMetrics Cap metrics from service
     * @return string HTML table
     */
    private function renderPlayersUnderContract(\Team $team, \Season $season, array $capMetrics): string
    {
        ob_start();
        ?>
<div class="table-scroll-wrapper">
<div class="table-scroll-container">
<table class="ibl-data-table team-table fa-table sortable" style="<?= \UI\TableStyles::inlineVars($team->color1, $team->color2) ?>">
    <?= $this->renderColgroups(false) ?>
    <?= $this->renderTableHeader('Players Under Contract', false, $team, false) ?>
    <tbody>
        <?php
        $rosterRows = $this->teamQueryRepo->getRosterUnderContractOrderedByOrdinal($team->teamID);
        foreach ($rosterRows as $playerRow): ?>
            <?php
            $player = Player::withPlrRow($this->mysqli_db, $playerRow);

            if (!$player->isPlayerFreeAgent($season)):
                $futureSalaries = $player->getFutureSalaries();
                $playerName = $player->name ?? '';
                if (($player->ordinal ?? 0) > \JSB::WAIVERS_ORDINAL) {
                    $playerName .= "*";
                }
            ?>
        <tr>
            <td>
                <?php if ($player->canRookieOption($season->phase)): ?>
                    <a href="modules.php?name=Player&amp;pa=rookieoption&amp;pid=<?= $player->playerID ?? 0 ?>&amp;from=fa">Rookie Option</a>
                <?php endif; ?>
            </td>
            <td><?= htmlspecialchars($player->position ?? '') ?></td>
            <?= PlayerImageHelper::renderFlexiblePlayerCell($player->playerID ?? 0, $playerName) ?>
            <td><?= $player->age ?? 0 ?></td>
            <?= $this->renderPlayerRatings($player) ?>
            <?php foreach ($futureSalaries as $salary): ?>
                <td><?= $salary ?></td>
            <?php endforeach; ?>
            <?= $this->renderPlayerPreferences($player) ?>
        </tr>
            <?php endif; ?>
        <?php endforeach; ?>
    </tbody>
    <tfoot>
        <tr>
            <td colspan="18" class="cap-footer-spacer"></td>
            <td colspan="10" style="text-align: right;"><strong><em><?= htmlspecialchars($team->name) ?> Total Salary</em></strong></td>
            <?php foreach ($capMetrics['totalSalaries'] as $salary): ?>
                <td><strong><em><?= $salary ?></em></strong></td>
            <?php endforeach; ?>
            <td colspan="5" class="cap-footer-spacer"></td>
        </tr>
    </tfoot>
</table>
</div>
</div>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Render contract offers table
     *
     * @param \Team $team Team object
     * @param CapMetrics $capMetrics Cap metrics from service
     * @return string HTML table
     */
    private function renderContractOffers(\Team $team, array $capMetrics): string
    {
        ob_start();
        ?>
<div class="table-scroll-wrapper">
<div class="table-scroll-container">
<table class="ibl-data-table team-table fa-table sortable" style="<?= \UI\TableStyles::inlineVars($team->color1, $team->color2) ?>">
    <?= $this->renderColgroups(false) ?>
    <?= $this->renderTableHeader('Contract Offers', false, $team, false) ?>
    <tbody>
        <?php
        $offersResult = $this->teamQueryRepo->getFreeAgencyOffers($team->teamID);
        foreach ($offersResult as $offerRow): ?>
            <?php
            $player = Player::withPlayerID($this->mysqli_db, $offerRow['pid'] ?? 0);
            ?>
        <tr>
            <td><a href="modules.php?name=FreeAgency&amp;pa=negotiate&amp;pid=<?= $player->playerID ?? 0 ?>">Negotiate</a></td>
            <td><?= htmlspecialchars($player->position ?? '') ?></td>
            <?= PlayerImageHelper::renderFlexiblePlayerCell($player->playerID ?? 0, $player->name ?? '') ?>
            <td><?= $player->age ?? 0 ?></td>
            <?= $this->renderPlayerRatings($player) ?>
            <td><?= $offerRow['offer1'] ?></td>
            <td><?= $offerRow['offer2'] ?></td>
            <td><?= $offerRow['offer3'] ?></td>
            <td><?= $offerRow['offer4'] ?></td>
            <td><?= $offerRow['offer5'] ?></td>
            <td><?= $offerRow['offer6'] ?></td>
            <?= $this->renderPlayerPreferences($player) ?>
        </tr>
        <?php endforeach; ?>
    </tbody>
    <tfoot>
        <tr>
            <td colspan="18" class="cap-footer-spacer"></td>
            <td colspan="10" style="text-align: right;"><strong><em><?= htmlspecialchars($team->name) ?> Total Salary Plus Contract Offers</em></strong></td>
            <?php foreach ($capMetrics['totalSalaries'] as $salary): ?>
                <td><strong><em><?= $salary ?></em></strong></td>
            <?php endforeach; ?>
            <td colspan="5" class="cap-footer-spacer"></td>
        </tr>
        <?= $this->renderCapSpaceFooter($team, $capMetrics) ?>
    </tfoot>
</table>
</div>
</div>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Render team free agents table
     *
     * @param \Team $team Team object
     * @param \Season $season Season object
     * @param CapMetrics $capMetrics Cap metrics from service
     * @return string HTML table
     */
    private function renderTeamFreeAgents(\Team $team, \Season $season, array $capMetrics): string
    {
        ob_start();
        ?>
<div class="table-scroll-wrapper">
<div class="table-scroll-container">
<table class="ibl-data-table team-table fa-table sortable" style="<?= \UI\TableStyles::inlineVars($team->color1, $team->color2) ?>">
    <?= $this->renderColgroups(false) ?>
    <?= $this->renderTableHeader('Unsigned Free Agents', true, $team, false) ?>
    <tbody>
        <?php
        $rosterRows = $this->teamQueryRepo->getRosterUnderContractOrderedByOrdinal($team->teamID);
        foreach ($rosterRows as $playerRow): ?>
            <?php
            $player = Player::withPlrRow($this->mysqli_db, $playerRow);

            if ($player->isPlayerFreeAgent($season)):
                $demands = $player->getFreeAgencyDemands();
            ?>
        <tr>
            <td>
                <?php if ($capMetrics['rosterSpots'][0] > 0): ?>
                    <a href="modules.php?name=FreeAgency&amp;pa=negotiate&amp;pid=<?= $player->playerID ?? 0 ?>">Negotiate</a>
                <?php endif; ?>
            </td>
            <td><?= htmlspecialchars($player->position ?? '') ?></td>
            <?php $resolved = PlayerImageHelper::resolvePlayerDisplay($player->playerID ?? 0, $player->name ?? ''); ?>
            <td class="ibl-player-cell"><a href="modules.php?name=Player&amp;pa=showpage&amp;pid=<?= $player->playerID ?? 0 ?>">
                <?= $resolved['thumbnail'] ?>
                <?php if (($player->birdYears ?? 0) >= 3): ?>
                    *<em><?= htmlspecialchars($resolved['name']) ?></em>*
                <?php else: ?>
                    <?= htmlspecialchars($resolved['name']) ?>
                <?php endif; ?>
            </a></td>
            <td><?= $player->age ?? 0 ?></td>
            <?= $this->renderPlayerRatings($player) ?>
            <?= $this->renderPlayerDemands($demands) ?>
            <?= $this->renderPlayerPreferences($player) ?>
        </tr>
            <?php endif; ?>
        <?php endforeach; ?>
    </tbody>
</table>
</div>
</div>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Render other free agents table
     *
     * @param \Team $team Team object
     * @param \Season $season Season object
     * @param list<PlayerRow> $allOtherPlayers Pre-fetched player rows from service
     * @return string HTML table
     */
    private function renderOtherFreeAgents(\Team $team, \Season $season, array $allOtherPlayers): string
    {
        ob_start();
        ?>
<div class="sticky-scroll-wrapper page-sticky">
<div class="sticky-scroll-container">
<table class="ibl-data-table team-table fa-table sticky-table sortable" style="<?= \UI\TableStyles::inlineVars('666666', 'ffffff') ?>">
    <?= $this->renderColgroups() ?>
    <?= $this->renderTableHeader('All Other Free Agents', false, $team) ?>
    <tbody>
        <?php
        foreach ($allOtherPlayers as $playerRow):
            $player = Player::withPlrRow($this->mysqli_db, $playerRow);

            if ($player->isPlayerFreeAgent($season)):
                $demands = $player->getFreeAgencyDemands();
        ?>
        <tr>
            <td><a href="modules.php?name=FreeAgency&amp;pa=negotiate&amp;pid=<?= $player->playerID ?? 0 ?>">Negotiate</a></td>
            <td><?= htmlspecialchars($player->position ?? '') ?></td>
            <?= PlayerImageHelper::renderFlexiblePlayerCell($player->playerID ?? 0, $player->name ?? '') ?>
            <?= $this->renderTeamCell($player) ?>
            <td><?= $player->age ?? 0 ?></td>
            <?= $this->renderPlayerRatings($player) ?>
            <?= $this->renderPlayerDemands($demands) ?>
            <?= $this->renderPlayerPreferences($player) ?>
        </tr>
            <?php endif; ?>
        <?php endforeach; ?>
    </tbody>
</table>
</div>
</div>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Render colgroups for table column organization
     *
     * @return string HTML colgroup elements
     */
    private function renderColgroups(bool $showTeamColumn = true): string
    {
        ob_start();
        if ($showTeamColumn) {
            ?><colgroup span="4"></colgroup><?php // Options, Pos, Player, Team
        } else {
            ?><colgroup span="3"></colgroup><?php // Options, Pos, Player
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
     * @param \Team $team Team object for name display
     * @return string HTML table header
     */
    private function renderTableHeader(string $title, bool $showBirdRightsNote, \Team $team, bool $showTeamColumn = true): string
    {
        $teamName = htmlspecialchars($team->name);
        $fullTitle = $title;
        if ($title !== 'All Other Free Agents') {
            $fullTitle = $teamName . ' ' . $title;
        }

        $colspan = $showTeamColumn ? 40 : 39;

        ob_start();
        ?>
    <thead>
        <tr>
            <th colspan="<?= $colspan ?>">
                <?= $fullTitle ?>
                <?php if ($showBirdRightsNote): ?>
                    <br><small>(Note: * and <em>italicized</em> indicates player has Bird Rights)</small>
                <?php endif; ?>
            </th>
        </tr>
        <tr>
            <th>Options</th>
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
            <th>Yr1</th>
            <th>Yr2</th>
            <th>Yr3</th>
            <th>Yr4</th>
            <th>Yr5</th>
            <th>Yr6</th>
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
     * Render a team cell with colors and logo
     *
     * @param Player $player Player with team data
     * @return string HTML table cell
     */
    private function renderTeamCell(Player $player): string
    {
        $teamId = $player->teamID ?? 0;

        if ($teamId === 0) {
            return '<td>Free Agent</td>';
        }

        $teamName = $player->teamName ?? '';
        if ($teamName === '') {
            $commonRepo = new \Services\CommonMysqliRepository($this->mysqli_db);
            $teamName = $commonRepo->getTeamnameFromTeamID($teamId) ?? '';
        }

        $teamColors = \Player\Views\TeamColorHelper::getTeamColors($this->mysqli_db, $teamId);

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
    private function renderPlayerRatings(Player $player): string
    {
        ob_start();
        ?>
<td><?= $player->ratingFieldGoalAttempts ?? 0 ?></td>
<td class="sep-r-weak"><?= $player->ratingFieldGoalPercentage ?? 0 ?></td>
<td><?= $player->ratingFreeThrowAttempts ?? 0 ?></td>
<td class="sep-r-weak"><?= $player->ratingFreeThrowPercentage ?? 0 ?></td>
<td><?= $player->ratingThreePointAttempts ?? 0 ?></td>
<td class="sep-r-team"><?= $player->ratingThreePointPercentage ?? 0 ?></td>
<td><?= $player->ratingOffensiveRebounds ?? 0 ?></td>
<td><?= $player->ratingDefensiveRebounds ?? 0 ?></td>
<td><?= $player->ratingAssists ?? 0 ?></td>
<td><?= $player->ratingSteals ?? 0 ?></td>
<td><?= $player->ratingTurnovers ?? 0 ?></td>
<td><?= $player->ratingBlocks ?? 0 ?></td>
<td class="sep-r-team"><?= $player->ratingFouls ?? 0 ?></td>
<td><?= $player->ratingOutsideOffense ?? 0 ?></td>
<td><?= $player->ratingDriveOffense ?? 0 ?></td>
<td><?= $player->ratingPostOffense ?? 0 ?></td>
<td class="sep-r-weak"><?= $player->ratingTransitionOffense ?? 0 ?></td>
<td><?= $player->ratingOutsideDefense ?? 0 ?></td>
<td><?= $player->ratingDriveDefense ?? 0 ?></td>
<td><?= $player->ratingPostDefense ?? 0 ?></td>
<td class="sep-r-team"><?= $player->ratingTransitionDefense ?? 0 ?></td>
<td><?= $player->ratingTalent ?? 0 ?></td>
<td><?= $player->ratingSkill ?? 0 ?></td>
<td class="sep-r-team"><?= $player->ratingIntangibles ?? 0 ?></td>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Render player preferences cells
     *
     * @param Player $player
     * @return string HTML table cells
     */
    private function renderPlayerPreferences(Player $player): string
    {
        ob_start();
        ?>
<td><?= $player->freeAgencyLoyalty ?? 0 ?></td>
<td><?= $player->freeAgencyPlayForWinner ?? 0 ?></td>
<td><?= $player->freeAgencyPlayingTime ?? 0 ?></td>
<td><?= $player->freeAgencySecurity ?? 0 ?></td>
<td><?= $player->freeAgencyTradition ?? 0 ?></td>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Render player demands cells
     *
     * @param array<string, int> $demands
     * @return string HTML table cells
     */
    private function renderPlayerDemands(array $demands): string
    {
        $dem1 = $demands['dem1'] ?? 0;
        $dem2 = $demands['dem2'] ?? 0;
        $dem3 = $demands['dem3'] ?? 0;
        $dem4 = $demands['dem4'] ?? 0;
        $dem5 = $demands['dem5'] ?? 0;
        $dem6 = $demands['dem6'] ?? 0;

        ob_start();
        echo '<td>' . ($dem1 !== 0 ? $dem1 : '') . '</td>';
        echo '<td>' . ($dem2 !== 0 ? $dem2 : '') . '</td>';
        echo '<td>' . ($dem3 !== 0 ? $dem3 : '') . '</td>';
        echo '<td>' . ($dem4 !== 0 ? $dem4 : '') . '</td>';
        echo '<td>' . ($dem5 !== 0 ? $dem5 : '') . '</td>';
        echo '<td>' . ($dem6 !== 0 ? $dem6 : '') . '</td>';
        return (string) ob_get_clean();
    }

    /**
     * Render cap space footer rows
     *
     * @param \Team $team Team object
     * @param CapMetrics $capMetrics Cap metrics from service
     * @return string HTML table rows
     */
    private function renderCapSpaceFooter(\Team $team, array $capMetrics): string
    {
        $MLEicon = ($team->hasMLE === 1) ? "\u{2705}" : "\u{274C}";
        $LLEicon = ($team->hasLLE === 1) ? "\u{2705}" : "\u{274C}";

        ob_start();
        ?>
<tr class="cap-footer-row">
    <td class="cap-footer-label"><strong>MLE:</strong></td>
    <td><?= $MLEicon ?></td>
    <td colspan="16" class="cap-footer-spacer"></td>
    <td colspan="10" class="cap-footer-label"><strong>Soft Cap Space</strong></td>
    <?php foreach ($capMetrics['softCapSpace'] as $capSpace): ?>
        <td><?= $capSpace ?></td>
    <?php endforeach; ?>
    <td colspan="5" class="cap-footer-spacer"></td>
</tr>
<tr class="cap-footer-row">
    <td class="cap-footer-label"><strong>LLE:</strong></td>
    <td><?= $LLEicon ?></td>
    <td colspan="16" class="cap-footer-spacer"></td>
    <td colspan="10" class="cap-footer-label"><strong>Hard Cap Space</strong></td>
    <?php foreach ($capMetrics['hardCapSpace'] as $capSpace): ?>
        <td><?= $capSpace ?></td>
    <?php endforeach; ?>
    <td colspan="5" class="cap-footer-spacer"></td>
</tr>
<tr class="cap-footer-row">
    <td colspan="18" class="cap-footer-spacer"></td>
    <td colspan="10" class="cap-footer-label"><strong>Empty Roster Slots</strong></td>
    <?php foreach ($capMetrics['rosterSpots'] as $spots): ?>
        <td><?= $spots ?></td>
    <?php endforeach; ?>
    <td colspan="5" class="cap-footer-spacer"></td>
</tr>
        <?php
        return (string) ob_get_clean();
    }
}
