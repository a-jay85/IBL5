<?php

declare(strict_types=1);

namespace Player\Views;

use Player\PlayerRepository;
use Player\Contracts\PlayerPlayoffStatsViewInterface;
use BasketballStats\StatsFormatter;
use Utilities\HtmlSanitizer;

/**
 * PlayerPlayoffStatsView - Renders playoff statistics (totals/averages)
 * 
 * Pure rendering with no database logic - all data fetched via PlayerRepository
 * 
 * @see PlayerPlayoffStatsViewInterface
 */
class PlayerPlayoffStatsView implements PlayerPlayoffStatsViewInterface
{
    private PlayerRepository $repository;

    public function __construct(PlayerRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @see PlayerPlayoffStatsViewInterface::renderPlayoffTotals()
     */
    public function renderPlayoffTotals(string $playerName): string
    {
        $playoffStats = $this->repository->getPlayoffStats($playerName);

        ob_start();
        ?>
<table class="stats-table">
    <tr>
        <td class="content-header">Team</td>
        <td class="content-header">Year</td>
        <td class="content-header">Games</td>
        <td class="content-header">Min</td>
        <td class="content-header">FGM-FGA</td>
        <td class="content-header">FG%</td>
        <td class="content-header">FTM-FTA</td>
        <td class="content-header">FT%</td>
        <td class="content-header">3GM-3GA</td>
        <td class="content-header">3G%</td>
        <td class="content-header">ORB</td>
        <td class="content-header">DRB</td>
        <td class="content-header">REB</td>
        <td class="content-header">AST</td>
        <td class="content-header">STL</td>
        <td class="content-header">TO</td>
        <td class="content-header">BLK</td>
        <td class="content-header">PF</td>
        <td class="content-header">PTS</td>
    </tr>
        <?php
        foreach ($playoffStats as $stats) {
            $team = HtmlSanitizer::safeHtmlOutput($stats['team']);
            $year = HtmlSanitizer::safeHtmlOutput($stats['year']);
            $games = HtmlSanitizer::safeHtmlOutput($stats['games']);
            $minutes = HtmlSanitizer::safeHtmlOutput($stats['minutes']);
            
            $fgm = $stats['fgm'];
            $fga = $stats['fga'];
            $fgMade = HtmlSanitizer::safeHtmlOutput($fgm);
            $fgAttempted = HtmlSanitizer::safeHtmlOutput($fga);
            $fgPercent = StatsFormatter::formatPercentage($fgm, $fga);
            
            $ftm = $stats['ftm'];
            $fta = $stats['fta'];
            $ftMade = HtmlSanitizer::safeHtmlOutput($ftm);
            $ftAttempted = HtmlSanitizer::safeHtmlOutput($fta);
            $ftPercent = StatsFormatter::formatPercentage($ftm, $fta);
            
            $tgm = $stats['tgm'];
            $tga = $stats['tga'];
            $tgMade = HtmlSanitizer::safeHtmlOutput($tgm);
            $tgAttempted = HtmlSanitizer::safeHtmlOutput($tga);
            $tgPercent = StatsFormatter::formatPercentage($tgm, $tga);
            
            $orb = HtmlSanitizer::safeHtmlOutput($stats['orb']);
            $drb = HtmlSanitizer::safeHtmlOutput($stats['drb']);
            $reb = HtmlSanitizer::safeHtmlOutput($stats['reb']);
            $ast = HtmlSanitizer::safeHtmlOutput($stats['ast']);
            $stl = HtmlSanitizer::safeHtmlOutput($stats['stl']);
            $to = HtmlSanitizer::safeHtmlOutput($stats['tovr']);
            $blk = HtmlSanitizer::safeHtmlOutput($stats['blk']);
            $pf = HtmlSanitizer::safeHtmlOutput($stats['pf']);
            $pts = HtmlSanitizer::safeHtmlOutput($stats['pts']);
            ?>
    <tr>
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
     * Render playoff averages table
     * 
     * @param string $playerName Player name to fetch stats for
     * @return string HTML for playoff averages table
     */
    public function renderPlayoffAverages(string $playerName): string
    {
        $playoffStats = $this->repository->getPlayoffStats($playerName);

        ob_start();
        ?>
<table class="stats-table">
    <tr>
        <td class="content-header">Team</td>
        <td class="content-header">Year</td>
        <td class="content-header">Games</td>
        <td class="content-header">Min</td>
        <td class="content-header">FG%</td>
        <td class="content-header">FT%</td>
        <td class="content-header">3G%</td>
        <td class="content-header">ORB</td>
        <td class="content-header">DRB</td>
        <td class="content-header">REB</td>
        <td class="content-header">AST</td>
        <td class="content-header">STL</td>
        <td class="content-header">TO</td>
        <td class="content-header">BLK</td>
        <td class="content-header">PF</td>
        <td class="content-header">PTS</td>
    </tr>
        <?php
        foreach ($playoffStats as $stats) {
            $games = $stats['games'];
            if ($games == 0) {
                continue;
            }

            $team = HtmlSanitizer::safeHtmlOutput($stats['team']);
            $year = HtmlSanitizer::safeHtmlOutput($stats['year']);
            $gamesDisplay = HtmlSanitizer::safeHtmlOutput($games);
            
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
    <tr>
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
