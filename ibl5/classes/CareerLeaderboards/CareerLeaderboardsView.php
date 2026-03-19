<?php

declare(strict_types=1);

namespace CareerLeaderboards;

use CareerLeaderboards\Contracts\CareerLeaderboardsViewInterface;
use Player\PlayerImageHelper;
use Utilities\HtmlSanitizer;

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
        'orb' => 'orb',     'reb' => 'reb',       'ast' => 'ast',
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
            <label class="ibl-filter-form__label">Type:</label>
            <select name="boards_type">
                <?php foreach ($boardTypes as $key => $value): ?>
                    <?php $selected = ($boardsType === $value) ? ' selected' : ''; ?>
                    <option value="<?= HtmlSanitizer::e($value) ?>"<?= $selected ?>><?= HtmlSanitizer::e($value) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="ibl-filter-form__group">
            <label class="ibl-filter-form__label">Category:</label>
            <select name="sort_cat">
                <?php foreach ($sortCategories as $key => $value): ?>
                    <?php $selected = ($sortCat === $value) ? ' selected' : ''; ?>
                    <option value="<?= HtmlSanitizer::e($value) ?>"<?= $selected ?>><?= HtmlSanitizer::e($value) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="ibl-filter-form__group">
            <label class="ibl-filter-form__label">Include Retirees:</label>
            <select name="active">
                <option value="0"<?= ($active === '0') ? ' selected' : '' ?>>Yes</option>
                <option value="1"<?= ($active === '1') ? ' selected' : '' ?>>No</option>
            </select>
        </div>
        <div class="ibl-filter-form__group">
            <label class="ibl-filter-form__label">Limit:</label>
            <input type="number" name="display" value="<?= HtmlSanitizer::e($display) ?>">
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
<div class="table-scroll-container">
<table class="sortable ibl-data-table responsive-table">
    <thead>
        <tr>
            <th class="sticky-col-1">Rank</th>
            <th class="sticky-col-2">Name</th>
            <th<?= $this->sortAttr('games') ?>>Games</th>
            <th<?= $this->sortAttr('minutes') ?>>Minutes</th>
            <th<?= $this->sortAttr('fgm') ?>>FGM</th>
            <th<?= $this->sortAttr('fga') ?>>FGA</th>
            <th<?= $this->sortAttr('fgp') ?>>FG%</th>
            <th<?= $this->sortAttr('ftm') ?>>FTM</th>
            <th<?= $this->sortAttr('fta') ?>>FTA</th>
            <th<?= $this->sortAttr('ftp') ?>>FT%</th>
            <th<?= $this->sortAttr('tgm') ?>>3GM</th>
            <th<?= $this->sortAttr('tga') ?>>3GA</th>
            <th<?= $this->sortAttr('tgp') ?>>3P%</th>
            <th<?= $this->sortAttr('orb') ?>>ORB</th>
            <th<?= $this->sortAttr('reb') ?>>REB</th>
            <th<?= $this->sortAttr('ast') ?>>AST</th>
            <th<?= $this->sortAttr('stl') ?>>STL</th>
            <th<?= $this->sortAttr('tvr') ?>>TVR</th>
            <th<?= $this->sortAttr('blk') ?>>BLK</th>
            <th<?= $this->sortAttr('pf') ?>>FOULS</th>
            <th<?= $this->sortAttr('pts') ?>>PTS</th>
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
        $pid = $stats['pid'];
        $name = $stats['name'];
        $playerCell = PlayerImageHelper::renderFlexiblePlayerCell($pid, $name, 'sticky-col-2');

        ob_start();
        ?>
<tr>
    <td class="rank-cell sticky-col-1"><?= $rank ?></td>
    <?= $playerCell ?>
    <td<?= $this->sortAttr('games') ?>><?= HtmlSanitizer::e((string) $stats['games']) ?></td>
    <td<?= $this->sortAttr('minutes') ?>><?= HtmlSanitizer::e($stats['minutes']) ?></td>
    <td<?= $this->sortAttr('fgm') ?>><?= HtmlSanitizer::e($stats['fgm']) ?></td>
    <td<?= $this->sortAttr('fga') ?>><?= HtmlSanitizer::e($stats['fga']) ?></td>
    <td<?= $this->sortAttr('fgp') ?>><?= HtmlSanitizer::e($stats['fgp']) ?></td>
    <td<?= $this->sortAttr('ftm') ?>><?= HtmlSanitizer::e($stats['ftm']) ?></td>
    <td<?= $this->sortAttr('fta') ?>><?= HtmlSanitizer::e($stats['fta']) ?></td>
    <td<?= $this->sortAttr('ftp') ?>><?= HtmlSanitizer::e($stats['ftp']) ?></td>
    <td<?= $this->sortAttr('tgm') ?>><?= HtmlSanitizer::e($stats['tgm']) ?></td>
    <td<?= $this->sortAttr('tga') ?>><?= HtmlSanitizer::e($stats['tga']) ?></td>
    <td<?= $this->sortAttr('tgp') ?>><?= HtmlSanitizer::e($stats['tgp']) ?></td>
    <td<?= $this->sortAttr('orb') ?>><?= HtmlSanitizer::e($stats['orb']) ?></td>
    <td<?= $this->sortAttr('reb') ?>><?= HtmlSanitizer::e($stats['reb']) ?></td>
    <td<?= $this->sortAttr('ast') ?>><?= HtmlSanitizer::e($stats['ast']) ?></td>
    <td<?= $this->sortAttr('stl') ?>><?= HtmlSanitizer::e($stats['stl']) ?></td>
    <td<?= $this->sortAttr('tvr') ?>><?= HtmlSanitizer::e($stats['tvr']) ?></td>
    <td<?= $this->sortAttr('blk') ?>><?= HtmlSanitizer::e($stats['blk']) ?></td>
    <td<?= $this->sortAttr('pf') ?>><?= HtmlSanitizer::e($stats['pf']) ?></td>
    <td<?= $this->sortAttr('pts') ?>><?= HtmlSanitizer::e($stats['pts']) ?></td>
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
