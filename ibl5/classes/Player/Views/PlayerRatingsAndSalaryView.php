<?php

declare(strict_types=1);

namespace Player\Views;

use Player\PlayerStatsRepository;
use Player\Contracts\PlayerRatingsAndSalaryViewInterface;

/**
 * PlayerRatingsAndSalaryView - Renders ratings and salary history table
 * 
 * Shows year-by-year player ratings and salary information.
 * Uses PlayerStatsRepository for all database access.
 * 
 * @see PlayerRatingsAndSalaryViewInterface
 */
class PlayerRatingsAndSalaryView implements PlayerRatingsAndSalaryViewInterface
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
     * @see PlayerRatingsAndSalaryViewInterface::renderRatingsAndSalary()
     */
    public function renderRatingsAndSalary(int $playerID): string
    {
        $historicalStats = $this->repository->getHistoricalStats($playerID);

        $totalSalary = 0;

        ob_start();
        ?>
<table class="sortable player-table">
    <tr>
        <td colspan=24 class="player-table-header">Ratings by Year</td>
    </tr>
    <tr>
        <th>year</th>
        <th>2ga</th>
        <th>2gp</th>
        <th>fta</th>
        <th>ftp</th>
        <th>3ga</th>
        <th>3gp</th>
        <th>orb</th>
        <th>drb</th>
        <th>ast</th>
        <th>stl</th>
        <th>blk</th>
        <th>tvr</th>
        <th>oo</th>
        <th>do</th>
        <th>po</th>
        <th>to</th>
        <th>od</th>
        <th>dd</th>
        <th>pd</th>
        <th>td</th>
        <th>Off</th>
        <th>Def</th>
        <th>Salary</th>
    </tr>
        <?php
        foreach ($historicalStats as $row) {
            $year = (int)$row['year'];
            $r_2ga = (int)$row['r_2ga'];
            $r_2gp = (int)$row['r_2gp'];
            $r_fta = (int)$row['r_fta'];
            $r_ftp = (int)$row['r_ftp'];
            $r_3ga = (int)$row['r_3ga'];
            $r_3gp = (int)$row['r_3gp'];
            $r_orb = (int)$row['r_orb'];
            $r_drb = (int)$row['r_drb'];
            $r_ast = (int)$row['r_ast'];
            $r_stl = (int)$row['r_stl'];
            $r_blk = (int)$row['r_blk'];
            $r_tvr = (int)$row['r_tvr'];
            $r_oo = (int)$row['r_oo'];
            $r_do = (int)$row['r_do'];
            $r_po = (int)$row['r_po'];
            $r_to = (int)$row['r_to'];
            $r_od = (int)$row['r_od'];
            $r_dd = (int)$row['r_dd'];
            $r_pd = (int)$row['r_pd'];
            $r_td = (int)$row['r_td'];
            $salary = (int)$row['salary'];

            $r_Off = $r_oo + $r_do + $r_po + $r_to;
            $r_Def = $r_od + $r_dd + $r_pd + $r_td;

            $totalSalary += $salary;
            ?>
    <tr>
        <td><?= $year ?></td>
        <td><?= $r_2ga ?></td>
        <td><?= $r_2gp ?></td>
        <td><?= $r_fta ?></td>
        <td><?= $r_ftp ?></td>
        <td><?= $r_3ga ?></td>
        <td><?= $r_3gp ?></td>
        <td><?= $r_orb ?></td>
        <td><?= $r_drb ?></td>
        <td><?= $r_ast ?></td>
        <td><?= $r_stl ?></td>
        <td><?= $r_blk ?></td>
        <td><?= $r_tvr ?></td>
        <td><?= $r_oo ?></td>
        <td><?= $r_do ?></td>
        <td><?= $r_po ?></td>
        <td><?= $r_to ?></td>
        <td><?= $r_od ?></td>
        <td><?= $r_dd ?></td>
        <td><?= $r_pd ?></td>
        <td><?= $r_td ?></td>
        <td><?= $r_Off ?></td>
        <td><?= $r_Def ?></td>
        <td><?= $salary ?></td>
    </tr>
            <?php
        }

        $totalSalaryMillion = $totalSalary / 100;
        ?>
    <tr>
        <td colspan=24 class="text-center text-bold">Total Career Salary Earned: <?= $totalSalaryMillion ?> million dollars</td>
    </tr>
</table>
        <?php
        return ob_get_clean();
    }
}
