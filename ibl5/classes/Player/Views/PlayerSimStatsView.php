<?php

declare(strict_types=1);

namespace Player\Views;

use Player\Contracts\PlayerPageViewInterface;
use Player\Player;
use Player\PlayerStats;
use Player\PlayerStatsRepository;
use Utilities\HtmlSanitizer;

/**
 * PlayerSimStatsView - Renders sim-by-sim statistics
 * 
 * @see PlayerPageViewInterface
 */
class PlayerSimStatsView implements PlayerPageViewInterface
{
    private Player $player;
    private PlayerStats $playerStats;
    private PlayerStatsRepository $statsRepository;

    public function __construct(
        Player $player,
        PlayerStats $playerStats,
        PlayerStatsRepository $statsRepository
    ) {
        $this->player = $player;
        $this->playerStats = $playerStats;
        $this->statsRepository = $statsRepository;
    }

    /**
     * @see PlayerPageViewInterface::render
     */
    public function render(): string
    {
        $h = HtmlSanitizer::class;
        $simDates = $this->statsRepository->getSimDateRanges(20);
        
        ob_start();
        ?>
<table border=1 cellspacing=0 class="sortable" style='margin: 0 auto;'>
    <tr>
        <td colspan=16 style='font-weight:bold;text-align:center;background-color:#00c;color:#fff;'>Sim Averages</td>
    </tr>
    <tr style="font-weight: bold">
        <th>sim</th>
        <th>g</th>
        <th>min</th>
        <th>FGP</th>
        <th>FTP</th>
        <th>3GP</th>
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
        foreach ($simDates as $simDate) {
            $simNumber = $simDate['Sim'];
            $simStartDate = $simDate['Start Date'];
            $simEndDate = $simDate['End Date'];
            
            $stats = $this->statsRepository->getSimAggregatedStats(
                $this->player->playerID,
                $simStartDate,
                $simEndDate
            );
            
            $games = $stats['games'];
            if ($games === 0) {
                continue;
            }
            
            $fgMade = $stats['fg2Made'] + $stats['fg3Made'];
            $fgAttempted = $stats['fg2Attempted'] + $stats['fg3Attempted'];
            
            $avgMin = number_format($stats['minutes'] / $games, 1);
            $avgFGP = $fgAttempted > 0 ? number_format($fgMade / $fgAttempted, 3, '.', '') : '0.000';
            $avgFTP = $stats['ftAttempted'] > 0 ? number_format($stats['ftMade'] / $stats['ftAttempted'], 3, '.', '') : '0.000';
            $avg3GP = $stats['fg3Attempted'] > 0 ? number_format($stats['fg3Made'] / $stats['fg3Attempted'], 3, '.', '') : '0.000';
            $avgORB = number_format($stats['offRebounds'] / $games, 1);
            $avgREB = number_format(($stats['offRebounds'] + $stats['defRebounds']) / $games, 1);
            $avgAST = number_format($stats['assists'] / $games, 1);
            $avgSTL = number_format($stats['steals'] / $games, 1);
            $avgTO = number_format($stats['turnovers'] / $games, 1);
            $avgBLK = number_format($stats['blocks'] / $games, 1);
            $avgPF = number_format($stats['fouls'] / $games, 1);
            $avgPTS = number_format($stats['points'] / $games, 1);
            ?>
    <tr>
        <td><?= $h::safeHtmlOutput($simNumber) ?></td>
        <td><?= $h::safeHtmlOutput($games) ?></td>
        <td><?= $h::safeHtmlOutput($avgMin) ?></td>
        <td><?= $h::safeHtmlOutput($avgFGP) ?></td>
        <td><?= $h::safeHtmlOutput($avgFTP) ?></td>
        <td><?= $h::safeHtmlOutput($avg3GP) ?></td>
        <td><?= $h::safeHtmlOutput($avgORB) ?></td>
        <td><?= $h::safeHtmlOutput($avgREB) ?></td>
        <td><?= $h::safeHtmlOutput($avgAST) ?></td>
        <td><?= $h::safeHtmlOutput($avgSTL) ?></td>
        <td><?= $h::safeHtmlOutput($avgTO) ?></td>
        <td><?= $h::safeHtmlOutput($avgBLK) ?></td>
        <td><?= $h::safeHtmlOutput($avgPF) ?></td>
        <td><?= $h::safeHtmlOutput($avgPTS) ?></td>
    </tr>
            <?php
        }
        ?>
</table>
        <?php
        return ob_get_clean();
    }
}
