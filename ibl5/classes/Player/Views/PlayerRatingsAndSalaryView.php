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
            /** @var array{pid: int, name: string, year: int, team: string, teamid: int, games: int, minutes: int, fgm: int, fga: int, ftm: int, fta: int, tgm: int, tga: int, orb: int, reb: int, ast: int, stl: int, blk: int, tvr: int, pf: int, pts: int, r_2ga: int, r_2gp: int, r_fta: int, r_ftp: int, r_3ga: int, r_3gp: int, r_orb: int, r_drb: int, r_ast: int, r_stl: int, r_blk: int, r_tvr: int, r_oo: int, r_drive_off: int, r_po: int, r_trans_off: int, r_od: int, r_dd: int, r_pd: int, r_td: int, salary: int} $row */
            $r_oo = $row['r_oo'];
            $r_drive_off = $row['r_drive_off'];
            $r_po = $row['r_po'];
            $r_trans_off = $row['r_trans_off'];
            $r_od = $row['r_od'];
            $r_dd = $row['r_dd'];
            $r_pd = $row['r_pd'];
            $r_td = $row['r_td'];
            $salary = $row['salary'];

            $r_Off = $r_oo + $r_drive_off + $r_po + $r_trans_off;
            $r_Def = $r_od + $r_dd + $r_pd + $r_td;
            $totalSalary += $salary;
            ?>
    <tr>
        <td><?= $row['year'] ?></td>
        <td><?= $row['r_2ga'] ?></td>
        <td><?= $row['r_2gp'] ?></td>
        <td><?= $row['r_fta'] ?></td>
        <td><?= $row['r_ftp'] ?></td>
        <td><?= $row['r_3ga'] ?></td>
        <td><?= $row['r_3gp'] ?></td>
        <td><?= $row['r_orb'] ?></td>
        <td><?= $row['r_drb'] ?></td>
        <td><?= $row['r_ast'] ?></td>
        <td><?= $row['r_stl'] ?></td>
        <td><?= $row['r_blk'] ?></td>
        <td><?= $row['r_tvr'] ?></td>
        <td><?= $r_oo ?></td>
        <td><?= $r_drive_off ?></td>
        <td><?= $r_po ?></td>
        <td><?= $r_trans_off ?></td>
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
        <td colspan=24 class="text-center font-bold">Total Career Salary Earned: <?= $totalSalaryMillion ?> million dollars</td>
    </tr>
</table>
        <?php
        return (string) ob_get_clean();
    }
}
