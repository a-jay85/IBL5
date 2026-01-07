<?php

declare(strict_types=1);

namespace Player\Views;

use Player\Contracts\PlayerPageViewInterface;
use Player\Player;
use Player\PlayerStats;
use Player\PlayerStatsRepository;
use Utilities\HtmlSanitizer;

/**
 * PlayerPlayoffAveragesView - Renders playoff averages
 * 
 * @see PlayerPageViewInterface
 */
class PlayerPlayoffAveragesView implements PlayerPageViewInterface
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
        $historicalStats = $this->statsRepository->getHistoricalStats($this->player->playerID, 'playoff');
        
        if (empty($historicalStats)) {
            ob_start();
            ?>
<table border=1 cellspacing=0 style='margin: 0 auto;'>
    <tr>
        <td colspan=17 style='font-weight:bold; text-align:center; background-color:#00c; color:#fff;'>Playoff Averages</td>
    </tr>
    <tr>
        <td colspan=17 style='text-align:center; padding: 10px;'>No playoff statistics available.</td>
    </tr>
</table>
            <?php
            return ob_get_clean();
        }
        
        $careerTotals = [
            'games' => 0, 'minutes' => 0, 'fgm' => 0, 'fga' => 0,
            'ftm' => 0, 'fta' => 0, 'tgm' => 0, 'tga' => 0,
            'reb' => 0, 'ast' => 0, 'stl' => 0, 'blk' => 0, 'pts' => 0
        ];
        
        ob_start();
        ?>
