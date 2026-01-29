<?php

declare(strict_types=1);

namespace FreeAgency;

use FreeAgency\Contracts\FreeAgencyDisplayHelperInterface;
use Player\Player;

/**
 * @see FreeAgencyDisplayHelperInterface
 */
class FreeAgencyDisplayHelper implements FreeAgencyDisplayHelperInterface
{
    private object $mysqli_db;
    private FreeAgencyRepository $repository;
    private array $capMetrics;
    private $team;
    private $season;

    public function __construct(object $mysqli_db, $team, $season)
    {
        $this->mysqli_db = $mysqli_db;
        $this->team = $team;
        $this->season = $season;
        $this->repository = new FreeAgencyRepository($mysqli_db);
        $this->initializeCapMetrics();
    }

    /**
     * Initialize cap data from the cap calculator
     *
     * @return void
     */
    private function initializeCapMetrics(): void
    {
        $capCalculator = new FreeAgencyCapCalculator($this->mysqli_db, $this->team, $this->season);
        $this->capMetrics = $capCalculator->calculateTeamCapMetrics();
    }

    /**
     * @see FreeAgencyDisplayHelperInterface::renderMainPage()
     */
    public function renderMainPage(): string
    {
        ob_start();
        // Generate team-colored table styles for all 4 tables
        $teamColor = $this->team->color1 ?? 'D4AF37';
        $teamColor2 = $this->team->color2 ?? '1e3a5f';
        echo \UI\TableStyles::render('fa-under-contract', $teamColor, $teamColor2);
        echo \UI\TableStyles::render('fa-offers', $teamColor, $teamColor2);
        echo \UI\TableStyles::render('fa-team-free-agents', $teamColor, $teamColor2);
        echo \UI\TableStyles::render('fa-other-free-agents', '666666', 'ffffff');
        ?>
<img src="images/logo/<?= (int) $this->team->teamID ?>.jpg" alt="Team Logo" class="team-logo-banner">
<p>
<?= $this->renderPlayersUnderContract() ?>
<p>
<?= $this->renderContractOffers() ?>
<p>
<hr>
<p>
<?= $this->renderTeamFreeAgents() ?>
<?= $this->renderOtherFreeAgents() ?>
        <?php
        return ob_get_clean();
    }

    /**
     * Render players under contract table
     *
     * @return string HTML table
     */
    private function renderPlayersUnderContract(): string
    {
        $teamId = (int) ($this->team->teamID ?? 0);
        $teamCity = htmlspecialchars($this->team->city ?? '');
        $teamNameStr = htmlspecialchars($this->team->name ?? '');
        $color1 = htmlspecialchars($this->team->color1 ?? 'D4AF37');
        $color2 = htmlspecialchars($this->team->color2 ?? '1e3a5f');

        ob_start();
        ?>
<table style="margin: 0 auto;" class="sortable fa-under-contract">
    <?= $this->renderColgroups() ?>
    <?= $this->renderTableHeader('Players Under Contract') ?>
    <tbody>
        <?php foreach ($this->team->getRosterUnderContractOrderedByOrdinalResult() as $playerRow): ?>
            <?php
            $player = Player::withPlrRow($this->mysqli_db, $playerRow);

            if (!$player->isPlayerFreeAgent($this->season)):
                $futureSalaries = $player->getFutureSalaries();
                $playerName = $player->name;
                if ($player->ordinal > \JSB::WAIVERS_ORDINAL) {
                    $playerName .= "*";
                }
            ?>
        <tr>
            <td style="text-align: center;">
                <?php if ($player->canRookieOption($this->season->phase)): ?>
                    <a href="modules.php?name=Player&amp;pa=rookieoption&amp;pid=<?= (int) $player->playerID ?>">Rookie Option</a>
                <?php endif; ?>
            </td>
            <td style="text-align: center;"><?= htmlspecialchars($player->position ?? '') ?></td>
            <td style="white-space: nowrap;"><a href="modules.php?name=Player&amp;pa=showpage&amp;pid=<?= (int) $player->playerID ?>"><?= htmlspecialchars($playerName ?? '') ?></a></td>
            <td class="ibl-team-cell--colored" style="background-color: #<?= $color1 ?>;">
                <a href="modules.php?name=Team&amp;op=team&amp;teamID=<?= $teamId ?>" class="ibl-team-cell__name" style="color: #<?= $color2 ?>;">
                    <img src="images/logo/new<?= $teamId ?>.png" alt="" class="ibl-team-cell__logo" width="24" height="24" loading="lazy">
                    <span class="ibl-team-cell__text"><?= $teamCity ?> <?= $teamNameStr ?></span>
                </a>
            </td>
            <td class="sep-team"></td>
            <td style="text-align: center;"><?= (int) $player->age ?></td>
            <?= $this->renderPlayerRatings($player) ?>
            <?php foreach ($futureSalaries as $salary): ?>
                <td style="text-align: center;"><?= (int) $salary ?></td>
            <?php endforeach; ?>
            <?= $this->renderPlayerPreferences($player) ?>
        </tr>
            <?php endif; ?>
        <?php endforeach; ?>
    </tbody>
    <tfoot>
        <tr>
            <td colspan="30" style="text-align: right;"><strong><em><?= htmlspecialchars($this->team->name) ?> Total Salary</em></strong></td>
            <?php foreach ($this->capMetrics['totalSalaries'] as $salary): ?>
                <td style="text-align: center;"><strong><em><?= (int) $salary ?></em></strong></td>
            <?php endforeach; ?>
        </tr>
    </tfoot>
</table>
        <?php
        return ob_get_clean();
    }

