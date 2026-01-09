<?php

declare(strict_types=1);

namespace Player\Views;

use Player\PlayerStatsRepository;
use Player\Contracts\PlayerRegularSeasonAveragesViewInterface;
use BasketballStats\StatsFormatter;
use Utilities\HtmlSanitizer;

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
<table class="sortable player-table">
    <tr>
        <td colspan=21 class="player-table-header">Regular Season Averages</td>
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
            $team = HtmlSanitizer::safeHtmlOutput($row['team']);
            $teamId = (int)$row['teamid'];
            $gm = (int)$row['games'];
            
            if ($gm > 0) {
                // Calculate points if pts is 0 (e.g., 2006 season)
                // Formula: 2*fgm + ftm + tgm
                $ptsTotal = (int)$row['pts'];
                if ($ptsTotal === 0) {
                    $ptsTotal = (2 * (int)$row['fgm']) + (int)$row['ftm'] + (int)$row['tgm'];
                }
                
                $min = StatsFormatter::formatPerGameAverage((float)$row['minutes'], $gm);
                $fgm = StatsFormatter::formatPerGameAverage((float)$row['fgm'], $gm);
                $fga = StatsFormatter::formatPerGameAverage((float)$row['fga'], $gm);
                $fgp = StatsFormatter::formatPercentageWithDecimals((int)$row['fgm'], (int)$row['fga']);
                $ftm = StatsFormatter::formatPerGameAverage((float)$row['ftm'], $gm);
                $fta = StatsFormatter::formatPerGameAverage((float)$row['fta'], $gm);
                $ftp = StatsFormatter::formatPercentageWithDecimals((int)$row['ftm'], (int)$row['fta']);
                $tgm = StatsFormatter::formatPerGameAverage((float)$row['tgm'], $gm);
                $tga = StatsFormatter::formatPerGameAverage((float)$row['tga'], $gm);
                $tgp = StatsFormatter::formatPercentageWithDecimals((int)$row['tgm'], (int)$row['tga']);
                $orb = StatsFormatter::formatPerGameAverage((float)$row['orb'], $gm);
                $reb = StatsFormatter::formatPerGameAverage((float)$row['reb'], $gm);
                $ast = StatsFormatter::formatPerGameAverage((float)$row['ast'], $gm);
                $stl = StatsFormatter::formatPerGameAverage((float)$row['stl'], $gm);
                $tvr = StatsFormatter::formatPerGameAverage((float)$row['tvr'], $gm);
                $blk = StatsFormatter::formatPerGameAverage((float)$row['blk'], $gm);
                $pf = StatsFormatter::formatPerGameAverage((float)$row['pf'], $gm);
                $pts = StatsFormatter::formatPerGameAverage((float)$ptsTotal, $gm);
            } else {
                $min = $fgm = $fga = $fgp = $ftm = $fta = $ftp = $tgm = $tga = $tgp = '0.0';
                $orb = $reb = $ast = $stl = $tvr = $blk = $pf = $pts = '0.0';
            }
            ?>
    <tr>
        <td><?= $year ?></td>
        <td><a href="modules.php?name=Team&op=team&teamID=<?= $teamId ?>&yr=<?= $year ?>"><?= $team ?></a></td>
        <td><?= $gm ?></td>
        <td><?= $min ?></td>
        <td><?= $fgm ?></td>
        <td><?= $fga ?></td>
        <td><?= $fgp ?></td>
        <td><?= $ftm ?></td>
        <td><?= $fta ?></td>
        <td><?= $ftp ?></td>
        <td><?= $tgm ?></td>
        <td><?= $tga ?></td>
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
            $carFgp = StatsFormatter::formatPercentageWithDecimals((int)round((float)$careerAverages['fgm']), (int)round((float)$careerAverages['fga']));
            $carFtp = StatsFormatter::formatPercentageWithDecimals((int)round((float)$careerAverages['ftm']), (int)round((float)$careerAverages['fta']));
            $carTgp = StatsFormatter::formatPercentageWithDecimals((int)round((float)$careerAverages['tgm']), (int)round((float)$careerAverages['tga']));
            ?>
    <tr class="player-table-row-bold">
        <td colspan=2>Career</td>
        <td><?= (int)$careerAverages['games'] ?></td>
        <td><?= StatsFormatter::formatWithDecimals((float)$careerAverages['minutes'], 1) ?></td>
        <td><?= StatsFormatter::formatWithDecimals((float)$careerAverages['fgm'], 1) ?></td>
        <td><?= StatsFormatter::formatWithDecimals((float)$careerAverages['fga'], 1) ?></td>
        <td><?= $carFgp ?></td>
        <td><?= StatsFormatter::formatWithDecimals((float)$careerAverages['ftm'], 1) ?></td>
        <td><?= StatsFormatter::formatWithDecimals((float)$careerAverages['fta'], 1) ?></td>
        <td><?= $carFtp ?></td>
        <td><?= StatsFormatter::formatWithDecimals((float)$careerAverages['tgm'], 1) ?></td>
        <td><?= StatsFormatter::formatWithDecimals((float)$careerAverages['tga'], 1) ?></td>
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
