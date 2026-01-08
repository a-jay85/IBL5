<?php

declare(strict_types=1);

namespace Player\Views;

use Player\PlayerRepository;
use Player\PlayerStatsRepository;
use Player\Contracts\PlayerPlayoffAveragesViewInterface;
use BasketballStats\StatsFormatter;
use Utilities\HtmlSanitizer;

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
<table class="sortable player-table">
    <tr>
        <td colspan=16 class="player-table-header">Playoff Averages</td>
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
    </tr>
        <?php
        foreach ($playoffStats as $row) {
            $year = (int)$row['year'];
            $team = HtmlSanitizer::safeHtmlOutput($row['team']);
            $gm = (int)$row['games'];
            
            if ($gm > 0) {
                $min = StatsFormatter::formatPerGameAverage((float)$row['minutes'], $gm);
                $fgp = StatsFormatter::formatPercentage((int)$row['fgm'], (int)$row['fga']);
                $ftp = StatsFormatter::formatPercentage((int)$row['ftm'], (int)$row['fta']);
                $tgp = StatsFormatter::formatPercentage((int)$row['tgm'], (int)$row['tga']);
                $orb = StatsFormatter::formatPerGameAverage((float)$row['orb'], $gm);
                $reb = StatsFormatter::formatPerGameAverage((float)$row['reb'], $gm);
                $ast = StatsFormatter::formatPerGameAverage((float)$row['ast'], $gm);
                $stl = StatsFormatter::formatPerGameAverage((float)$row['stl'], $gm);
                $tvr = StatsFormatter::formatPerGameAverage((float)$row['tvr'], $gm);
                $blk = StatsFormatter::formatPerGameAverage((float)$row['blk'], $gm);
                $pf = StatsFormatter::formatPerGameAverage((float)$row['pf'], $gm);
                // Calculate points: pts = 2*fgm + ftm + tgm
                $totalPts = StatsFormatter::calculatePoints((int)$row['fgm'], (int)$row['ftm'], (int)$row['tgm']);
                $pts = StatsFormatter::formatPerGameAverage((float)$totalPts, $gm);
            } else {
                $min = $fgp = $ftp = $tgp = $orb = $reb = $ast = $stl = $tvr = $blk = $pf = $pts = '0.0';
            }
            ?>
    <tr>
        <td><?= $year ?></td>
        <td><?= $team ?></td>
        <td><?= $gm ?></td>
        <td><?= $min ?></td>
        <td><?= $fgp ?></td>
        <td><?= $ftp ?></td>
        <td><?= $tgp ?></td>
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

        // Career averages row
        if ($careerAverages) {
            $carFgp = StatsFormatter::formatPercentage((int)$careerAverages['fgm'], (int)$careerAverages['fga']);
            $carFtp = StatsFormatter::formatPercentage((int)$careerAverages['ftm'], (int)$careerAverages['fta']);
            $carTgp = StatsFormatter::formatPercentage((int)$careerAverages['tgm'], (int)$careerAverages['tga']);
            ?>
    <tr class="player-table-row-bold">
        <td colspan=2>Playoff Career</td>
        <td><?= (int)$careerAverages['games'] ?></td>
        <td><?= StatsFormatter::formatWithDecimals((float)$careerAverages['min'], 1) ?></td>
        <td><?= $carFgp ?></td>
        <td><?= $carFtp ?></td>
        <td><?= $carTgp ?></td>
        <td><?= StatsFormatter::formatWithDecimals((float)$careerAverages['orb'], 1) ?></td>
        <td><?= StatsFormatter::formatWithDecimals((float)$careerAverages['reb'], 1) ?></td>
        <td><?= StatsFormatter::formatWithDecimals((float)$careerAverages['ast'], 1) ?></td>
        <td><?= StatsFormatter::formatWithDecimals((float)$careerAverages['stl'], 1) ?></td>
        <td><?= StatsFormatter::formatWithDecimals((float)$careerAverages['tvr'], 1) ?></td>
        <td><?= StatsFormatter::formatWithDecimals((float)$careerAverages['blk'], 1) ?></td>
        <td><?= StatsFormatter::formatWithDecimals((float)$careerAverages['pf'], 1) ?></td>
        <td><?= StatsFormatter::formatWithDecimals((float)$careerAverages['pts'], 1) ?></td>
    </tr>
            <?php
        }
        ?>
</table>
        <?php
        return ob_get_clean();
    }
}