    /**
     * Render contract offers table
     *
     * @return string HTML table
     */
    private function renderContractOffers(): string
    {
        $commonRepository = new \Services\CommonMysqliRepository($this->mysqli_db);
        $teamId = (int) ($this->team->teamID ?? 0);
        $teamCity = htmlspecialchars($this->team->city ?? '');
        $teamNameStr = htmlspecialchars($this->team->name ?? '');
        $color1 = htmlspecialchars($this->team->color1 ?? 'D4AF37');
        $color2 = htmlspecialchars($this->team->color2 ?? '1e3a5f');

        ob_start();
        ?>
<table style="margin: 0 auto;" class="sortable fa-offers">
    <?= $this->renderColgroups() ?>
    <?= $this->renderTableHeader('Contract Offers') ?>
    <tbody>
        <?php foreach ($this->team->getFreeAgencyOffersResult() as $offerRow): ?>
            <?php
            $playerID = $commonRepository->getPlayerIDFromPlayerName($offerRow['name']);
            $player = Player::withPlayerID($this->mysqli_db, $playerID);
            ?>
        <tr>
            <td style="text-align: center;"><a href="modules.php?name=Free_Agency&amp;pa=negotiate&amp;pid=<?= (int) $player->playerID ?>">Negotiate</a></td>
            <td style="text-align: center;"><?= htmlspecialchars($player->position ?? '') ?></td>
            <td style="white-space: nowrap;"><a href="modules.php?name=Player&amp;pa=showpage&amp;pid=<?= (int) $player->playerID ?>"><?= htmlspecialchars($player->name ?? '') ?></a></td>
            <td class="ibl-team-cell--colored" style="background-color: #<?= $color1 ?>;">
                <a href="modules.php?name=Team&amp;op=team&amp;teamID=<?= $teamId ?>" class="ibl-team-cell__name" style="color: #<?= $color2 ?>;">
                    <img src="images/logo/new<?= $teamId ?>.png" alt="" class="ibl-team-cell__logo" width="24" height="24" loading="lazy">
                    <span class="ibl-team-cell__text"><?= $teamCity ?> <?= $teamNameStr ?></span>
                </a>
            </td>
            <td class="sep-team"></td>
            <td style="text-align: center;"><?= (int) $player->age ?></td>
            <?= $this->renderPlayerRatings($player) ?>
            <td style="text-align: center;"><?= (int) $offerRow['offer1'] ?></td>
            <td style="text-align: center;"><?= (int) $offerRow['offer2'] ?></td>
            <td style="text-align: center;"><?= (int) $offerRow['offer3'] ?></td>
            <td style="text-align: center;"><?= (int) $offerRow['offer4'] ?></td>
            <td style="text-align: center;"><?= (int) $offerRow['offer5'] ?></td>
            <td style="text-align: center;"><?= (int) $offerRow['offer6'] ?></td>
            <?= $this->renderPlayerPreferences($player) ?>
        </tr>
        <?php endforeach; ?>
    </tbody>
    <tfoot>
        <tr>
            <td colspan="30" style="text-align: right;"><strong><em><?= htmlspecialchars($this->team->name) ?> Total Salary Plus Contract Offers</em></strong></td>
            <?php foreach ($this->capMetrics['totalSalaries'] as $salary): ?>
                <td style="text-align: center;"><strong><em><?= (int) $salary ?></em></strong></td>
            <?php endforeach; ?>
        </tr>
        <?= $this->renderCapSpaceFooter() ?>
    </tfoot>
</table>
        <?php
        return ob_get_clean();
    }

