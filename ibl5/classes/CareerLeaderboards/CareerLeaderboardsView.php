<?php

declare(strict_types=1);

namespace CareerLeaderboards;

use CareerLeaderboards\Contracts\CareerLeaderboardsViewInterface;
use Player\PlayerImageHelper;
use Security\HtmlSanitizer;

/**
 * @see CareerLeaderboardsViewInterface
 *
 * @phpstan-import-type FormattedPlayerStats from Contracts\CareerLeaderboardsServiceInterface
 * @phpstan-import-type FilterParams from Contracts\CareerLeaderboardsViewInterface
 */
class CareerLeaderboardsView implements CareerLeaderboardsViewInterface
{
    private CareerLeaderboardsService $service;
    private string $activeSortColumn = '';

    private const SORT_TO_COLUMN = [
        'pts' => 'pts',     'games' => 'games',   'minutes' => 'minutes',
        'fgm' => 'fgm',     'fga' => 'fga',       'fgpct' => 'fgp',
        'ftm' => 'ftm',     'fta' => 'fta',       'ftpct' => 'ftp',
        'tgm' => 'tgm',     'tga' => 'tga',       'tpct' => 'tgp',
        'orb' => 'orb',     'drb' => 'drb',       'reb' => 'reb',       'ast' => 'ast',
        'stl' => 'stl',     'tvr' => 'tvr',       'blk' => 'blk',
        'pf' => 'pf',
    ];

    public function __construct(CareerLeaderboardsService $service)
    {
        $this->service = $service;
    }

    public function setSortColumn(string $sortColumn): void
    {
        $this->activeSortColumn = self::SORT_TO_COLUMN[$sortColumn] ?? '';
    }

    private function sortAttr(string $statKey): string
    {
        return $this->activeSortColumn === $statKey ? ' class="sorted-col"' : '';
    }

