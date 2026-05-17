<?php

declare(strict_types=1);

namespace SeasonLeaderboards;

use Player\PlayerImageHelper;
use SeasonLeaderboards\Contracts\SeasonLeaderboardsRepositoryInterface;
use UI\TeamCellHelper;
use SeasonLeaderboards\Contracts\SeasonLeaderboardsServiceInterface;
use SeasonLeaderboards\Contracts\SeasonLeaderboardsViewInterface;
use Security\HtmlSanitizer;

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
        'PPG' => 'ppg',    'REB' => 'rpg',     'OREB' => 'orbpg',  'DREB' => 'drebpg',
        'AST' => 'apg',    'STL' => 'spg',     'BLK' => 'bpg',     'TO' => 'tpg',
        'FOUL' => 'fpg',   'QA' => 'qa',       'FGM' => 'fgmpg',   'FGA' => 'fgapg',
        'FGP' => 'fgp',    'FTM' => 'ftmpg',   'FTA' => 'ftapg',   'FTP' => 'ftp',
        'TGM' => 'tgmpg',  'TGA' => 'tgapg',   'TGP' => 'tgp',     'GAMES' => 'games',
        'MIN' => 'mpg',
    ];

    private const SORTED_ATTR = ' class="sorted-col"';

    public function __construct(SeasonLeaderboardsService $service)
    {
        $this->service = $service;
    }

    public function setSortBy(string $sortBy): void
    {
        $this->activeSortColumn = self::SORT_TO_COLUMN[$sortBy] ?? 'ppg';
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
        $selectedSort = (string) ($currentFilters['sortby'] ?? 'PPG');
        $limitValue = (string) ($currentFilters['limit'] ?? '');
        $sortOptions = $this->service->getSortOptions();

        ob_start();
        ?>
<form name="Leaderboards" method="post" action="modules.php?name=SeasonLeaderboards" class="ibl-filter-form">
    <div class="ibl-filter-form__row">
        <div class="ibl-filter-form__group">
            <label for="sl-team" class="ibl-filter-form__label">Team:</label>
            <select id="sl-team" name="team">
                <option value="0">All</option>
                <?php foreach ($teams as $team): ?>
                <option value="<?= (int)$team['teamid'] ?>"<?= ($selectedTeam === (int)$team['teamid']) ? ' selected' : '' ?>><?= HtmlSanitizer::e($team['Team']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="ibl-filter-form__group">
            <label for="sl-year" class="ibl-filter-form__label">Year:</label>
            <select id="sl-year" name="year">
                <option value="">All</option>
                <?php foreach ($years as $year): ?>
                <option value="<?= (int)$year ?>"<?= ($selectedYear === (string)$year) ? ' selected' : '' ?>><?= (int)$year ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="ibl-filter-form__group">
            <label for="sl-sortby" class="ibl-filter-form__label">Sort By:</label>
            <select id="sl-sortby" name="sortby">
                <?php foreach ($sortOptions as $key => $label): ?>
                <option value="<?= HtmlSanitizer::e($key) ?>"<?= ($key === $selectedSort) ? ' selected' : '' ?>><?= HtmlSanitizer::e($label) ?></option>
                <?php endforeach; ?>
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
            <th<?= $this->activeSortColumn === 'games' ? self::SORTED_ATTR : '' ?>>G</th>
            <th<?= $this->activeSortColumn === 'mpg' ? self::SORTED_ATTR : '' ?>>Min</th>
            <th<?= $this->activeSortColumn === 'fgmpg' ? self::SORTED_ATTR : '' ?>>fgm</th>
            <th<?= $this->activeSortColumn === 'fgapg' ? self::SORTED_ATTR : '' ?>>fga</th>
            <th<?= $this->activeSortColumn === 'fgp' ? self::SORTED_ATTR : '' ?>>fg%</th>
            <th<?= $this->activeSortColumn === 'ftmpg' ? self::SORTED_ATTR : '' ?>>ftm</th>
            <th<?= $this->activeSortColumn === 'ftapg' ? self::SORTED_ATTR : '' ?>>fta</th>
            <th<?= $this->activeSortColumn === 'ftp' ? self::SORTED_ATTR : '' ?>>ft%</th>
            <th<?= $this->activeSortColumn === 'tgmpg' ? self::SORTED_ATTR : '' ?>>tgm</th>
            <th<?= $this->activeSortColumn === 'tgapg' ? self::SORTED_ATTR : '' ?>>tga</th>
            <th<?= $this->activeSortColumn === 'tgp' ? self::SORTED_ATTR : '' ?>>tg%</th>
            <th<?= $this->activeSortColumn === 'orbpg' ? self::SORTED_ATTR : '' ?>>orb</th>
            <th<?= $this->activeSortColumn === 'drebpg' ? self::SORTED_ATTR : '' ?>>dreb</th>
            <th<?= $this->activeSortColumn === 'rpg' ? self::SORTED_ATTR : '' ?>>reb</th>
            <th<?= $this->activeSortColumn === 'apg' ? self::SORTED_ATTR : '' ?>>ast</th>
            <th<?= $this->activeSortColumn === 'spg' ? self::SORTED_ATTR : '' ?>>stl</th>
            <th<?= $this->activeSortColumn === 'tpg' ? self::SORTED_ATTR : '' ?>>to</th>
            <th<?= $this->activeSortColumn === 'bpg' ? self::SORTED_ATTR : '' ?>>blk</th>
            <th<?= $this->activeSortColumn === 'fpg' ? self::SORTED_ATTR : '' ?>>pf</th>
            <th<?= $this->activeSortColumn === 'ppg' ? self::SORTED_ATTR : '' ?>>ppg</th>
            <th<?= $this->activeSortColumn === 'qa' ? self::SORTED_ATTR : '' ?>>qa</th>
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
        ob_start();
        ?>
<tr data-team-id="<?= (int)$stats['teamid'] ?>">
    <td class="rank-cell sticky-col-1"><?= HtmlSanitizer::e($rank) ?>.</td>
    <td><?= (int)$stats['year'] ?></td>
    <?= PlayerImageHelper::renderFlexiblePlayerCell((int)$stats['pid'], $stats['name'], 'sticky-col-2') ?>
    <?= TeamCellHelper::renderTeamCellOrFreeAgent((int)$stats['teamid'], $stats['teamname'], $stats['color1'], $stats['color2']) ?>
    <td<?= $this->activeSortColumn === 'games' ? self::SORTED_ATTR : '' ?>><?= (int)$stats['games'] ?></td>
    <td<?= $this->activeSortColumn === 'mpg' ? self::SORTED_ATTR : '' ?>><?= HtmlSanitizer::e($stats['mpg']) ?></td>
    <td<?= $this->activeSortColumn === 'fgmpg' ? self::SORTED_ATTR : '' ?>><?= HtmlSanitizer::e($stats['fgmpg']) ?></td>
    <td<?= $this->activeSortColumn === 'fgapg' ? self::SORTED_ATTR : '' ?>><?= HtmlSanitizer::e($stats['fgapg']) ?></td>
    <td<?= $this->activeSortColumn === 'fgp' ? self::SORTED_ATTR : '' ?>><?= HtmlSanitizer::e($stats['fgp']) ?></td>
    <td<?= $this->activeSortColumn === 'ftmpg' ? self::SORTED_ATTR : '' ?>><?= HtmlSanitizer::e($stats['ftmpg']) ?></td>
    <td<?= $this->activeSortColumn === 'ftapg' ? self::SORTED_ATTR : '' ?>><?= HtmlSanitizer::e($stats['ftapg']) ?></td>
    <td<?= $this->activeSortColumn === 'ftp' ? self::SORTED_ATTR : '' ?>><?= HtmlSanitizer::e($stats['ftp']) ?></td>
    <td<?= $this->activeSortColumn === 'tgmpg' ? self::SORTED_ATTR : '' ?>><?= HtmlSanitizer::e($stats['tgmpg']) ?></td>
    <td<?= $this->activeSortColumn === 'tgapg' ? self::SORTED_ATTR : '' ?>><?= HtmlSanitizer::e($stats['tgapg']) ?></td>
    <td<?= $this->activeSortColumn === 'tgp' ? self::SORTED_ATTR : '' ?>><?= HtmlSanitizer::e($stats['tgp']) ?></td>
    <td<?= $this->activeSortColumn === 'orbpg' ? self::SORTED_ATTR : '' ?>><?= HtmlSanitizer::e($stats['orbpg']) ?></td>
    <td<?= $this->activeSortColumn === 'drebpg' ? self::SORTED_ATTR : '' ?>><?= HtmlSanitizer::e($stats['drebpg']) ?></td>
    <td<?= $this->activeSortColumn === 'rpg' ? self::SORTED_ATTR : '' ?>><?= HtmlSanitizer::e($stats['rpg']) ?></td>
    <td<?= $this->activeSortColumn === 'apg' ? self::SORTED_ATTR : '' ?>><?= HtmlSanitizer::e($stats['apg']) ?></td>
    <td<?= $this->activeSortColumn === 'spg' ? self::SORTED_ATTR : '' ?>><?= HtmlSanitizer::e($stats['spg']) ?></td>
    <td<?= $this->activeSortColumn === 'tpg' ? self::SORTED_ATTR : '' ?>><?= HtmlSanitizer::e($stats['tpg']) ?></td>
    <td<?= $this->activeSortColumn === 'bpg' ? self::SORTED_ATTR : '' ?>><?= HtmlSanitizer::e($stats['bpg']) ?></td>
    <td<?= $this->activeSortColumn === 'fpg' ? self::SORTED_ATTR : '' ?>><?= HtmlSanitizer::e($stats['fpg']) ?></td>
    <td<?= $this->activeSortColumn === 'ppg' ? self::SORTED_ATTR : '' ?>><?= HtmlSanitizer::e($stats['ppg']) ?></td>
    <td<?= $this->activeSortColumn === 'qa' ? self::SORTED_ATTR : '' ?>><?= HtmlSanitizer::e($stats['qa']) ?></td>
</tr>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * @see SeasonLeaderboardsViewInterface::renderTableFooter()
     */
    public function renderTableFooter(): string
    {
        return '</tbody></table></div>';
    }
}
