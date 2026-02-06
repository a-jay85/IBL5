<?php

declare(strict_types=1);

namespace SeasonLeaderboards;

use Player\PlayerImageHelper;
use SeasonLeaderboards\Contracts\SeasonLeaderboardsRepositoryInterface;
use UI\TeamCellHelper;
use SeasonLeaderboards\Contracts\SeasonLeaderboardsServiceInterface;
use SeasonLeaderboards\Contracts\SeasonLeaderboardsViewInterface;

/**
 * @see SeasonLeaderboardsViewInterface
 *
 * @phpstan-import-type TeamRow from SeasonLeaderboardsRepositoryInterface
 * @phpstan-import-type LeaderboardFilters from SeasonLeaderboardsRepositoryInterface
 * @phpstan-import-type ProcessedStats from SeasonLeaderboardsServiceInterface
 */
class SeasonLeaderboardsView implements SeasonLeaderboardsViewInterface
{
    private SeasonLeaderboardsService $service;

    public function __construct(SeasonLeaderboardsService $service)
    {
        $this->service = $service;
    }

    /**
     * @see SeasonLeaderboardsViewInterface::renderFilterForm()
     *
     * @param list<TeamRow> $teams Array of team data
     * @param list<int> $years Array of available years
     * @param LeaderboardFilters $currentFilters Current filter values
     */
    public function renderFilterForm(array $teams, array $years, array $currentFilters): string
    {
        $selectedTeam = (int) ($currentFilters['team'] ?? 0);
        $selectedYear = (string) ($currentFilters['year'] ?? '');
        $selectedSort = (string) ($currentFilters['sortby'] ?? '1');
        $limitValue = (string) ($currentFilters['limit'] ?? '');

        ob_start();
        ?>
<form name="Leaderboards" method="post" action="modules.php?name=SeasonLeaderboards" class="ibl-filter-form">
    <div class="ibl-filter-form__row">
        <div class="ibl-filter-form__group">
            <label class="ibl-filter-form__label">Team:</label>
            <select name="team">
                <?php echo $this->renderTeamOptions($teams, $selectedTeam); ?>
            </select>
        </div>
        <div class="ibl-filter-form__group">
            <label class="ibl-filter-form__label">Year:</label>
            <select name="year">
                <?php echo $this->renderYearOptions($years, $selectedYear); ?>
            </select>
        </div>
        <div class="ibl-filter-form__group">
            <label class="ibl-filter-form__label">Sort By:</label>
            <select name="sortby">
                <?php echo $this->renderSortOptions($selectedSort); ?>
            </select>
        </div>
        <div class="ibl-filter-form__group">
            <label class="ibl-filter-form__label">Limit:</label>
            <input type="number" name="limit" value="<?= htmlspecialchars($limitValue, ENT_QUOTES | ENT_HTML5) ?>" min="1" placeholder="50">
            <span class="ibl-filter-form__label">Records</span>
        </div>
        <button type="submit" class="ibl-filter-form__submit">Search Season Data</button>
    </div>
</form>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Render team dropdown options
     *
     * @param list<TeamRow> $teams Array of team data
     * @param int $selectedTeam Selected team ID
     * @return string HTML options
     */
    private function renderTeamOptions(array $teams, int $selectedTeam): string
    {
        $html = '<option value="0">All</option>' . "\n";
        foreach ($teams as $team) {
            $tid = $team['TeamID'];
            $teamName = $team['Team'];
            $selected = ($selectedTeam === $tid) ? ' selected' : '';
            $html .= '<option value="' . $tid . '"' . $selected . '>' . htmlspecialchars($teamName, ENT_QUOTES | ENT_HTML5) . '</option>' . "\n";
        }
        return $html;
    }

    /**
     * Render year dropdown options
     *
     * @param list<int> $years Available years
     * @param string $selectedYear Selected year
     * @return string HTML options
     */
    private function renderYearOptions(array $years, string $selectedYear): string
    {
        $html = '<option value="">All</option>' . "\n";
        foreach ($years as $year) {
            $selected = ($selectedYear === (string) $year) ? ' selected' : '';
            $html .= '<option value="' . $year . '"' . $selected . '>' . $year . '</option>' . "\n";
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
            $selected = ($i === (int)$selectedSort) ? ' selected' : '';
            $html .= '<option value="' . $i . '"' . $selected . '>' . htmlspecialchars($label, ENT_QUOTES | ENT_HTML5) . '</option>' . "\n";
            $i++;
        }
        return $html;
    }

    /**
     * @see SeasonLeaderboardsViewInterface::renderTableHeader()
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
        return (string) ob_get_clean();
    }

    /**
     * @see SeasonLeaderboardsViewInterface::renderPlayerRow()
     *
     * @param ProcessedStats $stats Formatted player statistics
     */
    public function renderPlayerRow(array $stats, int $rank): string
    {
        $teamId = $stats['teamid'];
        $teamCell = TeamCellHelper::renderTeamCellOrFreeAgent($teamId, $stats['teamname'], $stats['color1'], $stats['color2']);
        $playerCell = PlayerImageHelper::renderFlexiblePlayerCell($stats['pid'], $stats['name'], 'sticky-col-2');

        ob_start();
        ?>
<tr data-team-id="<?= $teamId ?>">
    <td class="rank-cell sticky-col-1"><?= $rank ?>.</td>
    <td><?= $stats['year'] ?></td>
    <?= $playerCell ?>
    <?= $teamCell ?>
    <td><?= $stats['games'] ?></td>
    <td><?= $stats['mpg'] ?></td>
    <td><?= $stats['fgmpg'] ?></td>
    <td><?= $stats['fgapg'] ?></td>
    <td><?= $stats['fgp'] ?></td>
    <td><?= $stats['ftmpg'] ?></td>
    <td><?= $stats['ftapg'] ?></td>
    <td><?= $stats['ftp'] ?></td>
    <td><?= $stats['tgmpg'] ?></td>
    <td><?= $stats['tgapg'] ?></td>
    <td><?= $stats['tgp'] ?></td>
    <td><?= $stats['orbpg'] ?></td>
    <td><?= $stats['rpg'] ?></td>
    <td><?= $stats['apg'] ?></td>
    <td><?= $stats['spg'] ?></td>
    <td><?= $stats['tpg'] ?></td>
    <td><?= $stats['bpg'] ?></td>
    <td><?= $stats['fpg'] ?></td>
    <td><?= $stats['ppg'] ?></td>
    <td><?= $stats['qa'] ?></td>
</tr>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * @see SeasonLeaderboardsViewInterface::renderTableFooter()
     */
    public function renderTableFooter(): string
    {
        return '</tbody></table></div>'; // Close table and scroll container
    }
}
