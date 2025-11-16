<?php

declare(strict_types=1);

namespace Leaderboards;

/**
 * LeaderboardsView - Handles HTML rendering for leaderboards
 * 
 * Separates presentation logic from business logic.
 * Uses output buffering pattern for cleaner, more maintainable HTML.
 */
class LeaderboardsView
{
    private LeaderboardsService $service;

    public function __construct(LeaderboardsService $service)
    {
        $this->service = $service;
    }

    /**
     * Render the filter form
     * 
     * @param array $currentFilters Current filter values
     * @return string HTML for the filter form
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
<form name="Leaderboards" method="post" action="modules.php?name=Leaderboards">
    <table style="margin: auto;">
        <tr>
            <td>
                Type: <select name="boards_type">
                    <?php foreach ($boardTypes as $key => $value): ?>
                        <?php $selected = ($boardsType == $value) ? ' SELECTED' : ''; ?>
                        <option value="<?= htmlspecialchars($value) ?>"<?= $selected ?>><?= htmlspecialchars($value) ?></option>
                    <?php endforeach; ?>
                </select>
            </td>
            <td>
                Category: <select name="sort_cat">
                    <?php foreach ($sortCategories as $key => $value): ?>
                        <?php $selected = ($sortCat == $value) ? ' SELECTED' : ''; ?>
                        <option value="<?= htmlspecialchars($value) ?>"<?= $selected ?>><?= htmlspecialchars($value) ?></option>
                    <?php endforeach; ?>
                </select>
            </td>
            <td>
                Include Retirees: <select name="active">
                    <option value="0"<?= ($active == '0') ? ' SELECTED' : '' ?>>Yes</option>
                    <option value="1"<?= ($active == '1') ? ' SELECTED' : '' ?>>No</option>
                </select>
            </td>
            <td>
                Limit: <input type="number" name="display" style="width: 4em" value="<?= htmlspecialchars((string)$display) ?>"> Records
            </td>
            <td>
                <input type="hidden" name="submitted" value="1">
                <input type="submit" value="Display Leaderboards">
            </td>
        </tr>
    </table>
</form>
        <?php
        return ob_get_clean();
    }

    /**
     * Render the statistics table header
     * 
     * @return string HTML table header
     */
    public function renderTableHeader(): string
    {
        ob_start();
        ?>
<h2 style="text-align: center;">Leaderboards Display</h2>
<p>
<table class="sortable">
    <tr>
        <th style="text-align: center;">Rank</th>
        <th style="text-align: center;">Name</th>
        <th style="text-align: center;">Games</th>
        <th style="text-align: center;">Minutes</th>
        <th style="text-align: center;">FGM</th>
        <th style="text-align: center;">FGA</th>
        <th style="text-align: center;">FG%</th>
        <th style="text-align: center;">FTM</th>
        <th style="text-align: center;">FTA</th>
        <th style="text-align: center;">FT%</th>
        <th style="text-align: center;">3GM</th>
        <th style="text-align: center;">3GA</th>
        <th style="text-align: center;">3P%</th>
        <th style="text-align: center;">ORB</th>
        <th style="text-align: center;">REB</th>
        <th style="text-align: center;">AST</th>
        <th style="text-align: center;">STL</th>
        <th style="text-align: center;">TVR</th>
        <th style="text-align: center;">BLK</th>
        <th style="text-align: center;">FOULS</th>
        <th style="text-align: center;">PTS</th>
    </tr>
        <?php
        return ob_get_clean();
    }

    /**
     * Render a single player statistics row
     * 
     * @param array $stats Formatted player statistics
     * @param int $rank Player's rank in the leaderboard
     * @return string HTML table row
     */
    public function renderPlayerRow(array $stats, int $rank): string
    {
        ob_start();
        ?>
<tr>
    <td style="text-align: center;"><?= htmlspecialchars((string)$rank) ?></td>
    <td style="text-align: center;"><a href="modules.php?name=Player&pa=showpage&pid=<?= htmlspecialchars((string)$stats['pid']) ?>"><?= htmlspecialchars($stats['name']) ?></a></td>
    <td style="text-align: center;"><?= htmlspecialchars((string)$stats['games']) ?></td>
    <td style="text-align: center;"><?= htmlspecialchars((string)$stats['minutes']) ?></td>
    <td style="text-align: center;"><?= htmlspecialchars((string)$stats['fgm']) ?></td>
    <td style="text-align: center;"><?= htmlspecialchars((string)$stats['fga']) ?></td>
    <td style="text-align: center;"><?= htmlspecialchars((string)$stats['fgp']) ?></td>
    <td style="text-align: center;"><?= htmlspecialchars((string)$stats['ftm']) ?></td>
    <td style="text-align: center;"><?= htmlspecialchars((string)$stats['fta']) ?></td>
    <td style="text-align: center;"><?= htmlspecialchars((string)$stats['ftp']) ?></td>
    <td style="text-align: center;"><?= htmlspecialchars((string)$stats['tgm']) ?></td>
    <td style="text-align: center;"><?= htmlspecialchars((string)$stats['tga']) ?></td>
    <td style="text-align: center;"><?= htmlspecialchars((string)$stats['tgp']) ?></td>
    <td style="text-align: center;"><?= htmlspecialchars((string)$stats['orb']) ?></td>
    <td style="text-align: center;"><?= htmlspecialchars((string)$stats['reb']) ?></td>
    <td style="text-align: center;"><?= htmlspecialchars((string)$stats['ast']) ?></td>
    <td style="text-align: center;"><?= htmlspecialchars((string)$stats['stl']) ?></td>
    <td style="text-align: center;"><?= htmlspecialchars((string)$stats['tvr']) ?></td>
    <td style="text-align: center;"><?= htmlspecialchars((string)$stats['blk']) ?></td>
    <td style="text-align: center;"><?= htmlspecialchars((string)$stats['pf']) ?></td>
    <td style="text-align: center;"><?= htmlspecialchars((string)$stats['pts']) ?></td>
</tr>
        <?php
        return ob_get_clean();
    }

    /**
     * Render the table footer
     * 
     * @return string HTML table closing tag
     */
    public function renderTableFooter(): string
    {
        return '</table></center></td></tr>';
    }
}
