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
<style>
.leaderboards-form {
    background: var(--gray-50, #f9fafb);
    border: 1px solid var(--gray-200, #e5e7eb);
    border-radius: var(--radius-lg, 0.5rem);
    padding: 1rem;
    margin-bottom: 1.5rem;
}
.leaderboards-form__row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: center;
    gap: 1rem;
}
.leaderboards-form__group {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.leaderboards-form__label {
    font-family: var(--font-display, 'Poppins', sans-serif);
    font-size: 1rem;
    font-weight: 600;
    color: var(--gray-600, #4b5563);
    text-transform: uppercase;
    letter-spacing: 0.03em;
}
.leaderboards-form select,
.leaderboards-form input[type="number"] {
    font-family: var(--font-sans, 'Inter', sans-serif);
    font-size: 1.125rem;
    padding: 0.375rem 0.625rem;
    border: 1px solid var(--gray-300, #d1d5db);
    border-radius: var(--radius-md, 0.375rem);
    background: white;
    color: var(--gray-800, #1f2937);
    transition: border-color 150ms ease, box-shadow 150ms ease;
}
.leaderboards-form select:focus,
.leaderboards-form input:focus {
    outline: none;
    border-color: var(--accent-500, #f97316);
    box-shadow: 0 0 0 3px rgba(249, 115, 22, 0.1);
}
.leaderboards-form input[type="number"] {
    width: 5rem;
}
.leaderboards-form__submit {
    font-family: var(--font-display, 'Poppins', sans-serif);
    font-size: 1rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    padding: 0.5rem 1rem;
    background: linear-gradient(135deg, var(--navy-800, #1e293b), var(--navy-900, #0f172a));
    color: white;
    border: none;
    border-radius: var(--radius-md, 0.375rem);
    cursor: pointer;
    transition: transform 150ms ease, box-shadow 150ms ease;
}
.leaderboards-form__submit:hover {
    transform: translateY(-1px);
    box-shadow: var(--shadow-md, 0 4px 6px -1px rgb(0 0 0 / 0.1));
}
</style>
<form name="Leaderboards" method="post" action="modules.php?name=Leaderboards" class="leaderboards-form">
    <div class="leaderboards-form__row">
        <div class="leaderboards-form__group">
            <label class="leaderboards-form__label">Type:</label>
            <select name="boards_type">
                <?php foreach ($boardTypes as $key => $value): ?>
                    <?php $selected = ($boardsType == $value) ? ' selected' : ''; ?>
                    <option value="<?= htmlspecialchars($value) ?>"<?= $selected ?>><?= htmlspecialchars($value) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="leaderboards-form__group">
            <label class="leaderboards-form__label">Category:</label>
            <select name="sort_cat">
                <?php foreach ($sortCategories as $key => $value): ?>
                    <?php $selected = ($sortCat == $value) ? ' selected' : ''; ?>
                    <option value="<?= htmlspecialchars($value) ?>"<?= $selected ?>><?= htmlspecialchars($value) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="leaderboards-form__group">
            <label class="leaderboards-form__label">Include Retirees:</label>
            <select name="active">
                <option value="0"<?= ($active == '0') ? ' selected' : '' ?>>Yes</option>
                <option value="1"<?= ($active == '1') ? ' selected' : '' ?>>No</option>
            </select>
        </div>
        <div class="leaderboards-form__group">
            <label class="leaderboards-form__label">Limit:</label>
            <input type="number" name="display" value="<?= htmlspecialchars((string)$display) ?>">
            <span class="leaderboards-form__label">Records</span>
        </div>
        <input type="hidden" name="submitted" value="1">
        <button type="submit" class="leaderboards-form__submit">Display Leaderboards</button>
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
<style>
.leaderboards-title {
    font-family: var(--font-display, 'Poppins', sans-serif);
    font-size: 1.5rem;
    font-weight: 600;
    color: var(--navy-900, #0f172a);
    text-align: center;
    margin: 0 0 1rem 0;
}
.leaderboards-table {
    font-family: var(--font-sans, 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif);
    border-collapse: separate;
    border-spacing: 0;
    border: none;
    border-radius: var(--radius-lg, 0.5rem);
    overflow: hidden;
    box-shadow: var(--shadow-md, 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1));
    width: 100%;
    margin: 0 auto;
}
.leaderboards-table thead {
    background: linear-gradient(135deg, var(--navy-800, #1e293b), var(--navy-900, #0f172a));
}
.leaderboards-table th {
    color: white;
    font-family: var(--font-display, 'Poppins', -apple-system, BlinkMacSystemFont, sans-serif);
    font-weight: 600;
    font-size: 1.25rem;
    text-transform: uppercase;
    letter-spacing: 0.02em;
    padding: 0.625rem 0.375rem;
    text-align: center;
    white-space: nowrap;
}
.leaderboards-table td {
    color: var(--gray-800, #1f2937);
    font-size: 1rem;
    padding: 0.5rem 0.375rem;
    text-align: center;
}
.leaderboards-table tbody tr {
    transition: background-color 150ms ease;
}
.leaderboards-table tbody tr:nth-child(odd) {
    background-color: white;
}
.leaderboards-table tbody tr:nth-child(even) {
    background-color: var(--gray-50, #f9fafb);
}
.leaderboards-table tbody tr:hover {
    background-color: var(--gray-100, #f3f4f6);
}
.leaderboards-table a {
    color: var(--gray-800, #1f2937);
    text-decoration: none;
    font-weight: 500;
    transition: color 150ms ease;
}
.leaderboards-table a:hover {
    color: var(--accent-500, #f97316);
}
.leaderboards-table .rank-cell {
    font-weight: 600;
    color: var(--navy-700, #334155);
}

/* Mobile sticky columns support */
@media (max-width: 768px) {
    .leaderboards-table.responsive-table th.sticky-col-1,
    .leaderboards-table.responsive-table td.sticky-col-1 {
        position: sticky;
        left: 0;
        z-index: 1;
        min-width: 36px;
    }
    .leaderboards-table.responsive-table th.sticky-col-2,
    .leaderboards-table.responsive-table td.sticky-col-2 {
        position: sticky;
        left: 36px;
        z-index: 1;
        min-width: 100px;
    }
    .leaderboards-table.responsive-table thead th.sticky-col-1,
    .leaderboards-table.responsive-table thead th.sticky-col-2 {
        background: linear-gradient(135deg, var(--navy-800, #1e293b), var(--navy-900, #0f172a));
        z-index: 3;
    }
    .leaderboards-table.responsive-table tbody tr:nth-child(odd) td.sticky-col-1,
    .leaderboards-table.responsive-table tbody tr:nth-child(odd) td.sticky-col-2 {
        background-color: white;
    }
    .leaderboards-table.responsive-table tbody tr:nth-child(even) td.sticky-col-1,
    .leaderboards-table.responsive-table tbody tr:nth-child(even) td.sticky-col-2 {
        background-color: var(--gray-50, #f9fafb);
    }
    .leaderboards-table.responsive-table tbody tr:hover td.sticky-col-1,
    .leaderboards-table.responsive-table tbody tr:hover td.sticky-col-2 {
        background-color: var(--gray-100, #f3f4f6);
    }
    .leaderboards-table.responsive-table td.sticky-col-2 {
        box-shadow: 2px 0 4px rgba(0, 0, 0, 0.05);
    }
}
</style>
<h2 class="leaderboards-title">Leaderboards Display</h2>
<div class="table-scroll-container">
<table class="sortable leaderboards-table responsive-table">
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
