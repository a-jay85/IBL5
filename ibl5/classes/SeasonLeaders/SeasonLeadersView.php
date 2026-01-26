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
    public function renderFilterForm(array $teams, array $years, array $currentFilters): string
    {
        ob_start();
        ?>
<form name="Leaderboards" method="post" action="modules.php?name=Season_Leaders" class="ibl-filter-form">
    <div class="ibl-filter-form__row">
        <div class="ibl-filter-form__group">
            <label class="ibl-filter-form__label">Team:</label>
            <select name="team">
                <?php echo $this->renderTeamOptions($teams, $currentFilters['team'] ?? 0); ?>
            </select>
        </div>
        <div class="ibl-filter-form__group">
            <label class="ibl-filter-form__label">Year:</label>
            <select name="year">
                <?php echo $this->renderYearOptions($years, $currentFilters['year'] ?? ''); ?>
            </select>
        </div>
        <div class="ibl-filter-form__group">
            <label class="ibl-filter-form__label">Sort By:</label>
            <select name="sortby">
                <?php echo $this->renderSortOptions($currentFilters['sortby'] ?? '1'); ?>
            </select>
        </div>
        <button type="submit" class="ibl-filter-form__submit">Search Season Data</button>
    </div>
</form>
        <?php
        return ob_get_clean();
    }

    /**
     * Render team dropdown options
     *
     * @param array $teams Array of team data
     * @param int $selectedTeam Selected team ID
     * @return string HTML options
     */
    private function renderTeamOptions(array $teams, int $selectedTeam): string
    {
        $html = '<option value="0">All</option>' . "\n";
        foreach ($teams as $team) {
            $tid = (int) ($team['TeamID'] ?? 0);
            $teamName = $team['Team'] ?? '';
            $selected = ($selectedTeam == $tid) ? ' selected' : '';
            $html .= '<option value="' . htmlspecialchars((string) $tid) . '"' . $selected . '>' . htmlspecialchars($teamName) . '</option>' . "\n";
        }
        return $html;
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
        $html = '<option value="">All</option>' . "\n";
        foreach ($years as $year) {
            $escapedYear = is_int($year) ? (string)$year : htmlspecialchars((string)$year);
            $selected = ($selectedYear == $year) ? ' selected' : '';
            $html .= '<option value="' . $escapedYear . '"' . $selected . '>' . $escapedYear . '</option>' . "\n";
        }
        return $html;
    }

    /**
     * Render sort by dropdown options
     *
     * @param string $selectedSort Selected sort option
     * @return string HTML options
     */
    private function renderSortOptions(string $selectedSort): string
    {
        $html = '';
        $sortOptions = $this->service->getSortOptions();
        $i = 1;
        foreach ($sortOptions as $label) {
            $selected = ($i == (int)$selectedSort) ? ' selected' : '';
            $html .= '<option value="' . htmlspecialchars((string)$i) . '"' . $selected . '>' . htmlspecialchars($label) . '</option>' . "\n";
            $i++;
        }
        return $html;
    }

    /**
     * @see SeasonLeadersViewInterface::renderTableHeader()
     */
    public function renderTableHeader(): string
    {
        ob_start();
        ?>
<div class="table-scroll-container">
<table class="sortable ibl-data-table responsive-table">
    <thead>
        <tr>
            <th class="sticky-col-1">Rank</th>
            <th>Year</th>
            <th class="sticky-col-2">Name</th>
            <th>Team</th>
            <th>G</th>
            <th>Min</th>
            <th>fgm</th>
            <th>fga</th>
            <th>fg%</th>
            <th>ftm</th>
            <th>fta</th>
            <th>ft%</th>
            <th>tgm</th>
            <th>tga</th>
            <th>tg%</th>
            <th>orb</th>
            <th>reb</th>
            <th>ast</th>
            <th>stl</th>
            <th>to</th>
            <th>blk</th>
            <th>pf</th>
            <th>ppg</th>
            <th>qa</th>
        </tr>
    </thead>
    <tbody>
        <?php
        return ob_get_clean();
    }

    /**
     * @see SeasonLeadersViewInterface::renderPlayerRow()
     */
    public function renderPlayerRow(array $stats, int $rank): string
    {
        ob_start();
        ?>
<tr>
    <td class="rank-cell sticky-col-1"><?= htmlspecialchars((string)$rank) ?>.</td>
    <td><?= htmlspecialchars((string)$stats['year']) ?></td>
    <td class="sticky-col-2"><a href="modules.php?name=Player&amp;pa=showpage&amp;pid=<?= htmlspecialchars((string)$stats['pid']) ?>"><?= htmlspecialchars($stats['name']) ?></a></td>
    <td><a href="modules.php?name=Team&amp;op=team&amp;teamID=<?= htmlspecialchars((string)$stats['teamid']) ?>"><?= htmlspecialchars($stats['teamname']) ?></a></td>
    <td><?= htmlspecialchars((string)$stats['games']) ?></td>
    <td><?= htmlspecialchars((string)$stats['mpg']) ?></td>
    <td><?= htmlspecialchars((string)$stats['fgmpg']) ?></td>
    <td><?= htmlspecialchars((string)$stats['fgapg']) ?></td>
    <td><?= htmlspecialchars((string)$stats['fgp']) ?></td>
    <td><?= htmlspecialchars((string)$stats['ftmpg']) ?></td>
    <td><?= htmlspecialchars((string)$stats['ftapg']) ?></td>
    <td><?= htmlspecialchars((string)$stats['ftp']) ?></td>
    <td><?= htmlspecialchars((string)$stats['tgmpg']) ?></td>
    <td><?= htmlspecialchars((string)$stats['tgapg']) ?></td>
    <td><?= htmlspecialchars((string)$stats['tgp']) ?></td>
    <td><?= htmlspecialchars((string)$stats['orbpg']) ?></td>
    <td><?= htmlspecialchars((string)$stats['rpg']) ?></td>
    <td><?= htmlspecialchars((string)$stats['apg']) ?></td>
    <td><?= htmlspecialchars((string)$stats['spg']) ?></td>
    <td><?= htmlspecialchars((string)$stats['tpg']) ?></td>
    <td><?= htmlspecialchars((string)$stats['bpg']) ?></td>
    <td><?= htmlspecialchars((string)$stats['fpg']) ?></td>
    <td><?= htmlspecialchars((string)$stats['ppg']) ?></td>
    <td><?= htmlspecialchars((string)$stats['qa']) ?></td>
</tr>
        <?php
        return ob_get_clean();
    }

    /**
     * @see SeasonLeadersViewInterface::renderTableFooter()
     */
    public function renderTableFooter(): string
    {
        return '</tbody></table></div>'; // Close table and scroll container
    }
}
