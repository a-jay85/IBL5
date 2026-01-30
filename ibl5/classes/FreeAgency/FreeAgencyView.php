<?php

declare(strict_types=1);

namespace FreeAgency;

use FreeAgency\Contracts\FreeAgencyViewInterface;
use Player\Player;

/**
 * @see FreeAgencyViewInterface
 */
class FreeAgencyView implements FreeAgencyViewInterface
{
    private object $mysqli_db;

    public function __construct(object $mysqli_db)
    {
        $this->mysqli_db = $mysqli_db;
    }

    /**
     * @see FreeAgencyViewInterface::render()
     */
    public function render(array $mainPageData, ?string $result = null): string
    {
        $team = $mainPageData['team'];
        $season = $mainPageData['season'];
        $capMetrics = $mainPageData['capMetrics'];
        $allOtherPlayers = $mainPageData['allOtherPlayers'];

        ob_start();
        // Generate team-colored table styles for all 4 tables
        $teamColor = $team->color1 ?? 'D4AF37';
        $teamColor2 = $team->color2 ?? '1e3a5f';
        echo \UI\TableStyles::render('fa-under-contract', $teamColor, $teamColor2);
        echo \UI\TableStyles::render('fa-offers', $teamColor, $teamColor2);
        echo \UI\TableStyles::render('fa-team-free-agents', $teamColor, $teamColor2);
        echo \UI\TableStyles::render('fa-other-free-agents', '666666', 'ffffff');
        echo $this->renderResultBanner($result);
        ?>
<img src="images/logo/<?= (int) $team->teamID ?>.jpg" alt="Team Logo" class="team-logo-banner">
<div style="margin-top: 1.5rem;"></div>
<?= $this->renderPlayersUnderContract($team, $season, $capMetrics) ?>
<div style="margin-top: 1.5rem;"></div>
<?= $this->renderContractOffers($team, $capMetrics) ?>
<div style="margin-top: 1.5rem;"></div>
<?= $this->renderTeamFreeAgents($team, $season, $capMetrics) ?>
<?= $this->renderOtherFreeAgents($team, $season, $allOtherPlayers) ?>
        <?php
        return ob_get_clean();
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
        return '<div class="ibl-alert ' . $banner['class'] . '">' . \Utilities\HtmlSanitizer::safeHtmlOutput($banner['message']) . '</div>';
    }

