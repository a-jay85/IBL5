<?php

declare(strict_types=1);

namespace Player\Views;

use Player\PlayerRepository;
use Player\Contracts\PlayerOlympicTotalsViewInterface;
use Utilities\HtmlSanitizer;

/**
 * PlayerOlympicTotalsView - Renders Olympics totals table
 *
 * Shows year-by-year Olympics statistics totals with career totals row.
 * Uses PlayerRepository for all database access.
 *
 * @see PlayerOlympicTotalsViewInterface
 */
class PlayerOlympicTotalsView implements PlayerOlympicTotalsViewInterface
{
    private PlayerRepository $repository;

    public function __construct(PlayerRepository $repository)
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
     * @see PlayerOlympicTotalsViewInterface::renderTotals()
     */
    public function renderTotals(int $playerID): string
    {
        $olympicsStats = $this->repository->getOlympicsStats($playerID);

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
        <td colspan=15 class="player-table-header">Olympics Totals</td>
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
        foreach ($olympicsStats as $row) {
            /** @var array{team: string, year: int, games: int, minutes: int, fgm: int, fga: int, ftm: int, fta: int, tgm: int, tga: int, orb: int, reb: int, ast: int, stl: int, tvr: int, blk: int, pf: int, pts: int} $row */
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
            // Calculate points: 2*fgm + ftm + tgm
            $pts = $fgm + $fgm + $ftm + $tgm;

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
        <td><?= HtmlSanitizer::e($row['team']) ?></td>
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
        ?>
    <tr class="player-table-row-bold">
        <td colspan=2>Olympics Totals</td>
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