    /**
     * Render team free agents table
     *
     * @return string HTML table
     */
    private function renderTeamFreeAgents(): string
    {
        $teamId = (int) ($this->team->teamID ?? 0);
        $teamCity = htmlspecialchars($this->team->city ?? '');
        $teamNameStr = htmlspecialchars($this->team->name ?? '');
        $color1 = htmlspecialchars($this->team->color1 ?? 'D4AF37');
        $color2 = htmlspecialchars($this->team->color2 ?? '1e3a5f');

        ob_start();
        ?>
<table style="margin: 0 auto;" class="sortable fa-team-free-agents">
    <?= $this->renderColgroups() ?>
    <?= $this->renderTableHeader('Unsigned Free Agents', true) ?>
    <tbody>
        <?php foreach ($this->team->getRosterUnderContractOrderedByOrdinalResult() as $playerRow): ?>
            <?php
            $player = Player::withPlrRow($this->mysqli_db, $playerRow);

            if ($player->isPlayerFreeAgent($this->season)):
                $demands = $player->getFreeAgencyDemands();
            ?>
        <tr>
            <td style="text-align: center;">
                <?php if ($this->capMetrics['rosterSpots'][0] > 0): ?>
                    <a href="modules.php?name=Free_Agency&amp;pa=negotiate&amp;pid=<?= (int) $player->playerID ?>">Negotiate</a>
                <?php endif; ?>
            </td>
            <td style="text-align: center;"><?= htmlspecialchars($player->position ?? '') ?></td>
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
                    <span class="ibl-team-cell__text"><?= $teamCity ?> <?= $teamNameStr ?></span>
                </a>
            </td>
            <td class="sep-team"></td>
            <td style="text-align: center;"><?= (int) $player->age ?></td>
            <?= $this->renderPlayerRatings($player) ?>
            <?= $this->renderPlayerDemands($demands) ?>
            <?= $this->renderPlayerPreferences($player) ?>
        </tr>
            <?php endif; ?>
        <?php endforeach; ?>
    </tbody>
</table>
        <?php
        return ob_get_clean();
    }

