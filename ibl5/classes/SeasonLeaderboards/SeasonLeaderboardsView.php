<?php

declare(strict_types=1);

namespace SeasonLeaderboards;

use Player\PlayerImageHelper;
use SeasonLeaderboards\Contracts\SeasonLeaderboardsRepositoryInterface;
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
     * @param list<string> $years Array of available years
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
            <input type="number" name="limit" value="<?= htmlspecialchars($limitValue) ?>" min="1" placeholder="50">
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
            $html .= '<option value="' . $tid . '"' . $selected . '>' . htmlspecialchars($teamName) . '</option>' . "\n";
        }
        return $html;
    }

    /**
     * Render year dropdown options
     *
     * @param list<string> $years Available years
     * @param string $selectedYear Selected year
     * @return string HTML options
     */
    private function renderYearOptions(array $years, string $selectedYear): string
    {
        $html = '<option value="">All</option>' . "\n";
        foreach ($years as $year) {
            $escapedYear = htmlspecialchars($year);
            $selected = ($selectedYear === $year) ? ' selected' : '';
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
            $selected = ($i === (int)$selectedSort) ? ' selected' : '';
            $html .= '<option value="' . htmlspecialchars((string)$i) . '"' . $selected . '>' . htmlspecialchars($label) . '</option>' . "\n";
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
        $teamName = htmlspecialchars($stats['teamname']);
        $color1 = htmlspecialchars($stats['color1']);
        $color2 = htmlspecialchars($stats['color2']);

        // Handle free agents (tid=0) gracefully
        if ($teamId === 0) {
            $teamCell = '<td>Free Agent</td>';
        } else {
            $teamCell = '<td class="ibl-team-cell--colored" style="background-color: #' . $color1 . ';">
        <a href="modules.php?name=Team&amp;op=team&amp;teamID=' . $teamId . '" class="ibl-team-cell__name" style="color: #' . $color2 . ';">
            <img src="images/logo/new' . $teamId . '.png" alt="" class="ibl-team-cell__logo" width="24" height="24" loading="lazy">
            <span class="ibl-team-cell__text">' . $teamName . '</span>
        </a>
    </td>';
        }

        ob_start();
        ?>
<tr data-team-id="<?= $teamId ?>">
    <td class="rank-cell sticky-col-1"><?= $rank ?>.</td>
    <td><?= htmlspecialchars($stats['year']) ?></td>
    <?php $resolved = PlayerImageHelper::resolvePlayerDisplay($stats['pid'], $stats['name']); ?>
    <td class="sticky-col-2 ibl-player-cell"><a href="modules.php?name=Player&amp;pa=showpage&amp;pid=<?= $stats['pid'] ?>"><?= $resolved['thumbnail'] ?><?= htmlspecialchars($resolved['name']) ?></a></td>
    <?= $teamCell ?>
    <td><?= $stats['games'] ?></td>
    <td><?= htmlspecialchars($stats['mpg']) ?></td>
    <td><?= htmlspecialchars($stats['fgmpg']) ?></td>
    <td><?= htmlspecialchars($stats['fgapg']) ?></td>
    <td><?= htmlspecialchars($stats['fgp']) ?></td>
    <td><?= htmlspecialchars($stats['ftmpg']) ?></td>
    <td><?= htmlspecialchars($stats['ftapg']) ?></td>
    <td><?= htmlspecialchars($stats['ftp']) ?></td>
    <td><?= htmlspecialchars($stats['tgmpg']) ?></td>
    <td><?= htmlspecialchars($stats['tgapg']) ?></td>
    <td><?= htmlspecialchars($stats['tgp']) ?></td>
    <td><?= htmlspecialchars($stats['orbpg']) ?></td>
    <td><?= htmlspecialchars($stats['rpg']) ?></td>
    <td><?= htmlspecialchars($stats['apg']) ?></td>
    <td><?= htmlspecialchars($stats['spg']) ?></td>
    <td><?= htmlspecialchars($stats['tpg']) ?></td>
    <td><?= htmlspecialchars($stats['bpg']) ?></td>
    <td><?= htmlspecialchars($stats['fpg']) ?></td>
    <td><?= htmlspecialchars($stats['ppg']) ?></td>
    <td><?= htmlspecialchars($stats['qa']) ?></td>
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
