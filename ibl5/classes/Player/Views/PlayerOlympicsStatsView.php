<?php

declare(strict_types=1);

namespace Player\Views;

use Player\PlayerRepository;
use Player\Contracts\PlayerOlympicsStatsViewInterface;
use BasketballStats\StatsFormatter;

/**
 * PlayerOlympicsStatsView - Renders Olympics tournament statistics
 * 
 * Pure rendering with no database logic - all data fetched via PlayerRepository
 * 
 * @see PlayerOlympicsStatsViewInterface
 */
class PlayerOlympicsStatsView implements PlayerOlympicsStatsViewInterface
{
    private PlayerRepository $repository;

    public function __construct(PlayerRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @see PlayerOlympicsStatsViewInterface::renderOlympicsTotals()
     */
    public function renderOlympicsTotals(string $playerName): string
    {
        $olympicsStats = $this->repository->getOlympicsStats($playerName);

        ob_start();
        ?>
<table border=1 cellspacing=1 cellpadding=0>
    <tr>
        <td><center><b><font class="content">Team</font></b></center></td>
        <td><center><b><font class="content">Year</font></b></center></td>
        <td><center><b><font class="content">Games</font></b></center></td>
        <td><center><b><font class="content">Min</font></b></center></td>
        <td><center><b><font class="content">FGM-FGA</font></b></center></td>
        <td><center><b><font class="content">FG%</font></b></center></td>
        <td><center><b><font class="content">FTM-FTA</font></b></center></td>
        <td><center><b><font class="content">FT%</font></b></center></td>
        <td><center><b><font class="content">3GM-3GA</font></b></center></td>
        <td><center><b><font class="content">3G%</font></b></center></td>
        <td><center><b><font class="content">ORB</font></b></center></td>
        <td><center><b><font class="content">DRB</font></b></center></td>
        <td><center><b><font class="content">REB</font></b></center></td>
        <td><center><b><font class="content">AST</font></b></center></td>
        <td><center><b><font class="content">STL</font></b></center></td>
        <td><center><b><font class="content">TO</font></b></center></td>
        <td><center><b><font class="content">BLK</font></b></center></td>
        <td><center><b><font class="content">PF</font></b></center></td>
        <td><center><b><font class="content">PTS</font></b></center></td>
    </tr>
        <?php
        foreach ($olympicsStats as $stats) {
            $team = htmlspecialchars(stripslashes($stats['team']));
            $year = htmlspecialchars($stats['year']);
            $games = htmlspecialchars($stats['games']);
            $minutes = htmlspecialchars($stats['minutes']);
            
            $fgm = $stats['fgm'];
            $fga = $stats['fga'];
            $fgMade = htmlspecialchars($fgm);
            $fgAttempted = htmlspecialchars($fga);
            $fgPercent = StatsFormatter::formatPercentage($fgm, $fga);
            
            $ftm = $stats['ftm'];
            $fta = $stats['fta'];
            $ftMade = htmlspecialchars($ftm);
            $ftAttempted = htmlspecialchars($fta);
            $ftPercent = StatsFormatter::formatPercentage($ftm, $fta);
            
            $tgm = $stats['tgm'];
            $tga = $stats['tga'];
            $tgMade = htmlspecialchars($tgm);
            $tgAttempted = htmlspecialchars($tga);
            $tgPercent = StatsFormatter::formatPercentage($tgm, $tga);
            
            $orb = htmlspecialchars($stats['orb']);
            $drb = htmlspecialchars($stats['drb']);
            $reb = htmlspecialchars($stats['reb']);
            $ast = htmlspecialchars($stats['ast']);
            $stl = htmlspecialchars($stats['stl']);
            $to = htmlspecialchars($stats['tovr']);
            $blk = htmlspecialchars($stats['blk']);
            $pf = htmlspecialchars($stats['pf']);
            $pts = htmlspecialchars($stats['pts']);
            ?>
    <tr align=center>
        <td><?= $team ?></td>
        <td><?= $year ?></td>
        <td><?= $games ?></td>
        <td><?= $minutes ?></td>
        <td><?= $fgMade ?>-<?= $fgAttempted ?></td>
        <td><?= $fgPercent ?></td>
        <td><?= $ftMade ?>-<?= $ftAttempted ?></td>
        <td><?= $ftPercent ?></td>
        <td><?= $tgMade ?>-<?= $tgAttempted ?></td>
        <td><?= $tgPercent ?></td>
        <td><?= $orb ?></td>
        <td><?= $drb ?></td>
        <td><?= $reb ?></td>
        <td><?= $ast ?></td>
        <td><?= $stl ?></td>
        <td><?= $to ?></td>
        <td><?= $blk ?></td>
        <td><?= $pf ?></td>
        <td><?= $pts ?></td>
    </tr>
            <?php
        }
        ?>
</table>
        <?php
        return ob_get_clean();
    }

    /**
     * Render Olympics averages table
     * 
     * @param string $playerName Player name to fetch stats for
     * @return string HTML for Olympics averages table
     */
    public function renderOlympicsAverages(string $playerName): string
    {
        $olympicsStats = $this->repository->getOlympicsStats($playerName);

        ob_start();
        ?>
<table border=1 cellspacing=1 cellpadding=0>
    <tr>
        <td><center><b><font class="content">Team</font></b></center></td>
        <td><center><b><font class="content">Year</font></b></center></td>
        <td><center><b><font class="content">Games</font></b></center></td>
        <td><center><b><font class="content">Min</font></b></center></td>
        <td><center><b><font class="content">FG%</font></b></center></td>
        <td><center><b><font class="content">FT%</font></b></center></td>
        <td><center><b><font class="content">3G%</font></b></center></td>
        <td><center><b><font class="content">ORB</font></b></center></td>
        <td><center><b><font class="content">DRB</font></b></center></td>
        <td><center><b><font class="content">REB</font></b></center></td>
        <td><center><b><font class="content">AST</font></b></center></td>
        <td><center><b><font class="content">STL</font></b></center></td>
        <td><center><b><font class="content">TO</font></b></center></td>
        <td><center><b><font class="content">BLK</font></b></center></td>
        <td><center><b><font class="content">PF</font></b></center></td>
        <td><center><b><font class="content">PTS</font></b></center></td>
    </tr>
        <?php
        foreach ($olympicsStats as $stats) {
            $games = $stats['games'];
            if ($games == 0) {
                continue;
            }

            $team = htmlspecialchars(stripslashes($stats['team']));
            $year = htmlspecialchars($stats['year']);
            $gamesDisplay = htmlspecialchars($games);
            
            $avgMinutes = StatsFormatter::formatAverage($stats['minutes'], $games);
            $fgPercent = StatsFormatter::formatPercentage($stats['fgm'], $stats['fga']);
            $ftPercent = StatsFormatter::formatPercentage($stats['ftm'], $stats['fta']);
            $tgPercent = StatsFormatter::formatPercentage($stats['tgm'], $stats['tga']);
            $avgOrb = StatsFormatter::formatAverage($stats['orb'], $games);
            $avgDrb = StatsFormatter::formatAverage($stats['drb'], $games);
            $avgReb = StatsFormatter::formatAverage($stats['reb'], $games);
            $avgAst = StatsFormatter::formatAverage($stats['ast'], $games);
            $avgStl = StatsFormatter::formatAverage($stats['stl'], $games);
            $avgTo = StatsFormatter::formatAverage($stats['tovr'], $games);
            $avgBlk = StatsFormatter::formatAverage($stats['blk'], $games);
            $avgPf = StatsFormatter::formatAverage($stats['pf'], $games);
            $avgPts = StatsFormatter::formatAverage($stats['pts'], $games);
            ?>
    <tr align=center>
        <td><?= $team ?></td>
        <td><?= $year ?></td>
        <td><?= $gamesDisplay ?></td>
        <td><?= $avgMinutes ?></td>
        <td><?= $fgPercent ?></td>
        <td><?= $ftPercent ?></td>
        <td><?= $tgPercent ?></td>
        <td><?= $avgOrb ?></td>
        <td><?= $avgDrb ?></td>
        <td><?= $avgReb ?></td>
        <td><?= $avgAst ?></td>
        <td><?= $avgStl ?></td>
        <td><?= $avgTo ?></td>
        <td><?= $avgBlk ?></td>
        <td><?= $avgPf ?></td>
        <td><?= $avgPts ?></td>
    </tr>
            <?php
        }
        ?>
</table>
        <?php
        return ob_get_clean();
    }
}