    /**
     * Render other free agents table
     *
     * @return string HTML table
     */
    private function renderOtherFreeAgents(): string
    {
        ob_start();
        ?>
<table style="margin: 0 auto;" class="sortable fa-other-free-agents">
    <?= $this->renderColgroups() ?>
    <?= $this->renderTableHeader('All Other Free Agents') ?>
    <tbody>
        <?php
        $allPlayers = $this->repository->getAllPlayersExcludingTeam($this->team->name);

        foreach ($allPlayers as $playerRow):
            $player = Player::withPlrRow($this->mysqli_db, $playerRow);

            if ($player->isPlayerFreeAgent($this->season)):
                $demands = $player->getFreeAgencyDemands();
                $pTeamId = (int) ($player->teamID ?? 0);
        ?>
        <tr>
            <td style="text-align: center;"><a href="modules.php?name=Free_Agency&amp;pa=negotiate&amp;pid=<?= (int) $player->playerID ?>">Negotiate</a></td>
            <td style="text-align: center;"><?= htmlspecialchars($player->position ?? '') ?></td>
            <td style="white-space: nowrap;"><a href="modules.php?name=Player&amp;pa=showpage&amp;pid=<?= (int) $player->playerID ?>"><?= htmlspecialchars($player->name ?? '') ?></a></td>
            <?= $this->renderTeamCell($player) ?>
            <td class="sep-team"></td>
            <td style="text-align: center;"><?= (int) $player->age ?></td>
            <?= $this->renderPlayerRatings($player) ?>
            <?= $this->renderPlayerDemands($demands) ?>
            <?= $this->renderPlayerPreferences($player) ?>
        </tr>
            <?php endif; ?>
        <?php endforeach; ?>
    </tbody>
</table>
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
     * @return string HTML table header
     */
    private function renderTableHeader(string $title = '', bool $showBirdRightsNote = false): string
    {
        $teamName = htmlspecialchars($this->team->name ?? '');
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

        // Fetch team city from database
        $stmt = $this->mysqli_db->prepare('SELECT team_city FROM ibl_team_info WHERE teamid = ?');
        $stmt->bind_param('i', $teamId);
        $stmt->execute();
        $result = $stmt->get_result();
        $teamCity = '';
        if ($row = $result->fetch_assoc()) {
            $teamCity = htmlspecialchars($row['team_city'] ?? '');
        }
        $stmt->close();

        ob_start();
        ?>
<td class="ibl-team-cell--colored" style="background-color: #<?= $color1 ?>;">
    <a href="modules.php?name=Team&amp;op=team&amp;teamID=<?= $teamId ?>" class="ibl-team-cell__name" style="color: #<?= $color2 ?>;">
        <img src="images/logo/new<?= $teamId ?>.png" alt="" class="ibl-team-cell__logo" width="24" height="24" loading="lazy">
        <span class="ibl-team-cell__text"><?= $teamCity ?> <?= $teamName ?></span>
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
<td style="text-align: center;"><?= (int) $player->ratingFieldGoalAttempts ?></td>
<td style="text-align: center;"><?= (int) $player->ratingFieldGoalPercentage ?></td>
<td class="sep-weak"></td>
<td style="text-align: center;"><?= (int) $player->ratingFreeThrowAttempts ?></td>
<td style="text-align: center;"><?= (int) $player->ratingFreeThrowPercentage ?></td>
<td class="sep-weak"></td>
<td style="text-align: center;"><?= (int) $player->ratingThreePointAttempts ?></td>
<td style="text-align: center;"><?= (int) $player->ratingThreePointPercentage ?></td>
<td class="sep-team"></td>
<td style="text-align: center;"><?= (int) $player->ratingOffensiveRebounds ?></td>
<td style="text-align: center;"><?= (int) $player->ratingDefensiveRebounds ?></td>
<td style="text-align: center;"><?= (int) $player->ratingAssists ?></td>
<td style="text-align: center;"><?= (int) $player->ratingSteals ?></td>
<td style="text-align: center;"><?= (int) $player->ratingTurnovers ?></td>
<td style="text-align: center;"><?= (int) $player->ratingBlocks ?></td>
<td style="text-align: center;"><?= (int) $player->ratingFouls ?></td>
<td class="sep-team"></td>
<td style="text-align: center;"><?= (int) $player->ratingOutsideOffense ?></td>
<td style="text-align: center;"><?= (int) $player->ratingDriveOffense ?></td>
<td style="text-align: center;"><?= (int) $player->ratingPostOffense ?></td>
<td style="text-align: center;"><?= (int) $player->ratingTransitionOffense ?></td>
<td class="sep-weak"></td>
<td style="text-align: center;"><?= (int) $player->ratingOutsideDefense ?></td>
<td style="text-align: center;"><?= (int) $player->ratingDriveDefense ?></td>
<td style="text-align: center;"><?= (int) $player->ratingPostDefense ?></td>
<td style="text-align: center;"><?= (int) $player->ratingTransitionDefense ?></td>
<td class="sep-team"></td>
<td style="text-align: center;"><?= (int) $player->ratingTalent ?></td>
<td style="text-align: center;"><?= (int) $player->ratingSkill ?></td>
<td style="text-align: center;"><?= (int) $player->ratingIntangibles ?></td>
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
<td style="text-align: center;"><?= (int) $player->freeAgencyLoyalty ?></td>
<td style="text-align: center;"><?= (int) $player->freeAgencyPlayForWinner ?></td>
<td style="text-align: center;"><?= (int) $player->freeAgencyPlayingTime ?></td>
<td style="text-align: center;"><?= (int) $player->freeAgencySecurity ?></td>
<td style="text-align: center;"><?= (int) $player->freeAgencyTradition ?></td>
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
        echo '<td style="text-align: center;">' . ($demands['dem1'] !== 0 ? (int) $demands['dem1'] : '') . '</td>';
        echo '<td style="text-align: center;">' . ($demands['dem2'] !== 0 ? (int) $demands['dem2'] : '') . '</td>';
        echo '<td style="text-align: center;">' . ($demands['dem3'] !== 0 ? (int) $demands['dem3'] : '') . '</td>';
        echo '<td style="text-align: center;">' . ($demands['dem4'] !== 0 ? (int) $demands['dem4'] : '') . '</td>';
        echo '<td style="text-align: center;">' . ($demands['dem5'] !== 0 ? (int) $demands['dem5'] : '') . '</td>';
        echo '<td style="text-align: center;">' . ($demands['dem6'] !== 0 ? (int) $demands['dem6'] : '') . '</td>';
        return ob_get_clean();
    }

    /**
     * Render cap space footer rows
     *
     * @return string HTML table rows
     */
    private function renderCapSpaceFooter(): string
    {
        $MLEicon = ($this->team->hasMLE === "1") ? "\u{2705}" : "\u{274C}";
        $LLEicon = ($this->team->hasLLE === "1") ? "\u{2705}" : "\u{274C}";

        ob_start();
        ?>
<tr style="background-color: #cc0000;">
    <td style="text-align: right; color: white;"><strong>MLE:</strong></td>
    <td style="text-align: center;"><?= $MLEicon ?></td>
    <td colspan="28" style="background-color: #eeeeee;"></td>
    <td colspan="8" style="text-align: right; color: white;"><strong>Soft Cap Space</strong></td>
    <?php foreach ($this->capMetrics['softCapSpace'] as $capSpace): ?>
        <td style="text-align: center;"><?= (int) $capSpace ?></td>
    <?php endforeach; ?>
</tr>
<tr style="background-color: #cc0000;">
    <td style="text-align: right; color: white;"><strong>LLE:</strong></td>
    <td style="text-align: center;"><?= $LLEicon ?></td>
    <td colspan="28" style="background-color: #eeeeee;"></td>
    <td colspan="8" style="text-align: right; color: white;"><strong>Hard Cap Space</strong></td>
    <?php foreach ($this->capMetrics['hardCapSpace'] as $capSpace): ?>
        <td style="text-align: center;"><?= (int) $capSpace ?></td>
    <?php endforeach; ?>
</tr>
<tr style="background-color: #cc0000;">
    <td colspan="30" style="background-color: #eeeeee;"></td>
    <td colspan="8" style="text-align: right; color: white;"><strong>Empty Roster Slots</strong></td>
    <?php foreach ($this->capMetrics['rosterSpots'] as $spots): ?>
        <td style="text-align: center;"><?= (int) $spots ?></td>
    <?php endforeach; ?>
</tr>
        <?php
        return ob_get_clean();
    }
}