    /**
     * @see CareerLeaderboardsViewInterface::renderFilterForm()
     *
     * @param FilterParams $currentFilters
     */
    public function renderFilterForm(array $currentFilters): string
    {
        $boardTypes = $this->service->getBoardTypes();
        $sortCategories = $this->service->getSortCategories();

        $boardsType = $currentFilters['boards_type'] ?? '';
        $sortCat = $currentFilters['sort_cat'] ?? '';
        $active = $currentFilters['active'] ?? '0';
        $display = (string) ($currentFilters['display'] ?? '');

        ob_start();
        ?>
<form name="CareerLeaderboards" method="post" action="modules.php?name=CareerLeaderboards" class="ibl-filter-form">
    <div class="ibl-filter-form__row">
        <div class="ibl-filter-form__group">
            <label for="cl-type" class="ibl-filter-form__label">Type:</label>
            <select id="cl-type" name="boards_type">
                <?php foreach ($boardTypes as $key => $value): ?>
                    <option value="<?= HtmlSanitizer::e($value) ?>"<?= ($boardsType === $value) ? ' selected' : '' ?>><?= HtmlSanitizer::e($value) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="ibl-filter-form__group">
            <label for="cl-category" class="ibl-filter-form__label">Category:</label>
            <select id="cl-category" name="sort_cat">
                <?php foreach ($sortCategories as $key => $value): ?>
                    <option value="<?= HtmlSanitizer::e($value) ?>"<?= ($sortCat === $value) ? ' selected' : '' ?>><?= HtmlSanitizer::e($value) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="ibl-filter-form__group">
            <label for="cl-retirees" class="ibl-filter-form__label">Include Retirees:</label>
            <select id="cl-retirees" name="active">
                <option value="0"<?= ($active === '0') ? ' selected' : '' ?>>Yes</option>
                <option value="1"<?= ($active === '1') ? ' selected' : '' ?>>No</option>
            </select>
        </div>
        <div class="ibl-filter-form__group">
            <label for="cl-limit" class="ibl-filter-form__label">Limit:</label>
            <input id="cl-limit" type="number" name="display" value="<?= HtmlSanitizer::e($display) ?>">
            <span class="ibl-filter-form__label">Records</span>
        </div>
        <input type="hidden" name="submitted" value="1">
        <button type="submit" class="ibl-filter-form__submit">Display Career Leaderboards</button>
    </div>
</form>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * @see CareerLeaderboardsViewInterface::renderTableHeader()
     */
    public function renderTableHeader(): string
    {
        ob_start();
        ?>
<div class="table-scroll-container" tabindex="0" role="region" aria-label="Career leaderboards">
<table class="sortable ibl-data-table responsive-table">
    <thead>
        <tr>
            <th class="sticky-col-1">Rank</th>
            <th class="sticky-col-2">Name</th>
            <th<?= HtmlSanitizer::trusted($this->sortAttr('games')) ?>>Games</th>
            <th<?= HtmlSanitizer::trusted($this->sortAttr('minutes')) ?>>Minutes</th>
            <th<?= HtmlSanitizer::trusted($this->sortAttr('fgm')) ?>>FGM</th>
            <th<?= HtmlSanitizer::trusted($this->sortAttr('fga')) ?>>FGA</th>
            <th<?= HtmlSanitizer::trusted($this->sortAttr('fgp')) ?>>FG%</th>
            <th<?= HtmlSanitizer::trusted($this->sortAttr('ftm')) ?>>FTM</th>
            <th<?= HtmlSanitizer::trusted($this->sortAttr('fta')) ?>>FTA</th>
            <th<?= HtmlSanitizer::trusted($this->sortAttr('ftp')) ?>>FT%</th>
            <th<?= HtmlSanitizer::trusted($this->sortAttr('tgm')) ?>>3GM</th>
            <th<?= HtmlSanitizer::trusted($this->sortAttr('tga')) ?>>3GA</th>
            <th<?= HtmlSanitizer::trusted($this->sortAttr('tgp')) ?>>3P%</th>
            <th<?= HtmlSanitizer::trusted($this->sortAttr('orb')) ?>>ORB</th>
            <th<?= HtmlSanitizer::trusted($this->sortAttr('drb')) ?>>DRB</th>
            <th<?= HtmlSanitizer::trusted($this->sortAttr('reb')) ?>>REB</th>
            <th<?= HtmlSanitizer::trusted($this->sortAttr('ast')) ?>>AST</th>
            <th<?= HtmlSanitizer::trusted($this->sortAttr('stl')) ?>>STL</th>
            <th<?= HtmlSanitizer::trusted($this->sortAttr('tvr')) ?>>TVR</th>
            <th<?= HtmlSanitizer::trusted($this->sortAttr('blk')) ?>>BLK</th>
            <th<?= HtmlSanitizer::trusted($this->sortAttr('pf')) ?>>FOULS</th>
            <th<?= HtmlSanitizer::trusted($this->sortAttr('pts')) ?>>PTS</th>
        </tr>
    </thead>
    <tbody>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * @see CareerLeaderboardsViewInterface::renderPlayerRow()
     *
     * @param FormattedPlayerStats $stats
     */
    public function renderPlayerRow(array $stats, int $rank): string
    {
        ob_start();
        ?>
<tr>
    <td class="rank-cell sticky-col-1"><?= HtmlSanitizer::e($rank) ?></td>
    <?= PlayerImageHelper::renderFlexiblePlayerCell($stats['pid'], $stats['name'], 'sticky-col-2') ?>
    <td<?= HtmlSanitizer::trusted($this->sortAttr('games')) ?>><?= HtmlSanitizer::e((string) $stats['games']) ?></td>
    <td<?= HtmlSanitizer::trusted($this->sortAttr('minutes')) ?>><?= HtmlSanitizer::e($stats['minutes']) ?></td>
    <td<?= HtmlSanitizer::trusted($this->sortAttr('fgm')) ?>><?= HtmlSanitizer::e($stats['fgm']) ?></td>
    <td<?= HtmlSanitizer::trusted($this->sortAttr('fga')) ?>><?= HtmlSanitizer::e($stats['fga']) ?></td>
    <td<?= HtmlSanitizer::trusted($this->sortAttr('fgp')) ?>><?= HtmlSanitizer::e($stats['fgp']) ?></td>
    <td<?= HtmlSanitizer::trusted($this->sortAttr('ftm')) ?>><?= HtmlSanitizer::e($stats['ftm']) ?></td>
    <td<?= HtmlSanitizer::trusted($this->sortAttr('fta')) ?>><?= HtmlSanitizer::e($stats['fta']) ?></td>
    <td<?= HtmlSanitizer::trusted($this->sortAttr('ftp')) ?>><?= HtmlSanitizer::e($stats['ftp']) ?></td>
    <td<?= HtmlSanitizer::trusted($this->sortAttr('tgm')) ?>><?= HtmlSanitizer::e($stats['tgm']) ?></td>
    <td<?= HtmlSanitizer::trusted($this->sortAttr('tga')) ?>><?= HtmlSanitizer::e($stats['tga']) ?></td>
    <td<?= HtmlSanitizer::trusted($this->sortAttr('tgp')) ?>><?= HtmlSanitizer::e($stats['tgp']) ?></td>
    <td<?= HtmlSanitizer::trusted($this->sortAttr('orb')) ?>><?= HtmlSanitizer::e($stats['orb']) ?></td>
    <td<?= HtmlSanitizer::trusted($this->sortAttr('drb')) ?>><?= HtmlSanitizer::e($stats['drb']) ?></td>
    <td<?= HtmlSanitizer::trusted($this->sortAttr('reb')) ?>><?= HtmlSanitizer::e($stats['reb']) ?></td>
    <td<?= HtmlSanitizer::trusted($this->sortAttr('ast')) ?>><?= HtmlSanitizer::e($stats['ast']) ?></td>
    <td<?= HtmlSanitizer::trusted($this->sortAttr('stl')) ?>><?= HtmlSanitizer::e($stats['stl']) ?></td>
    <td<?= HtmlSanitizer::trusted($this->sortAttr('tvr')) ?>><?= HtmlSanitizer::e($stats['tvr']) ?></td>
    <td<?= HtmlSanitizer::trusted($this->sortAttr('blk')) ?>><?= HtmlSanitizer::e($stats['blk']) ?></td>
    <td<?= HtmlSanitizer::trusted($this->sortAttr('pf')) ?>><?= HtmlSanitizer::e($stats['pf']) ?></td>
    <td<?= HtmlSanitizer::trusted($this->sortAttr('pts')) ?>><?= HtmlSanitizer::e($stats['pts']) ?></td>
</tr>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * @see CareerLeaderboardsViewInterface::renderTableFooter()
     */
    public function renderTableFooter(): string
    {
        return '</tbody></table></div>'; // Close table and scroll container
    }
}
