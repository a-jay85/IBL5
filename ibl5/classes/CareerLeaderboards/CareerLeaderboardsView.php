<?php

declare(strict_types=1);

namespace CareerLeaderboards;

use CareerLeaderboards\Contracts\CareerLeaderboardsViewInterface;
use Player\PlayerImageHelper;

/**
 * @see CareerLeaderboardsViewInterface
 *
 * @phpstan-import-type FormattedPlayerStats from Contracts\CareerLeaderboardsServiceInterface
 * @phpstan-import-type FilterParams from Contracts\CareerLeaderboardsViewInterface
 */
class CareerLeaderboardsView implements CareerLeaderboardsViewInterface
{
    private CareerLeaderboardsService $service;

    public function __construct(CareerLeaderboardsService $service)
    {
        $this->service = $service;
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
                    <option value="<?= htmlspecialchars($value) ?>"<?= $selected ?>><?= htmlspecialchars($value) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="ibl-filter-form__group">
            <label class="ibl-filter-form__label">Category:</label>
            <select name="sort_cat">
                <?php foreach ($sortCategories as $key => $value): ?>
                    <?php $selected = ($sortCat === $value) ? ' selected' : ''; ?>
                    <option value="<?= htmlspecialchars($value) ?>"<?= $selected ?>><?= htmlspecialchars($value) ?></option>
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
            <input type="number" name="display" value="<?= htmlspecialchars($display) ?>">
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
            <th>Games</th>
            <th>Minutes</th>
            <th>FGM</th>
            <th>FGA</th>
            <th>FG%</th>
            <th>FTM</th>
            <th>FTA</th>
            <th>FT%</th>
            <th>3GM</th>
            <th>3GA</th>
            <th>3P%</th>
            <th>ORB</th>
            <th>REB</th>
            <th>AST</th>
            <th>STL</th>
            <th>TVR</th>
            <th>BLK</th>
            <th>FOULS</th>
            <th>PTS</th>
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
        $resolved = PlayerImageHelper::resolvePlayerDisplay($pid, $name);
        /** @var string $resolvedName */
        $resolvedName = $resolved['name'];
        /** @var string $resolvedThumbnail */
        $resolvedThumbnail = $resolved['thumbnail'];

        ob_start();
        ?>
<tr>
    <td class="rank-cell sticky-col-1"><?= $rank ?></td>
    <td class="sticky-col-2 ibl-player-cell"><a href="modules.php?name=Player&amp;pa=showpage&amp;pid=<?= $pid ?>"><?= $resolvedThumbnail ?><?= htmlspecialchars($resolvedName) ?></a></td>
    <td><?= htmlspecialchars((string) $stats['games']) ?></td>
    <td><?= htmlspecialchars($stats['minutes']) ?></td>
    <td><?= htmlspecialchars($stats['fgm']) ?></td>
    <td><?= htmlspecialchars($stats['fga']) ?></td>
    <td><?= htmlspecialchars($stats['fgp']) ?></td>
    <td><?= htmlspecialchars($stats['ftm']) ?></td>
    <td><?= htmlspecialchars($stats['fta']) ?></td>
    <td><?= htmlspecialchars($stats['ftp']) ?></td>
    <td><?= htmlspecialchars($stats['tgm']) ?></td>
    <td><?= htmlspecialchars($stats['tga']) ?></td>
    <td><?= htmlspecialchars($stats['tgp']) ?></td>
    <td><?= htmlspecialchars($stats['orb']) ?></td>
    <td><?= htmlspecialchars($stats['reb']) ?></td>
    <td><?= htmlspecialchars($stats['ast']) ?></td>
    <td><?= htmlspecialchars($stats['stl']) ?></td>
    <td><?= htmlspecialchars($stats['tvr']) ?></td>
    <td><?= htmlspecialchars($stats['blk']) ?></td>
    <td><?= htmlspecialchars($stats['pf']) ?></td>
    <td><?= htmlspecialchars($stats['pts']) ?></td>
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
