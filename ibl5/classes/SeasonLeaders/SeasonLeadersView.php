<?php

declare(strict_types=1);

namespace SeasonLeaders;

use SeasonLeaders\Contracts\SeasonLeadersViewInterface;

/**
 * @see SeasonLeadersViewInterface
 */
class SeasonLeadersView implements SeasonLeadersViewInterface
{
    private $service;

    public function __construct(SeasonLeadersService $service)
    {
        $this->service = $service;
    }

    /**
     * @see SeasonLeadersViewInterface::renderFilterForm()
     */
    public function renderFilterForm($teams, array $years, array $currentFilters): string
    {
        ob_start();
        ?>
<form name="Leaderboards" method="post" action="modules.php?name=Season_Leaders">
    <table border="1">
        <tr>
            <td><b>Team</b></td>
            <td>
                <select name="team">
                    <?php echo $this->renderTeamOptions($teams, $currentFilters['team'] ?? 0); ?>
                </select>
            </td>
            <td><b>Year</b></td>
            <td>
                <select name="year">
                    <?php echo $this->renderYearOptions($years, $currentFilters['year'] ?? ''); ?>
                </select>
            </td>
            <td><b>Sort By</b></td>
            <td>
                <select name="sortby">
                    <?php echo $this->renderSortOptions($currentFilters['sortby'] ?? '1'); ?>
                </select>
            </td>
            <td><input type="submit" value="Search Season Data"></td>
        </tr>
    </table>
</form>
        <?php
        return ob_get_clean();
    }

    /**
     * Render team dropdown options
     * 
     * @param resource $teams Database result
     * @param int $selectedTeam Selected team ID
     * @return string HTML options
     */
    private function renderTeamOptions($teams, int $selectedTeam): string
    {
        global $db;
        
        ob_start();
        ?>
<option value="0">All</option>
        <?php
        $numTeams = $db->sql_numrows($teams);
        for ($i = 0; $i < $numTeams; $i++) {
            $tid = $db->sql_result($teams, $i, "TeamID");
            $teamName = $db->sql_result($teams, $i, "Team");
            $selected = ($selectedTeam == $tid) ? ' selected' : '';
            ?>
<option value="<?= htmlspecialchars($tid) ?>"<?= $selected ?>><?= htmlspecialchars($teamName) ?></option>
            <?php
        }
        ?>
        <?php
        return ob_get_clean();
    }

    /**
     * Render year dropdown options
     * 
     * @param array $years Available years
     * @param string $selectedYear Selected year
     * @return string HTML options
     */
    private function renderYearOptions(array $years, string $selectedYear): string
    {
        ob_start();
        ?>
<option value="">All</option>
        <?php
        foreach ($years as $year) {
            $selected = ($selectedYear == $year) ? ' selected' : '';
            ?>
<option value="<?= htmlspecialchars($year) ?>"<?= $selected ?>><?= htmlspecialchars($year) ?></option>
            <?php
        }
        ?>
        <?php
        return ob_get_clean();
    }

    /**
     * Render sort by dropdown options
     * 
     * @param string $selectedSort Selected sort option
     * @return string HTML options
     */
    private function renderSortOptions(string $selectedSort): string
    {
        ob_start();
        $sortOptions = $this->service->getSortOptions();
        $i = 1;
        foreach ($sortOptions as $label) {
            $selected = ($i == (int)$selectedSort) ? ' selected' : '';
            ?>
<option value="<?= htmlspecialchars((string)$i) ?>"<?= $selected ?>><?= htmlspecialchars($label) ?></option>
            <?php
            $i++;
        }
        return ob_get_clean();
    }

