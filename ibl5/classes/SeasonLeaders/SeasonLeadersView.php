<?php

declare(strict_types=1);

namespace SeasonLeaders;

use SeasonLeaders\Contracts\SeasonLeadersViewInterface;

/**
 * @see SeasonLeadersViewInterface
 */
class SeasonLeadersView implements SeasonLeadersViewInterface
{
    private $service;

    public function __construct(SeasonLeadersService $service)
    {
        $this->service = $service;
    }

    /**
     * @see SeasonLeadersViewInterface::renderFilterForm()
     */
    public function renderFilterForm(array $teams, array $years, array $currentFilters): string
    {
        ob_start();
        ?>
<style>
.season-leaders-form {
    background: var(--gray-50, #f9fafb);
    border: 1px solid var(--gray-200, #e5e7eb);
    border-radius: var(--radius-lg, 0.5rem);
    padding: 1rem;
    margin-bottom: 1.5rem;
}
.season-leaders-form__row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: center;
    gap: 1rem;
}
.season-leaders-form__group {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.season-leaders-form__label {
    font-family: var(--font-display, 'Poppins', sans-serif);
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--gray-600, #4b5563);
    text-transform: uppercase;
    letter-spacing: 0.03em;
}
.season-leaders-form select {
    font-family: var(--font-sans, 'Inter', sans-serif);
    font-size: 0.8125rem;
    padding: 0.375rem 0.625rem;
    border: 1px solid var(--gray-300, #d1d5db);
    border-radius: var(--radius-md, 0.375rem);
    background: white;
    color: var(--gray-800, #1f2937);
    transition: border-color 150ms ease, box-shadow 150ms ease;
}
.season-leaders-form select:focus {
    outline: none;
    border-color: var(--accent-500, #f97316);
    box-shadow: 0 0 0 3px rgba(249, 115, 22, 0.1);
}
.season-leaders-form__submit {
    font-family: var(--font-display, 'Poppins', sans-serif);
    font-size: 0.75rem;
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
.season-leaders-form__submit:hover {
    transform: translateY(-1px);
    box-shadow: var(--shadow-md, 0 4px 6px -1px rgb(0 0 0 / 0.1));
}
</style>
<form name="Leaderboards" method="post" action="modules.php?name=Season_Leaders" class="season-leaders-form">
    <div class="season-leaders-form__row">
        <div class="season-leaders-form__group">
            <label class="season-leaders-form__label">Team:</label>
            <select name="team">
                <?php echo $this->renderTeamOptions($teams, $currentFilters['team'] ?? 0); ?>
            </select>
        </div>
        <div class="season-leaders-form__group">
            <label class="season-leaders-form__label">Year:</label>
            <select name="year">
                <?php echo $this->renderYearOptions($years, $currentFilters['year'] ?? ''); ?>
            </select>
        </div>
        <div class="season-leaders-form__group">
            <label class="season-leaders-form__label">Sort By:</label>
            <select name="sortby">
                <?php echo $this->renderSortOptions($currentFilters['sortby'] ?? '1'); ?>
            </select>
        </div>
        <button type="submit" class="season-leaders-form__submit">Search Season Data</button>
    </div>
</form>
        <?php
        return ob_get_clean();
    }

    /**
     * Render team dropdown options
     *
     * @param array $teams Array of team data
     * @param int $selectedTeam Selected team ID
     * @return string HTML options
     */
    private function renderTeamOptions(array $teams, int $selectedTeam): string
    {
        $html = '<option value="0">All</option>' . "\n";
        foreach ($teams as $team) {
            $tid = (int) ($team['TeamID'] ?? 0);
            $teamName = $team['Team'] ?? '';
            $selected = ($selectedTeam == $tid) ? ' selected' : '';
            $html .= '<option value="' . htmlspecialchars((string) $tid) . '"' . $selected . '>' . htmlspecialchars($teamName) . '</option>' . "\n";
        }
        return $html;
    }

    /**
     * Render year dropdown options
     *
     * @param array $years Available years
     * @param string $selectedYear Selected year
     * @return string HTML options
     */
    private function renderYearOptions(array $years, string $selectedYear): string
    {
        $html = '<option value="">All</option>' . "\n";
        foreach ($years as $year) {
            $escapedYear = is_int($year) ? (string)$year : htmlspecialchars((string)$year);
            $selected = ($selectedYear == $year) ? ' selected' : '';
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
            $selected = ($i == (int)$selectedSort) ? ' selected' : '';
            $html .= '<option value="' . htmlspecialchars((string)$i) . '"' . $selected . '>' . htmlspecialchars($label) . '</option>' . "\n";
            $i++;
        }
        return $html;
    }

    /**
     * @see SeasonLeadersViewInterface::renderTableHeader()
     */
    public function renderTableHeader(): string
    {
        ob_start();
        ?>
<style>
.season-leaders-table {
    font-family: var(--font-sans, 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif);
    border-collapse: separate;
    border-spacing: 0;
    border: none;
    border-radius: var(--radius-lg, 0.5rem);
    overflow: hidden;
    box-shadow: var(--shadow-md, 0 4px 6px -1px rgb(0 0 0 / 0.1));
    width: 100%;
    margin: 0 auto;
}
.season-leaders-table thead {
    background: linear-gradient(135deg, var(--navy-800, #1e293b), var(--navy-900, #0f172a));
}
.season-leaders-table th {
    color: white;
    font-family: var(--font-display, 'Poppins', sans-serif);
    font-weight: 600;
    font-size: 0.625rem;
    text-transform: uppercase;
    letter-spacing: 0.02em;
    padding: 0.625rem 0.375rem;
    text-align: center;
    white-space: nowrap;
}
.season-leaders-table td {
    color: var(--gray-800, #1f2937);
    font-size: 0.6875rem;
    padding: 0.5rem 0.375rem;
    text-align: center;
}
.season-leaders-table tbody tr {
    transition: background-color 150ms ease;
}
.season-leaders-table tbody tr:nth-child(odd) {
    background-color: white;
}
.season-leaders-table tbody tr:nth-child(even) {
    background-color: var(--gray-50, #f9fafb);
}
.season-leaders-table tbody tr:hover {
    background-color: var(--gray-100, #f3f4f6);
}
.season-leaders-table a {
    color: var(--gray-800, #1f2937);
    text-decoration: none;
    font-weight: 500;
    transition: color 150ms ease;
}
.season-leaders-table a:hover {
    color: var(--accent-500, #f97316);
}
.season-leaders-table .rank-cell {
    font-weight: 600;
    color: var(--navy-700, #334155);
}

/* Mobile sticky columns support */
@media (max-width: 768px) {
    .season-leaders-table.responsive-table th.sticky-col-1,
    .season-leaders-table.responsive-table td.sticky-col-1 {
        position: sticky;
        left: 0;
        z-index: 1;
        min-width: 36px;
    }
    .season-leaders-table.responsive-table th.sticky-col-2,
    .season-leaders-table.responsive-table td.sticky-col-2 {
        position: sticky;
        left: 36px;
        z-index: 1;
        min-width: 100px;
    }
    .season-leaders-table.responsive-table thead th.sticky-col-1,
    .season-leaders-table.responsive-table thead th.sticky-col-2 {
        background: linear-gradient(135deg, var(--navy-800, #1e293b), var(--navy-900, #0f172a));
        z-index: 3;
    }
    .season-leaders-table.responsive-table tbody tr:nth-child(odd) td.sticky-col-1,
    .season-leaders-table.responsive-table tbody tr:nth-child(odd) td.sticky-col-2 {
        background-color: white;
    }
    .season-leaders-table.responsive-table tbody tr:nth-child(even) td.sticky-col-1,
    .season-leaders-table.responsive-table tbody tr:nth-child(even) td.sticky-col-2 {
        background-color: var(--gray-50, #f9fafb);
    }
    .season-leaders-table.responsive-table tbody tr:hover td.sticky-col-1,
    .season-leaders-table.responsive-table tbody tr:hover td.sticky-col-2 {
        background-color: var(--gray-100, #f3f4f6);
    }
    .season-leaders-table.responsive-table td.sticky-col-2 {
        box-shadow: 2px 0 4px rgba(0, 0, 0, 0.05);
    }
}
</style>
<div class="table-scroll-container">
<table class="sortable season-leaders-table responsive-table">
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
        return ob_get_clean();
    }

    /**
     * @see SeasonLeadersViewInterface::renderPlayerRow()
     */
    public function renderPlayerRow(array $stats, int $rank): string
    {
        ob_start();
        ?>
<tr>
    <td class="rank-cell sticky-col-1"><?= htmlspecialchars((string)$rank) ?>.</td>
    <td><?= htmlspecialchars((string)$stats['year']) ?></td>
    <td class="sticky-col-2"><a href="modules.php?name=Player&amp;pa=showpage&amp;pid=<?= htmlspecialchars((string)$stats['pid']) ?>"><?= htmlspecialchars($stats['name']) ?></a></td>
    <td><a href="modules.php?name=Team&amp;op=team&amp;teamID=<?= htmlspecialchars((string)$stats['teamid']) ?>"><?= htmlspecialchars($stats['teamname']) ?></a></td>
    <td><?= htmlspecialchars((string)$stats['games']) ?></td>
    <td><?= htmlspecialchars((string)$stats['mpg']) ?></td>
    <td><?= htmlspecialchars((string)$stats['fgmpg']) ?></td>
    <td><?= htmlspecialchars((string)$stats['fgapg']) ?></td>
    <td><?= htmlspecialchars((string)$stats['fgp']) ?></td>
    <td><?= htmlspecialchars((string)$stats['ftmpg']) ?></td>
    <td><?= htmlspecialchars((string)$stats['ftapg']) ?></td>
    <td><?= htmlspecialchars((string)$stats['ftp']) ?></td>
    <td><?= htmlspecialchars((string)$stats['tgmpg']) ?></td>
    <td><?= htmlspecialchars((string)$stats['tgapg']) ?></td>
    <td><?= htmlspecialchars((string)$stats['tgp']) ?></td>
    <td><?= htmlspecialchars((string)$stats['orbpg']) ?></td>
    <td><?= htmlspecialchars((string)$stats['rpg']) ?></td>
    <td><?= htmlspecialchars((string)$stats['apg']) ?></td>
    <td><?= htmlspecialchars((string)$stats['spg']) ?></td>
    <td><?= htmlspecialchars((string)$stats['tpg']) ?></td>
    <td><?= htmlspecialchars((string)$stats['bpg']) ?></td>
    <td><?= htmlspecialchars((string)$stats['fpg']) ?></td>
    <td><?= htmlspecialchars((string)$stats['ppg']) ?></td>
    <td><?= htmlspecialchars((string)$stats['qa']) ?></td>
</tr>
        <?php
        return ob_get_clean();
    }

    /**
     * @see SeasonLeadersViewInterface::renderTableFooter()
     */
    public function renderTableFooter(): string
    {
        return '</tbody></table></div>'; // Close table and scroll container
    }
}
