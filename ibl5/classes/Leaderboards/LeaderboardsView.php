<?php

declare(strict_types=1);

namespace Leaderboards;

use Leaderboards\Contracts\LeaderboardsViewInterface;

/**
 * @see LeaderboardsViewInterface
 */
class LeaderboardsView implements LeaderboardsViewInterface
{
    private LeaderboardsService $service;

    public function __construct(LeaderboardsService $service)
    {
        $this->service = $service;
    }

    /**
     * @see LeaderboardsViewInterface::renderFilterForm()
     */
    public function renderFilterForm(array $currentFilters): string
    {
        $boardTypes = $this->service->getBoardTypes();
        $sortCategories = $this->service->getSortCategories();

        $boardsType = $currentFilters['boards_type'] ?? '';
        $sortCat = $currentFilters['sort_cat'] ?? '';
        $active = $currentFilters['active'] ?? '0';
        $display = $currentFilters['display'] ?? '';

        ob_start();
        ?>
<form name="Leaderboards" method="post" action="modules.php?name=Leaderboards" class="ibl-filter-form">
    <div class="ibl-filter-form__row">
        <div class="ibl-filter-form__group">
            <label class="ibl-filter-form__label">Type:</label>
            <select name="boards_type">
                <?php foreach ($boardTypes as $key => $value): ?>
                    <?php $selected = ($boardsType == $value) ? ' selected' : ''; ?>
                    <option value="<?= htmlspecialchars($value) ?>"<?= $selected ?>><?= htmlspecialchars($value) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="ibl-filter-form__group">
            <label class="ibl-filter-form__label">Category:</label>
            <select name="sort_cat">
                <?php foreach ($sortCategories as $key => $value): ?>
                    <?php $selected = ($sortCat == $value) ? ' selected' : ''; ?>
                    <option value="<?= htmlspecialchars($value) ?>"<?= $selected ?>><?= htmlspecialchars($value) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="ibl-filter-form__group">
            <label class="ibl-filter-form__label">Include Retirees:</label>
            <select name="active">
                <option value="0"<?= ($active == '0') ? ' selected' : '' ?>>Yes</option>
                <option value="1"<?= ($active == '1') ? ' selected' : '' ?>>No</option>
            </select>
        </div>
        <div class="ibl-filter-form__group">
            <label class="ibl-filter-form__label">Limit:</label>
            <input type="number" name="display" value="<?= htmlspecialchars((string)$display) ?>">
            <span class="ibl-filter-form__label">Records</span>
        </div>
        <input type="hidden" name="submitted" value="1">
        <button type="submit" class="ibl-filter-form__submit">Display Leaderboards</button>
    </div>
</form>
        <?php
        return ob_get_clean();
    }

    /**
     * @see LeaderboardsViewInterface::renderTableHeader()
     */
    public function renderTableHeader(): string
    {
        ob_start();
        ?>
<h2 class="ibl-title">Leaderboards Display</h2>
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
        return ob_get_clean();
    }

    /**
     * @see LeaderboardsViewInterface::renderPlayerRow()
     */
    public function renderPlayerRow(array $stats, int $rank): string
    {
        ob_start();
        ?>
<tr>
    <td class="rank-cell sticky-col-1"><?= htmlspecialchars((string)$rank) ?></td>
    <td class="sticky-col-2"><a href="modules.php?name=Player&amp;pa=showpage&amp;pid=<?= htmlspecialchars((string)$stats['pid']) ?>"><?= htmlspecialchars($stats['name']) ?></a></td>
    <td><?= htmlspecialchars((string)$stats['games']) ?></td>
    <td><?= htmlspecialchars((string)$stats['minutes']) ?></td>
    <td><?= htmlspecialchars((string)$stats['fgm']) ?></td>
    <td><?= htmlspecialchars((string)$stats['fga']) ?></td>
    <td><?= htmlspecialchars((string)$stats['fgp']) ?></td>
    <td><?= htmlspecialchars((string)$stats['ftm']) ?></td>
    <td><?= htmlspecialchars((string)$stats['fta']) ?></td>
    <td><?= htmlspecialchars((string)$stats['ftp']) ?></td>
    <td><?= htmlspecialchars((string)$stats['tgm']) ?></td>
    <td><?= htmlspecialchars((string)$stats['tga']) ?></td>
    <td><?= htmlspecialchars((string)$stats['tgp']) ?></td>
    <td><?= htmlspecialchars((string)$stats['orb']) ?></td>
    <td><?= htmlspecialchars((string)$stats['reb']) ?></td>
    <td><?= htmlspecialchars((string)$stats['ast']) ?></td>
    <td><?= htmlspecialchars((string)$stats['stl']) ?></td>
    <td><?= htmlspecialchars((string)$stats['tvr']) ?></td>
    <td><?= htmlspecialchars((string)$stats['blk']) ?></td>
    <td><?= htmlspecialchars((string)$stats['pf']) ?></td>
    <td><?= htmlspecialchars((string)$stats['pts']) ?></td>
</tr>
        <?php
        return ob_get_clean();
    }

    /**
     * @see LeaderboardsViewInterface::renderTableFooter()
     */
    public function renderTableFooter(): string
    {
        return '</tbody></table></div>'; // Close table and scroll container
    }
}
