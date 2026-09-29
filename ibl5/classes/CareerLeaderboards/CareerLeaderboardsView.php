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
    private bool $showGames = true;

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

    public function setShowGames(bool $showGames): void
    {
        $this->showGames = $showGames;
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
        $phases = $this->service->getPhases();
        $phase = $this->service->resolvePhase($currentFilters['phase'] ?? 'regular');
        $mode = $this->service->resolveMode($phase, $currentFilters['mode'] ?? 'totals');
        $sortKey = $this->service->resolveSortKey($currentFilters['sortby'] ?? 'PPG', $mode);
        $sortOptions = $this->service->getSortOptions($mode);
        $retirees = $currentFilters['retirees'] ?? true;
        $display = (string) ($currentFilters['display'] ?? '');
        $hasAverages = $this->service->phaseHasAverages($phase);
        $showsGames = $this->service->phaseShowsGames($phase);

        ob_start();
        ?>
<form name="CareerLeaderboards" method="get" action="modules.php" class="ibl-filter-form ibl-filter-form--stacked">
    <input type="hidden" name="name" value="Leaderboards">
    <input type="hidden" name="tab" value="career">
    <div class="ibl-filter-form__row">
        <div class="ibl-filter-form__group">
            <label for="cl-phase" class="ibl-filter-form__label">Phase:</label>
            <select id="cl-phase" name="phase">
                <?php foreach ($phases as $key => $label): ?>
                    <option value="<?= HtmlSanitizer::e($key) ?>" data-has-averages="<?= $this->service->phaseHasAverages($key) ? '1' : '0' ?>" data-shows-games="<?= $this->service->phaseShowsGames($key) ? '1' : '0' ?>"<?= ($phase === $key) ? ' selected' : '' ?>><?= HtmlSanitizer::e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="ibl-filter-form__group">
            <span class="ibl-filter-form__label" aria-hidden="true">Stats:</span>
            <fieldset class="ibl-segmented">
                <legend class="ibl-segmented__legend">Stats</legend>
                <label class="ibl-segmented__option">
                    <input type="radio" name="mode" value="totals"<?= ($mode === 'totals') ? ' checked' : '' ?>>
                    <span class="ibl-segmented__text">Totals</span>
                </label>
                <label class="ibl-segmented__option">
                    <input type="radio" name="mode" value="averages"<?= ($mode === 'averages') ? ' checked' : '' ?><?= $hasAverages ? '' : ' disabled' ?>>
                    <span class="ibl-segmented__text">Averages</span>
                </label>
            </fieldset>
        </div>
        <div class="ibl-filter-form__group">
            <label for="cl-sortby" class="ibl-filter-form__label">Sort By:</label>
            <select id="cl-sortby" name="sortby">
                <?php foreach ($sortOptions as $key => $label): ?>
                    <option value="<?= HtmlSanitizer::e($key) ?>"<?= ($sortKey === $key) ? ' selected' : '' ?><?= ($key === 'GAMES' && !$showsGames) ? ' disabled' : '' ?>><?= HtmlSanitizer::e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="ibl-filter-form__group">
            <label for="cl-retirees" class="ibl-filter-form__label">Retired?</label>
            <span class="ibl-switch">
                <input type="checkbox" role="switch" name="retirees" value="1" id="cl-retirees" class="ibl-switch__input"<?= $retirees ? ' checked' : '' ?>>
                <span class="ibl-switch__track" aria-hidden="true"></span>
            </span>
        </div>
        <div class="ibl-filter-form__group">
            <label for="cl-limit" class="ibl-filter-form__label">Results Limit:</label>
            <input id="cl-limit" type="number" name="display" value="<?= HtmlSanitizer::e($display) ?>" min="1" placeholder="50">
        </div>
    </div>
    <input type="hidden" name="submitted" value="1">
    <div class="ibl-filter-form__actions">
        <button type="submit" class="ibl-filter-form__submit">Search</button>
    </div>
</form>
<script src="jslib/career-leaderboards-form.js" defer></script>
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
            <th class="sticky-col-1">#</th>
            <th class="sticky-col-2">Name</th>
            <?php if ($this->showGames): ?>
            <th<?= HtmlSanitizer::trusted($this->sortAttr('games')) ?>>G</th>
            <?php endif; ?>
            <th<?= HtmlSanitizer::trusted($this->sortAttr('minutes')) ?>>MIN</th>
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
    <?php if ($this->showGames): ?>
    <td<?= HtmlSanitizer::trusted($this->sortAttr('games')) ?>><?= HtmlSanitizer::e((string) $stats['games']) ?></td>
    <?php endif; ?>
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
