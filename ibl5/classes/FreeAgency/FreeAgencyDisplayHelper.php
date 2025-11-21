<?php

namespace FreeAgency;

use Player\Player;

/**
 * Handles Free Agency main display page rendering
 * 
 * Renders tables for:
 * - Players under contract
 * - Contract offers
 * - Team free agents
 * - All other free agents
 */
class FreeAgencyDisplayHelper
{
    private $db;
    private \Services\DatabaseService $databaseService;
    private array $capMetrics;
    private $team;
    private $season;

    public function __construct($db, $team, $season)
    {
        $this->db = $db;
        $this->team = $team;
        $this->season = $season;
        $this->databaseService = new \Services\DatabaseService();
        $this->initializeCapMetrics();
    }

    /**
     * Initialize cap data from the cap calculator
     * 
     * @return void
     */
    private function initializeCapMetrics(): void
    {
        $capCalculator = new FreeAgencyCapCalculator($this->db, $this->team);
        $this->capMetrics = $capCalculator->calculateTeamCapMetrics();
    }

    /**
     * Render the complete free agency main page
     * 
     * @return string HTML output
     */
    public function renderMainPage(): string
    {
        ob_start();
        ?>
<center><img src="images/logo/<?= htmlspecialchars($this->team->teamID) ?>.jpg"></center>
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
        ob_start();
        ?>
<table border="1" cellspacing="0" class="sortable">
    <caption style="background-color: #0000cc">
        <center><b><font color="white"><?= htmlspecialchars($this->team->name) ?> Players Under Contract</font></b></center>
    </caption>
    <?= $this->renderTableHeader() ?>
    <tbody>
        <?php foreach ($this->team->getRosterUnderContractOrderedByOrdinalResult() as $playerRow): ?>
            <?php
            $player = Player::withPlrRow($this->db, $playerRow);
            
            if (!$player->isPlayerFreeAgent($this->season)):
                $futureSalaries = $player->getFutureSalaries();
                $playerName = $player->name;
                if ($player->ordinal > \JSB::WAIVERS_ORDINAL) {
                    $playerName .= "*";
                }
            ?>
        <tr>
            <td>
                <?php if ($player->canRookieOption($this->season->phase)): ?>
                    <a href="modules.php?name=Player&pa=rookieoption&pid=<?= htmlspecialchars($player->playerID) ?>">Rookie Option</a>
                <?php endif; ?>
            </td>
            <td><?= htmlspecialchars($player->position) ?></td>
            <td><a href="modules.php?name=Player&pa=showpage&pid=<?= htmlspecialchars($player->playerID) ?>"><?= htmlspecialchars($playerName) ?></a></td>
            <td><a href="modules.php?name=Team&op=team&teamID=<?= htmlspecialchars($player->teamID) ?>"><?= htmlspecialchars($player->teamName) ?></a></td>
            <td><?= htmlspecialchars($player->age) ?></td>
            <?= $this->renderPlayerRatings($player) ?>
            <?php foreach ($futureSalaries as $salary): ?>
                <td><?= htmlspecialchars($salary) ?></td>
            <?php endforeach; ?>
            <?= $this->renderPlayerPreferences($player) ?>
        </tr>
            <?php endif; ?>
        <?php endforeach; ?>
    </tbody>
    <tfoot>
        <tr>
            <td colspan="29" align="right"><b><i><?= htmlspecialchars($this->team->name) ?> Total Salary</i></b></td>
            <?php foreach ($this->capMetrics['totalSalaries'] as $salary): ?>
                <td><b><i><?= htmlspecialchars($salary) ?></i></b></td>
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
        $commonRepository = new \Services\CommonRepository($this->db);
        
        ob_start();
        ?>
<table border="1" cellspacing="0" class="sortable">
    <caption style="background-color: #0000cc">
        <center><b><font color="white"><?= htmlspecialchars($this->team->name) ?> Contract Offers</font></b></center>
    </caption>
    <?= $this->renderTableHeader() ?>
    <tbody>
        <?php foreach ($this->team->getFreeAgencyOffersResult() as $offerRow): ?>
            <?php
            $playerID = $commonRepository->getPlayerIDFromPlayerName($offerRow['name']);
            $player = Player::withPlayerID($this->db, $playerID);
            ?>
        <tr>
            <td><a href="modules.php?name=Free_Agency&pa=negotiate&pid=<?= htmlspecialchars($player->playerID) ?>">Negotiate</a></td>
            <td><?= htmlspecialchars($player->position) ?></td>
            <td><a href="modules.php?name=Player&pa=showpage&pid=<?= htmlspecialchars($player->playerID) ?>"><?= htmlspecialchars($player->name) ?></a></td>
            <td><a href="modules.php?name=Team&op=team&teamID=<?= htmlspecialchars($player->teamID) ?>"><?= htmlspecialchars($player->teamName) ?></a></td>
            <td><?= htmlspecialchars($player->age) ?></td>
            <?= $this->renderPlayerRatings($player) ?>
            <td><?= htmlspecialchars($offerRow['offer1']) ?></td>
            <td><?= htmlspecialchars($offerRow['offer2']) ?></td>
            <td><?= htmlspecialchars($offerRow['offer3']) ?></td>
            <td><?= htmlspecialchars($offerRow['offer4']) ?></td>
            <td><?= htmlspecialchars($offerRow['offer5']) ?></td>
            <td><?= htmlspecialchars($offerRow['offer6']) ?></td>
            <?= $this->renderPlayerPreferences($player) ?>
        </tr>
        <?php endforeach; ?>
    </tbody>
    <tfoot>
        <tr>
            <td colspan="29" align="right"><b><i><?= htmlspecialchars($this->team->name) ?> Total Salary Plus Contract Offers</i></b></td>
            <?php foreach ($this->capMetrics['totalSalaries'] as $salary): ?>
                <td><b><i><?= htmlspecialchars($salary) ?></i></b></td>
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
        ob_start();
        ?>
<table border="1" cellspacing="0" class="sortable">
    <caption style="background-color: #0000cc">
        <center><b><font color="white"><?= htmlspecialchars($this->team->name) ?> Unsigned Free Agents</b><br>
        (Note: * and <i>italicized</i> indicates player has Bird Rights)</font></b></center>
    </caption>
    <?= $this->renderTableHeader() ?>
    <tbody>
        <?php foreach ($this->team->getRosterUnderContractOrderedByOrdinalResult() as $playerRow): ?>
            <?php
            $player = Player::withPlrRow($this->db, $playerRow);
            
            if ($player->isPlayerFreeAgent($this->season)):
                $demands = $this->db->sql_fetchrow($player->getFreeAgencyDemands());
            ?>
        <tr>
            <td>
                <?php if ($this->capMetrics['rosterSpots'][0] > 0): ?>
                    <a href="modules.php?name=Free_Agency&pa=negotiate&pid=<?= htmlspecialchars($player->playerID) ?>">Negotiate</a>
                <?php endif; ?>
            </td>
            <td><?= htmlspecialchars($player->position) ?></td>
            <td><a href="modules.php?name=Player&pa=showpage&pid=<?= htmlspecialchars($player->playerID) ?>">
                <?php if ($player->birdYears >= 3): ?>
                    *<i><?= htmlspecialchars($player->name) ?></i>*
                <?php else: ?>
                    <?= htmlspecialchars($player->name) ?>
                <?php endif; ?>
            </a></td>
            <td><a href="modules.php?name=Team&op=team&teamID=<?= htmlspecialchars($player->teamID) ?>"><?= htmlspecialchars($player->teamName) ?></a></td>
            <td><?= htmlspecialchars($player->age) ?></td>
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
<table border="1" cellspacing="0" class="sortable">
    <caption style="background-color: #0000cc">
        <center><b><font color="white">All Other Free Agents</font></b></center>
    </caption>
    <?= $this->renderTableHeader() ?>
    <tbody>
        <?php
        $escapedTeamName = $this->databaseService->escapeString($this->db, $this->team->name);
        $query = "SELECT * FROM ibl_plr WHERE teamname!='$escapedTeamName' AND retired='0' ORDER BY ordinal ASC";
        $result = $this->db->sql_query($query);
        
        foreach ($result as $playerRow):
            $player = Player::withPlrRow($this->db, $playerRow);
            
            if ($player->isPlayerFreeAgent($this->season)):
                $demands = $this->db->sql_fetchrow($player->getFreeAgencyDemands());
        ?>
        <tr>
            <td><a href="modules.php?name=Free_Agency&pa=negotiate&pid=<?= htmlspecialchars($player->playerID) ?>">Negotiate</a></td>
            <td><?= htmlspecialchars($player->position) ?></td>
            <td><a href="modules.php?name=Player&pa=showpage&pid=<?= htmlspecialchars($player->playerID) ?>"><?= htmlspecialchars($player->name) ?></a></td>
            <td><a href="modules.php?name=Team&op=team&teamID=<?= htmlspecialchars($player->teamID) ?>"><?= htmlspecialchars($player->teamName) ?></a></td>
            <td><?= htmlspecialchars($player->age) ?></td>
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
     * Render table header
     * 
     * @return string HTML table header
     */
    private function renderTableHeader(): string
    {
        ob_start();
        ?>
    <colgroup>
        <col span="5">
        <col span="6" style="background-color: #ddd">
        <col span="7">
        <col span="8" style="background-color: #ddd">
        <col span="3">
        <col span="6" style="background-color: #ddd">
        <col span="5">
    </colgroup>
    <thead>
        <tr>
            <td><b>Options</b></td>
            <td><b>Pos</b></td>
            <td><b>Player</b></td>
            <td><b>Team</b></td>
            <td><b>Age</b></td>
            <td><b>2ga</b></td>
            <td><b>2g%</b></td>
            <td><b>fta</b></td>
            <td><b>ft%</b></td>
            <td><b>3ga</b></td>
            <td><b>3g%</b></td>
            <td><b>orb</b></td>
            <td><b>drb</b></td>
            <td><b>ast</b></td>
            <td><b>stl</b></td>
            <td><b>to</b></td>
            <td><b>blk</b></td>
            <td><b>foul</b></td>
            <td><b>oo</b></td>
            <td><b>do</b></td>
            <td><b>po</b></td>
            <td><b>to</b></td>
            <td><b>od</b></td>
            <td><b>dd</b></td>
            <td><b>pd</b></td>
            <td><b>td</b></td>
            <td><b>T</b></td>
            <td><b>S</b></td>
            <td><b>I</b></td>
            <td><b>Yr1</b></td>
            <td><b>Yr2</b></td>
            <td><b>Yr3</b></td>
            <td><b>Yr4</b></td>
            <td><b>Yr5</b></td>
            <td><b>Yr6</b></td>
            <td><b>Loy</b></td>
            <td><b>PFW</b></td>
            <td><b>PT</b></td>
            <td><b>Sec</b></td>
            <td><b>Trad</b></td>
        </tr>
    </thead>
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
<td><?= htmlspecialchars($player->ratingFieldGoalAttempts) ?></td>
<td><?= htmlspecialchars($player->ratingFieldGoalPercentage) ?></td>
<td><?= htmlspecialchars($player->ratingFreeThrowAttempts) ?></td>
<td><?= htmlspecialchars($player->ratingFreeThrowPercentage) ?></td>
<td><?= htmlspecialchars($player->ratingThreePointAttempts) ?></td>
<td><?= htmlspecialchars($player->ratingThreePointPercentage) ?></td>
<td><?= htmlspecialchars($player->ratingOffensiveRebounds) ?></td>
<td><?= htmlspecialchars($player->ratingDefensiveRebounds) ?></td>
<td><?= htmlspecialchars($player->ratingAssists) ?></td>
<td><?= htmlspecialchars($player->ratingSteals) ?></td>
<td><?= htmlspecialchars($player->ratingTurnovers) ?></td>
<td><?= htmlspecialchars($player->ratingBlocks) ?></td>
<td><?= htmlspecialchars($player->ratingFouls) ?></td>
<td><?= htmlspecialchars($player->ratingOutsideOffense) ?></td>
<td><?= htmlspecialchars($player->ratingDriveOffense) ?></td>
<td><?= htmlspecialchars($player->ratingPostOffense) ?></td>
<td><?= htmlspecialchars($player->ratingTransitionOffense) ?></td>
<td><?= htmlspecialchars($player->ratingOutsideDefense) ?></td>
<td><?= htmlspecialchars($player->ratingDriveDefense) ?></td>
<td><?= htmlspecialchars($player->ratingPostDefense) ?></td>
<td><?= htmlspecialchars($player->ratingTransitionDefense) ?></td>
<td><?= htmlspecialchars($player->ratingTalent) ?></td>
<td><?= htmlspecialchars($player->ratingSkill) ?></td>
<td><?= htmlspecialchars($player->ratingIntangibles) ?></td>
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
<td><?= htmlspecialchars($player->freeAgencyLoyalty) ?></td>
<td><?= htmlspecialchars($player->freeAgencyPlayForWinner) ?></td>
<td><?= htmlspecialchars($player->freeAgencyPlayingTime) ?></td>
<td><?= htmlspecialchars($player->freeAgencySecurity) ?></td>
<td><?= htmlspecialchars($player->freeAgencyTradition) ?></td>
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
        if ($demands['dem1'] != 0) echo "<td>" . htmlspecialchars($demands['dem1']) . "</td>"; else echo "<td></td>";
        if ($demands['dem2'] != 0) echo "<td>" . htmlspecialchars($demands['dem2']) . "</td>"; else echo "<td></td>";
        if ($demands['dem3'] != 0) echo "<td>" . htmlspecialchars($demands['dem3']) . "</td>"; else echo "<td></td>";
        if ($demands['dem4'] != 0) echo "<td>" . htmlspecialchars($demands['dem4']) . "</td>"; else echo "<td></td>";
        if ($demands['dem5'] != 0) echo "<td>" . htmlspecialchars($demands['dem5']) . "</td>"; else echo "<td></td>";
        if ($demands['dem6'] != 0) echo "<td>" . htmlspecialchars($demands['dem6']) . "</td>"; else echo "<td></td>";
        return ob_get_clean();
    }

    /**
     * Render cap space footer rows
     * 
     * @return string HTML table rows
     */
    private function renderCapSpaceFooter(): string
    {
        $MLEicon = ($this->team->hasMLE == "1") ? "\u{2705}" : "\u{274C}";
        $LLEicon = ($this->team->hasLLE == "1") ? "\u{2705}" : "\u{274C}";
        
        ob_start();
        ?>
<tr bgcolor="#cc0000">
    <td align="right"><font color="white"><b>MLE:</b></font></td>
    <td align="center"><?= $MLEicon ?></td>
    <td colspan="19" bgcolor="#eeeeee"></td>
    <td colspan="8" align="right"><font color="white"><b>Soft Cap Space</b></font></td>
    <?php foreach ($this->capMetrics['softCapSpace'] as $capSpace): ?>
        <td><?= htmlspecialchars($capSpace) ?></td>
    <?php endforeach; ?>
</tr>
<tr bgcolor="#cc0000">
    <td align="right"><font color="white"><b>LLE:</b></font></td>
    <td align="center"><?= $LLEicon ?></td>
    <td colspan="19" bgcolor="#eeeeee"></td>
    <td colspan="8" align="right"><font color="white"><b>Hard Cap Space</b></font></td>
    <?php foreach ($this->capMetrics['hardCapSpace'] as $capSpace): ?>
        <td><?= htmlspecialchars($capSpace) ?></td>
    <?php endforeach; ?>
</tr>
<tr bgcolor="#cc0000">
    <td colspan="21" bgcolor="#eeeeee"></td>
    <td colspan="8" align="right"><font color="white"><b>Empty Roster Slots</b></font></td>
    <?php foreach ($this->capMetrics['rosterSpots'] as $spots): ?>
        <td><?= htmlspecialchars($spots) ?></td>
    <?php endforeach; ?>
</tr>
        <?php
        return ob_get_clean();
    }
}
