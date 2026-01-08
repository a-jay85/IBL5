<?php

declare(strict_types=1);

namespace Player\Views;

use Player\PlayerStatsRepository;
use Player\Contracts\PlayerRegularSeasonAveragesViewInterface;

/**
 * PlayerRegularSeasonAveragesView - Renders regular season averages table
 * 
 * Shows season-by-season averages with career averages row.
 * Uses PlayerStatsRepository for all database access.
 * 
 * @see PlayerRegularSeasonAveragesViewInterface
 */
class PlayerRegularSeasonAveragesView implements PlayerRegularSeasonAveragesViewInterface
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
     * @see PlayerRegularSeasonAveragesViewInterface::renderAverages()
     */
    public function renderAverages(int $playerID): string
    {
        $historicalStats = $this->repository->getHistoricalStats($playerID);
        $careerAverages = $this->repository->getSeasonCareerAveragesById($playerID);

        ob_start();
        ?>
<table border=1 cellspacing=0 class="sortable" style='margin: 0 auto;'>
    <tr>
        <td colspan=21 style='font-weight:bold; text-align:center;background-color:#00c;color:#fff;'>Regular Season Averages</td>
    </tr>
    <tr>
        <th>year</th>
        <th>team</th>
        <th>g</th>
        <th>min</th>
        <th>fgm</th>
        <th>fga</th>
        <th>fgp</th>
        <th>ftm</th>
        <th>fta</th>
        <th>ftp</th>
        <th>3gm</th>
        <th>3ga</th>
        <th>3gp</th>
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
            
            if ($gm > 0) {
                $min = number_format((float)$row['minutes'] / $gm, 1);
                $fgm = number_format((float)$row['fgm'] / $gm, 1);
                $fga = number_format((float)$row['fga'] / $gm, 1);
                $fgp = $this->formatPercentage((int)$row['fgm'], (int)$row['fga']);
                $ftm = number_format((float)$row['ftm'] / $gm, 1);
                $fta = number_format((float)$row['fta'] / $gm, 1);
                $ftp = $this->formatPercentage((int)$row['ftm'], (int)$row['fta']);
                $tgm = number_format((float)$row['tgm'] / $gm, 1);
                $tga = number_format((float)$row['tga'] / $gm, 1);
                $tgp = $this->formatPercentage((int)$row['tgm'], (int)$row['tga']);
                $orb = number_format((float)$row['orb'] / $gm, 1);
                $reb = number_format((float)$row['reb'] / $gm, 1);
                $ast = number_format((float)$row['ast'] / $gm, 1);
                $stl = number_format((float)$row['stl'] / $gm, 1);
                $tvr = number_format((float)$row['tvr'] / $gm, 1);
                $blk = number_format((float)$row['blk'] / $gm, 1);
                $pf = number_format((float)$row['pf'] / $gm, 1);
                $pts = number_format((float)$row['pts'] / $gm, 1);
            } else {
                $min = $fgm = $fga = $fgp = $ftm = $fta = $ftp = $tgm = $tga = $tgp = '0.0';
                $orb = $reb = $ast = $stl = $tvr = $blk = $pf = $pts = '0.0';
            }
            ?>
    <tr>
        <td><center><?= $year ?></center></td>
        <td><center><a href="modules.php?name=Team&op=team&teamID=<?= $teamId ?>&yr=<?= $year ?>"><?= $team ?></a></center></td>
        <td><center><?= $gm ?></center></td>
        <td><center><?= $min ?></center></td>
        <td><center><?= $fgm ?></center></td>
        <td><center><?= $fga ?></center></td>
        <td><center><?= $fgp ?></center></td>
        <td><center><?= $ftm ?></center></td>
        <td><center><?= $fta ?></center></td>
        <td><center><?= $ftp ?></center></td>
        <td><center><?= $tgm ?></center></td>
        <td><center><?= $tga ?></center></td>
        <td><center><?= $tgp ?></center></td>
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

        // Career averages row
        if ($careerAverages) {
            $carFgp = $this->formatPercentage((int)round((float)$careerAverages['fgm']), (int)round((float)$careerAverages['fga']));
            $carFtp = $this->formatPercentage((int)round((float)$careerAverages['ftm']), (int)round((float)$careerAverages['fta']));
            $carTgp = $this->formatPercentage((int)round((float)$careerAverages['tgm']), (int)round((float)$careerAverages['tga']));
            ?>
    <tr style="font-weight: bold;">
        <td colspan=2><center>Career</center></td>
        <td><center><?= (int)$careerAverages['games'] ?></center></td>
        <td><center><?= number_format((float)$careerAverages['minutes'], 1) ?></center></td>
        <td><center><?= number_format((float)$careerAverages['fgm'], 1) ?></center></td>
        <td><center><?= number_format((float)$careerAverages['fga'], 1) ?></center></td>
        <td><center><?= $carFgp ?></center></td>
        <td><center><?= number_format((float)$careerAverages['ftm'], 1) ?></center></td>
        <td><center><?= number_format((float)$careerAverages['fta'], 1) ?></center></td>
        <td><center><?= $carFtp ?></center></td>
        <td><center><?= number_format((float)$careerAverages['tgm'], 1) ?></center></td>
        <td><center><?= number_format((float)$careerAverages['tga'], 1) ?></center></td>
        <td><center><?= $carTgp ?></center></td>
        <td><center><?= number_format((float)$careerAverages['orb'], 1) ?></center></td>
        <td><center><?= number_format((float)$careerAverages['reb'], 1) ?></center></td>
        <td><center><?= number_format((float)$careerAverages['ast'], 1) ?></center></td>
        <td><center><?= number_format((float)$careerAverages['stl'], 1) ?></center></td>
        <td><center><?= number_format((float)$careerAverages['tvr'], 1) ?></center></td>
        <td><center><?= number_format((float)$careerAverages['blk'], 1) ?></center></td>
        <td><center><?= number_format((float)$careerAverages['pf'], 1) ?></center></td>
        <td><center><?= number_format((float)$careerAverages['pts'], 1) ?></center></td>
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
