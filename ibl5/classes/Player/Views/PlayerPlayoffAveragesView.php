<?php

declare(strict_types=1);

namespace Player\Views;

use Player\PlayerRepository;
use Player\PlayerStatsRepository;
use Player\Contracts\PlayerPlayoffAveragesViewInterface;

/**
 * PlayerPlayoffAveragesView - Renders playoff averages table
 * 
 * Shows year-by-year playoff statistics averages with career averages row.
 * Uses PlayerRepository and PlayerStatsRepository for all database access.
 * 
 * @see PlayerPlayoffAveragesViewInterface
 */
class PlayerPlayoffAveragesView implements PlayerPlayoffAveragesViewInterface
{
    private PlayerRepository $repository;
    private PlayerStatsRepository $statsRepository;

    public function __construct(PlayerRepository $repository, PlayerStatsRepository $statsRepository)
    {
        $this->repository = $repository;
        $this->statsRepository = $statsRepository;
    }

    /**
     * @see PlayerViewInterface::render()
     */
    public function render(): string
    {
        return '';
    }

    /**
     * @see PlayerPlayoffAveragesViewInterface::renderAverages()
     */
    public function renderAverages(string $playerName): string
    {
        $playoffStats = $this->repository->getPlayoffStats($playerName);
        $careerAverages = $this->statsRepository->getPlayoffCareerAverages($playerName);

        ob_start();
        ?>
<table border=1 cellspacing=0 class="sortable" style='margin: 0 auto;'>
    <tr>
        <td colspan=16 style='font-weight:bold;text-align:center;background-color:#00c;color:#fff;'>Playoff Averages</td>
    </tr>
    <tr>
        <th>year</th>
        <th>team</th>
        <th>g</th>
        <th>min</th>
        <th>fg%</th>
        <th>ft%</th>
        <th>3g%</th>
        <th>orb</th>
        <th>reb</th>
        <th>ast</th>
        <th>stl</th>
        <th>to</th>
        <th>blk</th>
        <th>pf</th>
        <th>pts</th>
        <th>eff</th>
    </tr>
        <?php
        foreach ($playoffStats as $row) {
            $year = (int)$row['year'];
            $team = htmlspecialchars($row['team']);
            $gm = (int)$row['games'];
            
            if ($gm > 0) {
                $min = number_format((float)$row['minutes'] / $gm, 1);
                $fgp = $this->formatPercentage((int)$row['fgm'], (int)$row['fga']);
                $ftp = $this->formatPercentage((int)$row['ftm'], (int)$row['fta']);
                $tgp = $this->formatPercentage((int)$row['tgm'], (int)$row['tga']);
                $orb = number_format((float)$row['orb'] / $gm, 1);
                $reb = number_format((float)$row['reb'] / $gm, 1);
                $ast = number_format((float)$row['ast'] / $gm, 1);
                $stl = number_format((float)$row['stl'] / $gm, 1);
                $tvr = number_format((float)$row['tvr'] / $gm, 1);
                $blk = number_format((float)$row['blk'] / $gm, 1);
                $pf = number_format((float)$row['pf'] / $gm, 1);
                $pts = number_format((float)$row['pts'] / $gm, 1);
                $eff = isset($row['eff']) ? number_format((float)$row['eff'] / $gm, 1) : '0.0';
            } else {
                $min = $fgp = $ftp = $tgp = $orb = $reb = $ast = $stl = $tvr = $blk = $pf = $pts = $eff = '0.0';
            }
            ?>
    <tr>
        <td><center><?= $year ?></center></td>
        <td><center><?= $team ?></center></td>
        <td><center><?= $gm ?></center></td>
        <td><center><?= $min ?></center></td>
        <td><center><?= $fgp ?></center></td>
        <td><center><?= $ftp ?></center></td>
        <td><center><?= $tgp ?></center></td>
        <td><center><?= $orb ?></center></td>
        <td><center><?= $reb ?></center></td>
        <td><center><?= $ast ?></center></td>
        <td><center><?= $stl ?></center></td>
        <td><center><?= $tvr ?></center></td>
        <td><center><?= $blk ?></center></td>
        <td><center><?= $pf ?></center></td>
        <td><center><?= $pts ?></center></td>
        <td><center><?= $eff ?></center></td>
    </tr>
            <?php
        }

        // Career averages row
        if ($careerAverages) {
            $carFgp = $this->formatPercentage((int)$careerAverages['fgm'], (int)$careerAverages['fga']);
            $carFtp = $this->formatPercentage((int)$careerAverages['ftm'], (int)$careerAverages['fta']);
            $carTgp = $this->formatPercentage((int)$careerAverages['tgm'], (int)$careerAverages['tga']);
            ?>
    <tr style="font-weight: bold;">
        <td colspan=2><center>Playoff Career</center></td>
        <td><center><?= (int)$careerAverages['games'] ?></center></td>
        <td><center><?= number_format((float)$careerAverages['min'], 1) ?></center></td>
        <td><center><?= $carFgp ?></center></td>
        <td><center><?= $carFtp ?></center></td>
        <td><center><?= $carTgp ?></center></td>
        <td><center><?= number_format((float)$careerAverages['orb'], 1) ?></center></td>
        <td><center><?= number_format((float)$careerAverages['reb'], 1) ?></center></td>
        <td><center><?= number_format((float)$careerAverages['ast'], 1) ?></center></td>
        <td><center><?= number_format((float)$careerAverages['stl'], 1) ?></center></td>
        <td><center><?= number_format((float)$careerAverages['tvr'], 1) ?></center></td>
        <td><center><?= number_format((float)$careerAverages['blk'], 1) ?></center></td>
        <td><center><?= number_format((float)$careerAverages['pf'], 1) ?></center></td>
        <td><center><?= number_format((float)$careerAverages['pts'], 1) ?></center></td>
        <td><center><?= number_format((float)($careerAverages['eff'] ?? 0), 1) ?></center></td>
    </tr>
            <?php
        }
        ?>
</table>
        <?php
        return ob_get_clean();
    }

    /**
     * Calculate percentage from made and attempted values
     */
    private function formatPercentage(int $made, int $attempted): string
    {
        if ($attempted === 0) {
            return '0.000';
        }
        return sprintf('%01.3f', $made / $attempted);
    }
}
