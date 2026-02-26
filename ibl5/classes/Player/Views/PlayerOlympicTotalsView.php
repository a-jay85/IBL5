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
            $year = $row['year'];
            $team = HtmlSanitizer::safeHtmlOutput($row['team']);
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
        <td><?= $year ?></td>
        <td><?= $team ?></td>
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
        ?>
    <tr class="player-table-row-bold">
        <td colspan=2>Olympics Totals</td>
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