<table border=1 cellspacing=0 class="sortable" style='margin: 0 auto;'>
    <tr>
        <td colspan=17 style='font-weight:bold; text-align:center; background-color:#00c; color:#fff;'>Playoff Averages</td>
    </tr>
    <tr>
        <th>year</th>
        <th>team</th>
        <th>g</th>
        <th>min</th>
        <th>fgm</th>
        <th>fga</th>
        <th>fg%</th>
        <th>ftm</th>
        <th>fta</th>
        <th>ft%</th>
        <th>3pm</th>
        <th>3pa</th>
        <th>reb</th>
        <th>ast</th>
        <th>stl</th>
        <th>blk</th>
        <th>pts</th>
    </tr>
        <?php
        foreach ($historicalStats as $row) {
            $year = $row['year'];
            $team = $row['team'];
            $games = $row['games'] > 0 ? $row['games'] : 1;
            $gamesDisplay = $row['games'];
            
            $minutes = $row['minutes'];
            $fgm = $row['fgm'];
            $fga = $row['fga'];
            $ftm = $row['ftm'];
            $fta = $row['fta'];
            $tgm = $row['tgm'];
            $tga = $row['tga'];
            $reb = $row['reb'];
            $ast = $row['ast'];
            $stl = $row['stl'];
            $blk = $row['blk'];
            $pts = (2 * $fgm) + $ftm + $tgm;
            
            $careerTotals['games'] += $row['games'];
            $careerTotals['minutes'] += $minutes;
            $careerTotals['fgm'] += $fgm;
            $careerTotals['fga'] += $fga;
            $careerTotals['ftm'] += $ftm;
            $careerTotals['fta'] += $fta;
            $careerTotals['tgm'] += $tgm;
            $careerTotals['tga'] += $tga;
            $careerTotals['reb'] += $reb;
            $careerTotals['ast'] += $ast;
            $careerTotals['stl'] += $stl;
            $careerTotals['blk'] += $blk;
            $careerTotals['pts'] += $pts;
            
            $fgPct = $fga > 0 ? number_format(($fgm / $fga) * 100, 1) : '0.0';
            $ftPct = $fta > 0 ? number_format(($ftm / $fta) * 100, 1) : '0.0';
            ?>
    <tr>
        <td><center><?= $h::safeHtmlOutput($year) ?></center></td>
        <td><center><?= $h::safeHtmlOutput($team) ?></center></td>
        <td><center><?= $h::safeHtmlOutput($gamesDisplay) ?></center></td>
        <td><center><?= $h::safeHtmlOutput(number_format($minutes / $games, 1)) ?></center></td>
        <td><center><?= $h::safeHtmlOutput(number_format($fgm / $games, 1)) ?></center></td>
        <td><center><?= $h::safeHtmlOutput(number_format($fga / $games, 1)) ?></center></td>
        <td><center><?= $h::safeHtmlOutput($fgPct) ?></center></td>
        <td><center><?= $h::safeHtmlOutput(number_format($ftm / $games, 1)) ?></center></td>
        <td><center><?= $h::safeHtmlOutput(number_format($fta / $games, 1)) ?></center></td>
        <td><center><?= $h::safeHtmlOutput($ftPct) ?></center></td>
        <td><center><?= $h::safeHtmlOutput(number_format($tgm / $games, 1)) ?></center></td>
        <td><center><?= $h::safeHtmlOutput(number_format($tga / $games, 1)) ?></center></td>
        <td><center><?= $h::safeHtmlOutput(number_format($reb / $games, 1)) ?></center></td>
        <td><center><?= $h::safeHtmlOutput(number_format($ast / $games, 1)) ?></center></td>
        <td><center><?= $h::safeHtmlOutput(number_format($stl / $games, 1)) ?></center></td>
        <td><center><?= $h::safeHtmlOutput(number_format($blk / $games, 1)) ?></center></td>
        <td><center><?= $h::safeHtmlOutput(number_format($pts / $games, 1)) ?></center></td>
    </tr>
            <?php
        }
        
        $careerGames = $careerTotals['games'] > 0 ? $careerTotals['games'] : 1;
        $careerFgPct = $careerTotals['fga'] > 0 ? number_format(($careerTotals['fgm'] / $careerTotals['fga']) * 100, 1) : '0.0';
        $careerFtPct = $careerTotals['fta'] > 0 ? number_format(($careerTotals['ftm'] / $careerTotals['fta']) * 100, 1) : '0.0';
        ?>
    <tr style="font-weight: bold; background-color: #eee;">
        <td><center>Career</center></td>
        <td><center>-</center></td>
        <td><center><?= $h::safeHtmlOutput($careerTotals['games']) ?></center></td>
        <td><center><?= $h::safeHtmlOutput(number_format($careerTotals['minutes'] / $careerGames, 1)) ?></center></td>
        <td><center><?= $h::safeHtmlOutput(number_format($careerTotals['fgm'] / $careerGames, 1)) ?></center></td>
        <td><center><?= $h::safeHtmlOutput(number_format($careerTotals['fga'] / $careerGames, 1)) ?></center></td>
        <td><center><?= $h::safeHtmlOutput($careerFgPct) ?></center></td>
        <td><center><?= $h::safeHtmlOutput(number_format($careerTotals['ftm'] / $careerGames, 1)) ?></center></td>
        <td><center><?= $h::safeHtmlOutput(number_format($careerTotals['fta'] / $careerGames, 1)) ?></center></td>
        <td><center><?= $h::safeHtmlOutput($careerFtPct) ?></center></td>
        <td><center><?= $h::safeHtmlOutput(number_format($careerTotals['tgm'] / $careerGames, 1)) ?></center></td>
        <td><center><?= $h::safeHtmlOutput(number_format($careerTotals['tga'] / $careerGames, 1)) ?></center></td>
        <td><center><?= $h::safeHtmlOutput(number_format($careerTotals['reb'] / $careerGames, 1)) ?></center></td>
        <td><center><?= $h::safeHtmlOutput(number_format($careerTotals['ast'] / $careerGames, 1)) ?></center></td>
        <td><center><?= $h::safeHtmlOutput(number_format($careerTotals['stl'] / $careerGames, 1)) ?></center></td>
        <td><center><?= $h::safeHtmlOutput(number_format($careerTotals['blk'] / $careerGames, 1)) ?></center></td>
        <td><center><?= $h::safeHtmlOutput(number_format($careerTotals['pts'] / $careerGames, 1)) ?></center></td>
    </tr>
</table>
        <?php
        return ob_get_clean();
    }
}