    /**
     * Render players under contract table
     *
     * @param object $team Team object
     * @param \Season $season Season object
     * @param array $capMetrics Cap metrics from service
     * @return string HTML table
     */
    private function renderPlayersUnderContract(object $team, \Season $season, array $capMetrics): string
    {
        $teamId = (int) ($team->teamID ?? 0);
        $teamNameStr = htmlspecialchars($team->name ?? '');
        $color1 = htmlspecialchars($team->color1 ?? 'D4AF37');
        $color2 = htmlspecialchars($team->color2 ?? '1e3a5f');

        ob_start();
        ?>
<div style="overflow-x: auto; width: 0; min-width: 100%;">
<table class="sortable fa-under-contract" style="max-width: none;">
    <?= $this->renderColgroups() ?>
    <?= $this->renderTableHeader('Players Under Contract', false, $team) ?>
    <tbody>
        <?php foreach ($team->getRosterUnderContractOrderedByOrdinalResult() as $playerRow): ?>
            <?php
            $player = Player::withPlrRow($this->mysqli_db, $playerRow);

            if (!$player->isPlayerFreeAgent($season)):
                $futureSalaries = $player->getFutureSalaries();
                $playerName = $player->name;
                if ($player->ordinal > \JSB::WAIVERS_ORDINAL) {
                    $playerName .= "*";
                }
            ?>
        <tr>
            <td>
                <?php if ($player->canRookieOption($season->phase)): ?>
                    <a href="modules.php?name=Player&amp;pa=rookieoption&amp;pid=<?= (int) $player->playerID ?>&amp;from=fa">Rookie Option</a>
                <?php endif; ?>
            </td>
            <td><?= htmlspecialchars($player->position ?? '') ?></td>
            <td style="white-space: nowrap;"><a href="modules.php?name=Player&amp;pa=showpage&amp;pid=<?= (int) $player->playerID ?>"><?= htmlspecialchars($playerName ?? '') ?></a></td>
            <td class="ibl-team-cell--colored" style="background-color: #<?= $color1 ?>;">
                <a href="modules.php?name=Team&amp;op=team&amp;teamID=<?= $teamId ?>" class="ibl-team-cell__name" style="color: #<?= $color2 ?>;">
                    <img src="images/logo/new<?= $teamId ?>.png" alt="" class="ibl-team-cell__logo" width="24" height="24" loading="lazy">
                    <span class="ibl-team-cell__text"><?= $teamNameStr ?></span>
                </a>
            </td>
            <td class="sep-team"></td>
            <td><?= (int) $player->age ?></td>
            <?= $this->renderPlayerRatings($player) ?>
            <?php foreach ($futureSalaries as $salary): ?>
                <td><?= (int) $salary ?></td>
            <?php endforeach; ?>
            <?= $this->renderPlayerPreferences($player) ?>
        </tr>
            <?php endif; ?>
        <?php endforeach; ?>
    </tbody>
    <tfoot>
        <tr>
            <td colspan="30" style="text-align: right;"><strong><em><?= htmlspecialchars($team->name) ?> Total Salary</em></strong></td>
            <?php foreach ($capMetrics['totalSalaries'] as $salary): ?>
                <td><strong><em><?= (int) $salary ?></em></strong></td>
            <?php endforeach; ?>
        </tr>
    </tfoot>
</table>
</div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render contract offers table
     *
     * @param object $team Team object
     * @param array $capMetrics Cap metrics from service
     * @return string HTML table
     */
    private function renderContractOffers(object $team, array $capMetrics): string
    {
        $commonRepository = new \Services\CommonMysqliRepository($this->mysqli_db);
        $teamId = (int) ($team->teamID ?? 0);
        $teamNameStr = htmlspecialchars($team->name ?? '');
        $color1 = htmlspecialchars($team->color1 ?? 'D4AF37');
        $color2 = htmlspecialchars($team->color2 ?? '1e3a5f');

        ob_start();
        ?>
<div style="overflow-x: auto; width: 0; min-width: 100%;">
<table class="sortable fa-offers" style="max-width: none;">
    <?= $this->renderColgroups() ?>
    <?= $this->renderTableHeader('Contract Offers', false, $team) ?>
    <tbody>
        <?php foreach ($team->getFreeAgencyOffersResult() as $offerRow): ?>
            <?php
            $playerID = $commonRepository->getPlayerIDFromPlayerName($offerRow['name']);
            $player = Player::withPlayerID($this->mysqli_db, $playerID);
            ?>
        <tr>
            <td><a href="modules.php?name=Free_Agency&amp;pa=negotiate&amp;pid=<?= (int) $player->playerID ?>">Negotiate</a></td>
            <td><?= htmlspecialchars($player->position ?? '') ?></td>
            <td style="white-space: nowrap;"><a href="modules.php?name=Player&amp;pa=showpage&amp;pid=<?= (int) $player->playerID ?>"><?= htmlspecialchars($player->name ?? '') ?></a></td>
            <td class="ibl-team-cell--colored" style="background-color: #<?= $color1 ?>;">
                <a href="modules.php?name=Team&amp;op=team&amp;teamID=<?= $teamId ?>" class="ibl-team-cell__name" style="color: #<?= $color2 ?>;">
                    <img src="images/logo/new<?= $teamId ?>.png" alt="" class="ibl-team-cell__logo" width="24" height="24" loading="lazy">
                    <span class="ibl-team-cell__text"><?= $teamNameStr ?></span>
                </a>
            </td>
            <td class="sep-team"></td>
            <td><?= (int) $player->age ?></td>
            <?= $this->renderPlayerRatings($player) ?>
            <td><?= (int) $offerRow['offer1'] ?></td>
            <td><?= (int) $offerRow['offer2'] ?></td>
            <td><?= (int) $offerRow['offer3'] ?></td>
            <td><?= (int) $offerRow['offer4'] ?></td>
            <td><?= (int) $offerRow['offer5'] ?></td>
            <td><?= (int) $offerRow['offer6'] ?></td>
            <?= $this->renderPlayerPreferences($player) ?>
        </tr>
        <?php endforeach; ?>
    </tbody>
    <tfoot>
        <tr>
            <td colspan="30" style="text-align: right;"><strong><em><?= htmlspecialchars($team->name) ?> Total Salary Plus Contract Offers</em></strong></td>
            <?php foreach ($capMetrics['totalSalaries'] as $salary): ?>
                <td><strong><em><?= (int) $salary ?></em></strong></td>
            <?php endforeach; ?>
        </tr>
        <?= $this->renderCapSpaceFooter($team, $capMetrics) ?>
    </tfoot>
</table>
</div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render team free agents table
     *
     * @param object $team Team object
     * @param \Season $season Season object
     * @param array $capMetrics Cap metrics from service
     * @return string HTML table
     */
    private function renderTeamFreeAgents(object $team, \Season $season, array $capMetrics): string
    {
        $teamId = (int) ($team->teamID ?? 0);
        $teamNameStr = htmlspecialchars($team->name ?? '');
        $color1 = htmlspecialchars($team->color1 ?? 'D4AF37');
        $color2 = htmlspecialchars($team->color2 ?? '1e3a5f');

        ob_start();
        ?>
<div style="overflow-x: auto; width: 0; min-width: 100%;">
<table class="sortable fa-team-free-agents" style="max-width: none;">
    <?= $this->renderColgroups() ?>
    <?= $this->renderTableHeader('Unsigned Free Agents', true, $team) ?>
    <tbody>
        <?php foreach ($team->getRosterUnderContractOrderedByOrdinalResult() as $playerRow): ?>
            <?php
            $player = Player::withPlrRow($this->mysqli_db, $playerRow);

            if ($player->isPlayerFreeAgent($season)):
                $demands = $player->getFreeAgencyDemands();
            ?>
        <tr>
            <td>
                <?php if ($capMetrics['rosterSpots'][0] > 0): ?>
                    <a href="modules.php?name=Free_Agency&amp;pa=negotiate&amp;pid=<?= (int) $player->playerID ?>">Negotiate</a>
                <?php endif; ?>
            </td>
            <td><?= htmlspecialchars($player->position ?? '') ?></td>
            <td style="white-space: nowrap;"><a href="modules.php?name=Player&amp;pa=showpage&amp;pid=<?= (int) $player->playerID ?>">
                <?php if ($player->birdYears >= 3): ?>
                    *<em><?= htmlspecialchars($player->name ?? '') ?></em>*
                <?php else: ?>
                    <?= htmlspecialchars($player->name ?? '') ?>
                <?php endif; ?>
            </a></td>
            <td class="ibl-team-cell--colored" style="background-color: #<?= $color1 ?>;">
                <a href="modules.php?name=Team&amp;op=team&amp;teamID=<?= $teamId ?>" class="ibl-team-cell__name" style="color: #<?= $color2 ?>;">
                    <img src="images/logo/new<?= $teamId ?>.png" alt="" class="ibl-team-cell__logo" width="24" height="24" loading="lazy">
                    <span class="ibl-team-cell__text"><?= $teamNameStr ?></span>
                </a>
            </td>
            <td class="sep-team"></td>
            <td><?= (int) $player->age ?></td>
            <?= $this->renderPlayerRatings($player) ?>
            <?= $this->renderPlayerDemands($demands) ?>
            <?= $this->renderPlayerPreferences($player) ?>
        </tr>
            <?php endif; ?>
        <?php endforeach; ?>
    </tbody>
</table>
</div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render other free agents table
     *
     * @param object $team Team object
     * @param \Season $season Season object
     * @param array $allOtherPlayers Pre-fetched player rows from service
     * @return string HTML table
     */
    private function renderOtherFreeAgents(object $team, \Season $season, array $allOtherPlayers): string
    {
        ob_start();
        ?>
<div style="overflow-x: auto; width: 0; min-width: 100%;">
<table class="sortable fa-other-free-agents" style="max-width: none;">
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
            <td><a href="modules.php?name=Free_Agency&amp;pa=negotiate&amp;pid=<?= (int) $player->playerID ?>">Negotiate</a></td>
            <td><?= htmlspecialchars($player->position ?? '') ?></td>
            <td style="white-space: nowrap;"><a href="modules.php?name=Player&amp;pa=showpage&amp;pid=<?= (int) $player->playerID ?>"><?= htmlspecialchars($player->name ?? '') ?></a></td>
            <?= $this->renderTeamCell($player) ?>
            <td class="sep-team"></td>
            <td><?= (int) $player->age ?></td>
            <?= $this->renderPlayerRatings($player) ?>
            <?= $this->renderPlayerDemands($demands) ?>
            <?= $this->renderPlayerPreferences($player) ?>
        </tr>
            <?php endif; ?>
        <?php endforeach; ?>
    </tbody>
</table>
</div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render colgroups for table column organization
     *
     * @return string HTML colgroup elements
     */
    private function renderColgroups(): string
    {
        ob_start();
        ?>
<colgroup span="4"></colgroup><colgroup span="1"></colgroup><colgroup span="1"></colgroup><colgroup span="6"></colgroup><colgroup span="7"></colgroup><colgroup span="4"></colgroup><colgroup span="4"></colgroup><colgroup span="3"></colgroup><colgroup span="6"></colgroup><colgroup span="5"></colgroup>
        <?php
        return ob_get_clean();
    }

    /**
     * Render table header
     *
     * @param string $title Table title to display in header
     * @param bool $showBirdRightsNote Whether to show the Bird Rights note
     * @param object $team Team object for name display
     * @return string HTML table header
     */
    private function renderTableHeader(string $title, bool $showBirdRightsNote, object $team): string
    {
        $teamName = htmlspecialchars($team->name ?? '');
        $fullTitle = $title;
        if ($title !== 'All Other Free Agents') {
            $fullTitle = $teamName . ' ' . $title;
        }

        ob_start();
        ?>
    <thead>
        <tr>
            <th colspan="41">
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
            <th>Team</th>
            <th class="sep-team"></th>
            <th>Age</th>
            <th>2ga</th>
            <th>2g%</th>
            <th class="sep-weak"></th>
            <th>fta</th>
            <th>ft%</th>
            <th class="sep-weak"></th>
            <th>3ga</th>
            <th>3g%</th>
            <th class="sep-team"></th>
            <th>orb</th>
            <th>drb</th>
            <th>ast</th>
            <th>stl</th>
            <th>tvr</th>
            <th>blk</th>
            <th>foul</th>
            <th class="sep-team"></th>
            <th>oo</th>
            <th>do</th>
            <th>po</th>
            <th>to</th>
            <th class="sep-weak"></th>
            <th>od</th>
            <th>dd</th>
            <th>pd</th>
            <th>td</th>
            <th class="sep-team"></th>
            <th>T</th>
            <th>S</th>
            <th>I</th>
            <th class="sep-team"></th>
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
            <th>Trad</th>
        </tr>
    </thead>
        <?php
        return ob_get_clean();
    }

    /**
     * Render a team cell with colors and logo
     *
     * @param Player $player Player with team data
     * @return string HTML table cell
     */
    private function renderTeamCell(Player $player): string
    {
        $teamId = (int) ($player->teamID ?? 0);

        // Free agents without a team
        if ($teamId === 0) {
            return '<td>Free Agent</td>';
        }

        // Get team colors from database
        $teamColors = \Player\Views\TeamColorHelper::getTeamColors($this->mysqli_db, $teamId);
        $color1 = htmlspecialchars($teamColors['color1'] ?? 'D4AF37');
        $color2 = htmlspecialchars($teamColors['color2'] ?? '1e3a5f');
        $teamName = htmlspecialchars($player->teamName ?? '');

        ob_start();
        ?>
<td class="ibl-team-cell--colored" style="background-color: #<?= $color1 ?>;">
    <a href="modules.php?name=Team&amp;op=team&amp;teamID=<?= $teamId ?>" class="ibl-team-cell__name" style="color: #<?= $color2 ?>;">
        <img src="images/logo/new<?= $teamId ?>.png" alt="" class="ibl-team-cell__logo" width="24" height="24" loading="lazy">
        <span class="ibl-team-cell__text"><?= $teamName ?></span>
    </a>
</td>
        <?php
        return ob_get_clean();
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
<td><?= (int) $player->ratingFieldGoalAttempts ?></td>
<td><?= (int) $player->ratingFieldGoalPercentage ?></td>
<td class="sep-weak"></td>
<td><?= (int) $player->ratingFreeThrowAttempts ?></td>
<td><?= (int) $player->ratingFreeThrowPercentage ?></td>
<td class="sep-weak"></td>
<td><?= (int) $player->ratingThreePointAttempts ?></td>
<td><?= (int) $player->ratingThreePointPercentage ?></td>
<td class="sep-team"></td>
<td><?= (int) $player->ratingOffensiveRebounds ?></td>
<td><?= (int) $player->ratingDefensiveRebounds ?></td>
<td><?= (int) $player->ratingAssists ?></td>
<td><?= (int) $player->ratingSteals ?></td>
<td><?= (int) $player->ratingTurnovers ?></td>
<td><?= (int) $player->ratingBlocks ?></td>
<td><?= (int) $player->ratingFouls ?></td>
<td class="sep-team"></td>
<td><?= (int) $player->ratingOutsideOffense ?></td>
<td><?= (int) $player->ratingDriveOffense ?></td>
<td><?= (int) $player->ratingPostOffense ?></td>
<td><?= (int) $player->ratingTransitionOffense ?></td>
<td class="sep-weak"></td>
<td><?= (int) $player->ratingOutsideDefense ?></td>
<td><?= (int) $player->ratingDriveDefense ?></td>
<td><?= (int) $player->ratingPostDefense ?></td>
<td><?= (int) $player->ratingTransitionDefense ?></td>
<td class="sep-team"></td>
<td><?= (int) $player->ratingTalent ?></td>
<td><?= (int) $player->ratingSkill ?></td>
<td><?= (int) $player->ratingIntangibles ?></td>
<td class="sep-team"></td>
        <?php
        return ob_get_clean();
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
<td><?= (int) $player->freeAgencyLoyalty ?></td>
<td><?= (int) $player->freeAgencyPlayForWinner ?></td>
<td><?= (int) $player->freeAgencyPlayingTime ?></td>
<td><?= (int) $player->freeAgencySecurity ?></td>
<td><?= (int) $player->freeAgencyTradition ?></td>
        <?php
        return ob_get_clean();
    }

    /**
     * Render player demands cells
     *
     * @param array<string, mixed> $demands
     * @return string HTML table cells
     */
    private function renderPlayerDemands(array $demands): string
    {
        ob_start();
        echo '<td>' . ($demands['dem1'] !== 0 ? (int) $demands['dem1'] : '') . '</td>';
        echo '<td>' . ($demands['dem2'] !== 0 ? (int) $demands['dem2'] : '') . '</td>';
        echo '<td>' . ($demands['dem3'] !== 0 ? (int) $demands['dem3'] : '') . '</td>';
        echo '<td>' . ($demands['dem4'] !== 0 ? (int) $demands['dem4'] : '') . '</td>';
        echo '<td>' . ($demands['dem5'] !== 0 ? (int) $demands['dem5'] : '') . '</td>';
        echo '<td>' . ($demands['dem6'] !== 0 ? (int) $demands['dem6'] : '') . '</td>';
        return ob_get_clean();
    }

    /**
     * Render cap space footer rows
     *
     * @param object $team Team object
     * @param array $capMetrics Cap metrics from service
     * @return string HTML table rows
     */
    private function renderCapSpaceFooter(object $team, array $capMetrics): string
    {
        $MLEicon = ($team->hasMLE === "1") ? "\u{2705}" : "\u{274C}";
        $LLEicon = ($team->hasLLE === "1") ? "\u{2705}" : "\u{274C}";

        ob_start();
        ?>
<tr style="background-color: #cc0000;">
    <td style="text-align: right; color: white;"><strong>MLE:</strong></td>
    <td><?= $MLEicon ?></td>
    <td colspan="28" style="background-color: #eeeeee;"></td>
    <td colspan="8" style="text-align: right; color: white;"><strong>Soft Cap Space</strong></td>
    <?php foreach ($capMetrics['softCapSpace'] as $capSpace): ?>
        <td><?= (int) $capSpace ?></td>
    <?php endforeach; ?>
</tr>
<tr style="background-color: #cc0000;">
    <td style="text-align: right; color: white;"><strong>LLE:</strong></td>
    <td><?= $LLEicon ?></td>
    <td colspan="28" style="background-color: #eeeeee;"></td>
    <td colspan="8" style="text-align: right; color: white;"><strong>Hard Cap Space</strong></td>
    <?php foreach ($capMetrics['hardCapSpace'] as $capSpace): ?>
        <td><?= (int) $capSpace ?></td>
    <?php endforeach; ?>
</tr>
<tr style="background-color: #cc0000;">
    <td colspan="30" style="background-color: #eeeeee;"></td>
    <td colspan="8" style="text-align: right; color: white;"><strong>Empty Roster Slots</strong></td>
    <?php foreach ($capMetrics['rosterSpots'] as $spots): ?>
        <td><?= (int) $spots ?></td>
    <?php endforeach; ?>
</tr>
        <?php
        return ob_get_clean();
    }
}
