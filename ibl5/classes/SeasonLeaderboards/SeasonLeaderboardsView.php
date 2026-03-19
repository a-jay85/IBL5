<?php

declare(strict_types=1);

namespace SeasonLeaderboards;

use Player\PlayerImageHelper;
use SeasonLeaderboards\Contracts\SeasonLeaderboardsRepositoryInterface;
use UI\TeamCellHelper;
use SeasonLeaderboards\Contracts\SeasonLeaderboardsServiceInterface;
use SeasonLeaderboards\Contracts\SeasonLeaderboardsViewInterface;
use Utilities\HtmlSanitizer;

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
    private string $activeSortColumn = 'ppg';

    private const SORT_TO_COLUMN = [
        '1' => 'ppg',    '2' => 'rpg',    '3' => 'orbpg',  '4' => 'apg',
        '5' => 'spg',    '6' => 'bpg',    '7' => 'tpg',    '8' => 'fpg',
        '9' => 'qa',     '10' => 'fgmpg', '11' => 'fgapg', '12' => 'fgp',
        '13' => 'ftmpg', '14' => 'ftapg', '15' => 'ftp',   '16' => 'tgmpg',
        '17' => 'tgapg', '18' => 'tgp',   '19' => 'games', '20' => 'mpg',
    ];

    public function __construct(SeasonLeaderboardsService $service)
    {
        $this->service = $service;
    }

    public function setSortBy(string $sortBy): void
    {
        $this->activeSortColumn = self::SORT_TO_COLUMN[$sortBy] ?? 'ppg';
    }

    private function sortAttr(string $statKey): string
    {
        return $this->activeSortColumn === $statKey ? ' class="sorted-col"' : '';
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
            <label for="sl-team" class="ibl-filter-form__label">Team:</label>
            <select id="sl-team" name="team">
                <?php echo $this->renderTeamOptions($teams, $selectedTeam); ?>
            </select>
        </div>
        <div class="ibl-filter-form__group">
            <label for="sl-year" class="ibl-filter-form__label">Year:</label>
            <select id="sl-year" name="year">
                <?php echo $this->renderYearOptions($years, $selectedYear); ?>
            </select>
        </div>
        <div class="ibl-filter-form__group">
            <label for="sl-sortby" class="ibl-filter-form__label">Sort By:</label>
            <select id="sl-sortby" name="sortby">
                <?php echo $this->renderSortOptions($selectedSort); ?>
            </select>
        </div>
        <div class="ibl-filter-form__group">
            <label for="sl-limit" class="ibl-filter-form__label">Limit:</label>
            <input id="sl-limit" type="number" name="limit" value="<?= HtmlSanitizer::e($limitValue) ?>" min="1" placeholder="50">
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
            $html .= '<option value="' . $tid . '"' . $selected . '>' . HtmlSanitizer::e($teamName) . '</option>' . "\n";
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
            $html .= '<option value="' . $i . '"' . $selected . '>' . HtmlSanitizer::e($label) . '</option>' . "\n";
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
<div class="table-scroll-container" tabindex="0" role="region" aria-label="Season leaderboards">
<table class="sortable ibl-data-table responsive-table">
    <thead>
        <tr>
            <th class="sticky-col-1">Rank</th>
            <th>Year</th>
            <th class="sticky-col-2">Name</th>
            <th>Team</th>
            <th<?= $this->sortAttr('games') ?>>G</th>
            <th<?= $this->sortAttr('mpg') ?>>Min</th>
            <th<?= $this->sortAttr('fgmpg') ?>>fgm</th>
            <th<?= $this->sortAttr('fgapg') ?>>fga</th>
            <th<?= $this->sortAttr('fgp') ?>>fg%</th>
            <th<?= $this->sortAttr('ftmpg') ?>>ftm</th>
            <th<?= $this->sortAttr('ftapg') ?>>fta</th>
            <th<?= $this->sortAttr('ftp') ?>>ft%</th>
            <th<?= $this->sortAttr('tgmpg') ?>>tgm</th>
            <th<?= $this->sortAttr('tgapg') ?>>tga</th>
            <th<?= $this->sortAttr('tgp') ?>>tg%</th>
            <th<?= $this->sortAttr('orbpg') ?>>orb</th>
            <th<?= $this->sortAttr('rpg') ?>>reb</th>
            <th<?= $this->sortAttr('apg') ?>>ast</th>
            <th<?= $this->sortAttr('spg') ?>>stl</th>
            <th<?= $this->sortAttr('tpg') ?>>to</th>
            <th<?= $this->sortAttr('bpg') ?>>blk</th>
            <th<?= $this->sortAttr('fpg') ?>>pf</th>
            <th<?= $this->sortAttr('ppg') ?>>ppg</th>
            <th<?= $this->sortAttr('qa') ?>>qa</th>
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
    <td<?= $this->sortAttr('games') ?>><?= $stats['games'] ?></td>
    <td<?= $this->sortAttr('mpg') ?>><?= $stats['mpg'] ?></td>
    <td<?= $this->sortAttr('fgmpg') ?>><?= $stats['fgmpg'] ?></td>
    <td<?= $this->sortAttr('fgapg') ?>><?= $stats['fgapg'] ?></td>
    <td<?= $this->sortAttr('fgp') ?>><?= $stats['fgp'] ?></td>
    <td<?= $this->sortAttr('ftmpg') ?>><?= $stats['ftmpg'] ?></td>
    <td<?= $this->sortAttr('ftapg') ?>><?= $stats['ftapg'] ?></td>
    <td<?= $this->sortAttr('ftp') ?>><?= $stats['ftp'] ?></td>
    <td<?= $this->sortAttr('tgmpg') ?>><?= $stats['tgmpg'] ?></td>
    <td<?= $this->sortAttr('tgapg') ?>><?= $stats['tgapg'] ?></td>
    <td<?= $this->sortAttr('tgp') ?>><?= $stats['tgp'] ?></td>
    <td<?= $this->sortAttr('orbpg') ?>><?= $stats['orbpg'] ?></td>
    <td<?= $this->sortAttr('rpg') ?>><?= $stats['rpg'] ?></td>
    <td<?= $this->sortAttr('apg') ?>><?= $stats['apg'] ?></td>
    <td<?= $this->sortAttr('spg') ?>><?= $stats['spg'] ?></td>
    <td<?= $this->sortAttr('tpg') ?>><?= $stats['tpg'] ?></td>
    <td<?= $this->sortAttr('bpg') ?>><?= $stats['bpg'] ?></td>
    <td<?= $this->sortAttr('fpg') ?>><?= $stats['fpg'] ?></td>
    <td<?= $this->sortAttr('ppg') ?>><?= $stats['ppg'] ?></td>
    <td<?= $this->sortAttr('qa') ?>><?= $stats['qa'] ?></td>
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
