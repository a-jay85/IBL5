<?php

declare(strict_types=1);

namespace Player\Views;

use Player\PlayerStatsRepository;
use Player\Contracts\PlayerRegularSeasonTotalsViewInterface;

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
<table border=1 cellspacing=0 class="sortable" style='margin: 0 auto;'>
    <tr>
        <td colspan=15 style='font-weight:bold; text-align:center; background-color:#00c; color:#fff;'>Regular Season Totals</td>
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
            $year = (int)$row['year'];
            $team = htmlspecialchars($row['team']);
            $teamId = (int)$row['teamid'];
            $gm = (int)$row['games'];
            $min = (int)$row['minutes'];
            $fgm = (int)$row['fgm'];
            $fga = (int)$row['fga'];
            $ftm = (int)$row['ftm'];
            $fta = (int)$row['fta'];
            $tgm = (int)$row['tgm'];
            $tga = (int)$row['tga'];
            $orb = (int)$row['orb'];
            $reb = (int)$row['reb'];
            $ast = (int)$row['ast'];
            $stl = (int)$row['stl'];
            $tvr = (int)$row['tvr'];
            $blk = (int)$row['blk'];
            $pf = (int)$row['pf'];
            $pts = (int)$row['pts'];

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
        <td><center><?= $year ?></center></td>
        <td><center><a href="modules.php?name=Team&op=team&teamID=<?= $teamId ?>&yr=<?= $year ?>"><?= $team ?></a></center></td>
        <td><center><?= $gm ?></center></td>
        <td><center><?= $min ?></center></td>
        <td><center><?= $fgm ?>-<?= $fga ?></center></td>
        <td><center><?= $ftm ?>-<?= $fta ?></center></td>
        <td><center><?= $tgm ?>-<?= $tga ?></center></td>
        <td><center><?= $orb ?></center></td>
        <td><center><?= $reb ?></center></td>
        <td><center><?= $ast ?></center></td>
        <td><center><?= $stl ?></center></td>
        <td><center><?= $tvr ?></center></td>
        <td><center><?= $blk ?></center></td>
        <td><center><?= $pf ?></center></td>
        <td><center><?= $pts ?></center></td>
    </tr>
            <?php
        }
        ?>
    <tr style="font-weight: bold;">
        <td colspan=2><center>Career</center></td>
        <td><center><?= $carTotals['gm'] ?></center></td>
        <td><center><?= $carTotals['min'] ?></center></td>
        <td><center><?= $carTotals['fgm'] ?>-<?= $carTotals['fga'] ?></center></td>
        <td><center><?= $carTotals['ftm'] ?>-<?= $carTotals['fta'] ?></center></td>
        <td><center><?= $carTotals['tgm'] ?>-<?= $carTotals['tga'] ?></center></td>
        <td><center><?= $carTotals['orb'] ?></center></td>
        <td><center><?= $carTotals['reb'] ?></center></td>
        <td><center><?= $carTotals['ast'] ?></center></td>
        <td><center><?= $carTotals['stl'] ?></center></td>
        <td><center><?= $carTotals['tvr'] ?></center></td>
        <td><center><?= $carTotals['blk'] ?></center></td>
        <td><center><?= $carTotals['pf'] ?></center></td>
        <td><center><?= $carTotals['pts'] ?></center></td>
    </tr>
</table>
        <?php
        return ob_get_clean();
    }
}
