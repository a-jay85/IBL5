<?php

declare(strict_types=1);

namespace Player\Views;

use Player\PlayerStatsRepository;
use Player\Contracts\PlayerRegularSeasonTotalsViewInterface;
use Security\HtmlSanitizer;

/**
 * PlayerRegularSeasonTotalsView - Renders regular season totals table
 * 
 * Shows season-by-season totals with career totals row.
 * Uses PlayerStatsRepository for all database access.
 * 
 * @see PlayerRegularSeasonTotalsViewInterface
 */
class PlayerRegularSeasonTotalsView implements PlayerRegularSeasonTotalsViewInterface
{
    private PlayerStatsRepository $repository;

    public function __construct(PlayerStatsRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @see PlayerViewInterface::render()
     */
    public function render(): string
    {
        return '';
    }

    /**
     * @see PlayerRegularSeasonTotalsViewInterface::renderTotals()
     */
    public function renderTotals(int $playerID): string
    {
        $historicalStats = $this->repository->getHistoricalStats($playerID);

        // Initialize career totals
        $carTotals = [
            'gm' => 0, 'min' => 0, 'fgm' => 0, 'fga' => 0, 'ftm' => 0, 'fta' => 0,
            'tgm' => 0, 'tga' => 0, 'orb' => 0, 'reb' => 0, 'ast' => 0, 'stl' => 0,
            'blk' => 0, 'tvr' => 0, 'pf' => 0, 'pts' => 0
        ];

        ob_start();
        ?>
<table class="sortable player-table">
    <tr>
        <td colspan=15 class="player-table-header">Regular Season Totals</td>
    </tr>
    <tr>
        <th>year</th>
        <th>team</th>
        <th>g</th>
        <th>min</th>
        <th>FGM-FGA</th>
        <th>FTM-FTA</th>
        <th>3GM-3GA</th>
        <th>orb</th>
        <th>reb</th>
        <th>ast</th>
        <th>stl</th>
        <th>to</th>
        <th>blk</th>
        <th>pf</th>
        <th>pts</th>
    </tr>
        <?php
        foreach ($historicalStats as $row) {
            $gm = $row['games'];
            $min = $row['minutes'];
            $fgm = $row['fgm'];
            $fga = $row['fga'];
            $ftm = $row['ftm'];
            $fta = $row['fta'];
            $tgm = $row['tgm'];
            $tga = $row['tga'];
            $orb = $row['orb'];
            $reb = $row['reb'];
            $ast = $row['ast'];
            $stl = $row['stl'];
            $tvr = $row['tvr'];
            $blk = $row['blk'];
            $pf = $row['pf'];
            $pts = $row['pts'];

            // Calculate points if pts is 0 (e.g., 2006 season)
            // Formula: 2*fgm + ftm + tgm (fgm includes all field goals, tgm adds the extra point for 3-pointers)
            if ($pts === 0) {
                $pts = (2 * $fgm) + $ftm + $tgm;
            }

            // Accumulate career totals
            $carTotals['gm'] += $gm;
            $carTotals['min'] += $min;
            $carTotals['fgm'] += $fgm;
            $carTotals['fga'] += $fga;
            $carTotals['ftm'] += $ftm;
            $carTotals['fta'] += $fta;
            $carTotals['tgm'] += $tgm;
            $carTotals['tga'] += $tga;
            $carTotals['orb'] += $orb;
            $carTotals['reb'] += $reb;
            $carTotals['ast'] += $ast;
            $carTotals['stl'] += $stl;
            $carTotals['blk'] += $blk;
            $carTotals['tvr'] += $tvr;
            $carTotals['pf'] += $pf;
            $carTotals['pts'] += $pts;
            ?>
    <tr>
        <td><?= (int)$row['year'] ?></td>
        <td><a href="modules.php?name=Team&op=team&teamid=<?= (int)$row['teamid'] ?>&yr=<?= (int)$row['year'] ?>"><?= HtmlSanitizer::e($row['team']) ?></a></td>
        <td><?= (int)$gm ?></td>
        <td><?= (int)$min ?></td>
        <td><?= (int)$fgm ?>-<?= (int)$fga ?></td>
        <td><?= (int)$ftm ?>-<?= (int)$fta ?></td>
        <td><?= (int)$tgm ?>-<?= (int)$tga ?></td>
        <td><?= (int)$orb ?></td>
        <td><?= (int)$reb ?></td>
        <td><?= (int)$ast ?></td>
        <td><?= (int)$stl ?></td>
        <td><?= (int)$tvr ?></td>
        <td><?= (int)$blk ?></td>
        <td><?= (int)$pf ?></td>
        <td><?= (int)$pts ?></td>
    </tr>
            <?php
        }
        
        // Recalculate career total points to ensure accuracy
        $carTotals['pts'] = (2 * $carTotals['fgm']) + $carTotals['ftm'] + $carTotals['tgm'];
        ?>
    <tr class="player-table-row-bold">
        <td colspan=2>Career</td>
        <td><?= (int)$carTotals['gm'] ?></td>
        <td><?= (int)$carTotals['min'] ?></td>
        <td><?= (int)$carTotals['fgm'] ?>-<?= (int)$carTotals['fga'] ?></td>
        <td><?= (int)$carTotals['ftm'] ?>-<?= (int)$carTotals['fta'] ?></td>
        <td><?= (int)$carTotals['tgm'] ?>-<?= (int)$carTotals['tga'] ?></td>
        <td><?= (int)$carTotals['orb'] ?></td>
        <td><?= (int)$carTotals['reb'] ?></td>
        <td><?= (int)$carTotals['ast'] ?></td>
        <td><?= (int)$carTotals['stl'] ?></td>
        <td><?= (int)$carTotals['tvr'] ?></td>
        <td><?= (int)$carTotals['blk'] ?></td>
        <td><?= (int)$carTotals['pf'] ?></td>
        <td><?= (int)$carTotals['pts'] ?></td>
    </tr>
</table>
        <?php
        return (string) ob_get_clean();
    }
}
