<?php

declare(strict_types=1);

namespace Player\Views;

use Player\PlayerStatsRepository;
use Player\Contracts\PlayerRegularSeasonTotalsViewInterface;
use Utilities\HtmlSanitizer;

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
            $year = (int)$row['year'];
            /** @var string $team */
            $team = HtmlSanitizer::safeHtmlOutput($row['team']);
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
        <td><?= $year ?></td>
        <td><a href="modules.php?name=Team&op=team&teamID=<?= $teamId ?>&yr=<?= $year ?>"><?= $team ?></a></td>
        <td><?= $gm ?></td>
        <td><?= $min ?></td>
        <td><?= $fgm ?>-<?= $fga ?></td>
        <td><?= $ftm ?>-<?= $fta ?></td>
        <td><?= $tgm ?>-<?= $tga ?></td>
        <td><?= $orb ?></td>
        <td><?= $reb ?></td>
        <td><?= $ast ?></td>
        <td><?= $stl ?></td>
        <td><?= $tvr ?></td>
        <td><?= $blk ?></td>
        <td><?= $pf ?></td>
        <td><?= $pts ?></td>
    </tr>
            <?php
        }
        
        // Recalculate career total points to ensure accuracy
        $carTotals['pts'] = (2 * $carTotals['fgm']) + $carTotals['ftm'] + $carTotals['tgm'];
        ?>
    <tr class="player-table-row-bold">
        <td colspan=2>Career</td>
        <td><?= $carTotals['gm'] ?></td>
        <td><?= $carTotals['min'] ?></td>
        <td><?= $carTotals['fgm'] ?>-<?= $carTotals['fga'] ?></td>
        <td><?= $carTotals['ftm'] ?>-<?= $carTotals['fta'] ?></td>
        <td><?= $carTotals['tgm'] ?>-<?= $carTotals['tga'] ?></td>
        <td><?= $carTotals['orb'] ?></td>
        <td><?= $carTotals['reb'] ?></td>
        <td><?= $carTotals['ast'] ?></td>
        <td><?= $carTotals['stl'] ?></td>
        <td><?= $carTotals['tvr'] ?></td>
        <td><?= $carTotals['blk'] ?></td>
        <td><?= $carTotals['pf'] ?></td>
        <td><?= $carTotals['pts'] ?></td>
    </tr>
</table>
        <?php
        return (string) ob_get_clean();
    }
}