    /**
     * @see SeasonLeadersViewInterface::renderTableHeader()
     */
    public function renderTableHeader(): string
    {
        ob_start();
        ?>
<table cellpadding="3" cellspacing="0" border="0" style="background-color: #C2D69A;">
    <tr>
        <td><strong>Rank</strong></td>
        <td><strong>Year</strong></td>
        <td><strong>Name</strong></td>
        <td><strong>Team</strong></td>
        <td><strong>G</strong></td>
        <td style="text-align: right;"><strong>Min</strong></td>
        <td style="text-align: right;"><strong>fgm</strong></td>
        <td style="text-align: right;"><strong>fga</strong></td>
        <td style="text-align: right;"><strong>fg%</strong></td>
        <td style="text-align: right;"><strong>ftm</strong></td>
        <td style="text-align: right;"><strong>fta</strong></td>
        <td style="text-align: right;"><strong>ft%</strong></td>
        <td style="text-align: right;"><strong>tgm</strong></td>
        <td style="text-align: right;"><strong>tga</strong></td>
        <td style="text-align: right;"><strong>tg%</strong></td>
        <td style="text-align: right;"><strong>orb</strong></td>
        <td style="text-align: right;"><strong>reb</strong></td>
        <td style="text-align: right;"><strong>ast</strong></td>
        <td style="text-align: right;"><strong>stl</strong></td>
        <td style="text-align: right;"><strong>to</strong></td>
        <td style="text-align: right;"><strong>blk</strong></td>
        <td style="text-align: right;"><strong>pf</strong></td>
        <td style="text-align: right;"><strong>ppg</strong></td>
        <td style="text-align: right;"><strong>qa</strong></td>
    </tr>
        <?php
        return ob_get_clean();
    }

    /**
     * @see SeasonLeadersViewInterface::renderPlayerRow()
     */
    public function renderPlayerRow(array $stats, int $rank): string
    {
        ob_start();
        $bgcolor = ($rank % 2 == 0) ? "#FFFFFF" : "#DDDDDD";
        ?>
<tr style="background-color: <?= $bgcolor; ?>">
    <td><?= htmlspecialchars((string)$rank) ?>.</td>
    <td><?= htmlspecialchars($stats['year']) ?></td>
    <td><a href="modules.php?name=Player&pa=showpage&pid=<?= htmlspecialchars($stats['pid']) ?>"><?= htmlspecialchars($stats['name']) ?></a></td>
    <td><a href="modules.php?name=Team&op=team&teamID=<?= htmlspecialchars($stats['teamid']) ?>"><?= htmlspecialchars($stats['teamname']) ?></a></td>
    <td><?= htmlspecialchars($stats['games']) ?></td>
    <td style="text-align: right;"><?= htmlspecialchars($stats['mpg']) ?></td>
    <td style="text-align: right;"><?= htmlspecialchars($stats['fgmpg']) ?></td>
    <td style="text-align: right;"><?= htmlspecialchars($stats['fgapg']) ?></td>
    <td style="text-align: right;"><?= htmlspecialchars($stats['fgp']) ?></td>
    <td style="text-align: right;"><?= htmlspecialchars($stats['ftmpg']) ?></td>
    <td style="text-align: right;"><?= htmlspecialchars($stats['ftapg']) ?></td>
    <td style="text-align: right;"><?= htmlspecialchars($stats['ftp']) ?></td>
    <td style="text-align: right;"><?= htmlspecialchars($stats['tgmpg']) ?></td>
    <td style="text-align: right;"><?= htmlspecialchars($stats['tgapg']) ?></td>
    <td style="text-align: right;"><?= htmlspecialchars($stats['tgp']) ?></td>
    <td style="text-align: right;"><?= htmlspecialchars($stats['orbpg']) ?></td>
    <td style="text-align: right;"><?= htmlspecialchars($stats['rpg']) ?></td>
    <td style="text-align: right;"><?= htmlspecialchars($stats['apg']) ?></td>
    <td style="text-align: right;"><?= htmlspecialchars($stats['spg']) ?></td>
    <td style="text-align: right;"><?= htmlspecialchars($stats['tpg']) ?></td>
    <td style="text-align: right;"><?= htmlspecialchars($stats['bpg']) ?></td>
    <td style="text-align: right;"><?= htmlspecialchars($stats['fpg']) ?></td>
    <td style="text-align: right;"><?= htmlspecialchars($stats['ppg']) ?></td>
    <td style="text-align: right;"><?= htmlspecialchars($stats['qa']) ?></td>
</tr>
        <?php
        return ob_get_clean();
    }

    /**
     * @see SeasonLeadersViewInterface::renderTableFooter()
     */
    public function renderTableFooter(): string
    {
        return '</table>';
    }
}
