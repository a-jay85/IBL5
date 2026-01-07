<?php

declare(strict_types=1);

namespace Player\Views;

use Player\Contracts\PlayerPageViewInterface;
use Player\Player;
use Player\PlayerStats;
use Player\PlayerStatsRepository;
use Utilities\HtmlSanitizer;

/**
 * PlayerPlayoffTotalsView - Renders playoff totals
 * 
 * @see PlayerPageViewInterface
 */
class PlayerPlayoffTotalsView implements PlayerPageViewInterface
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
        <td colspan=15 style='font-weight:bold; text-align:center; background-color:#00c; color:#fff;'>Playoff Totals</td>
    </tr>
    <tr>
        <td colspan=15 style='text-align:center; padding: 10px;'>No playoff statistics available.</td>
    </tr>
</table>
            <?php
            return ob_get_clean();
        }
        
        $careerTotals = [
            'games' => 0, 'minutes' => 0, 'fgm' => 0, 'fga' => 0,
            'ftm' => 0, 'fta' => 0, 'tgm' => 0, 'tga' => 0,
            'orb' => 0, 'reb' => 0, 'ast' => 0, 'stl' => 0,
            'blk' => 0, 'tovr' => 0, 'pf' => 0, 'pts' => 0
        ];
        
        ob_start();
        ?>
<table border=1 cellspacing=0 class="sortable" style='margin: 0 auto;'>
    <tr>
        <td colspan=15 style='font-weight:bold; text-align:center; background-color:#00c; color:#fff;'>Playoff Totals</td>
    </tr>
    <tr>
        <th>year</th>
        <th>team</th>
        <th>g</th>
        <th>min</th>
        <th>FGM-FGA</th>
        <th>FTM-FTA</th>
        <th>3GM-3GA</th>
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
            $year = $row['year'];
            $team = $row['team'];
            $games = $row['games'];
            $minutes = $row['minutes'];
            $fgm = $row['fgm'];
            $fga = $row['fga'];
            $ftm = $row['ftm'];
            $fta = $row['fta'];
            $tgm = $row['tgm'];
            $tga = $row['tga'];
            $orb = $row['orb'];
            $reb = $row['reb'];
            $ast = $row['ast'];
            $stl = $row['stl'];
            $tovr = $row['tovr'];
            $blk = $row['blk'];
            $pf = $row['pf'];
            $pts = (2 * $fgm) + $ftm + $tgm;
            
            $careerTotals['games'] += $games;
            $careerTotals['minutes'] += $minutes;
            $careerTotals['fgm'] += $fgm;
            $careerTotals['fga'] += $fga;
            $careerTotals['ftm'] += $ftm;
            $careerTotals['fta'] += $fta;
            $careerTotals['tgm'] += $tgm;
            $careerTotals['tga'] += $tga;
            $careerTotals['orb'] += $orb;
            $careerTotals['reb'] += $reb;
            $careerTotals['ast'] += $ast;
            $careerTotals['stl'] += $stl;
            $careerTotals['tovr'] += $tovr;
            $careerTotals['blk'] += $blk;
            $careerTotals['pf'] += $pf;
            $careerTotals['pts'] += $pts;
            ?>
    <tr>
        <td><center><?= $h::safeHtmlOutput($year) ?></center></td>
        <td><center><?= $h::safeHtmlOutput($team) ?></center></td>
        <td><center><?= $h::safeHtmlOutput($games) ?></center></td>
        <td><center><?= $h::safeHtmlOutput($minutes) ?></center></td>
        <td><center><?= $h::safeHtmlOutput($fgm) ?>-<?= $h::safeHtmlOutput($fga) ?></center></td>
        <td><center><?= $h::safeHtmlOutput($ftm) ?>-<?= $h::safeHtmlOutput($fta) ?></center></td>
        <td><center><?= $h::safeHtmlOutput($tgm) ?>-<?= $h::safeHtmlOutput($tga) ?></center></td>
        <td><center><?= $h::safeHtmlOutput($orb) ?></center></td>
        <td><center><?= $h::safeHtmlOutput($reb) ?></center></td>
        <td><center><?= $h::safeHtmlOutput($ast) ?></center></td>
        <td><center><?= $h::safeHtmlOutput($stl) ?></center></td>
        <td><center><?= $h::safeHtmlOutput($tovr) ?></center></td>
        <td><center><?= $h::safeHtmlOutput($blk) ?></center></td>
        <td><center><?= $h::safeHtmlOutput($pf) ?></center></td>
        <td><center><?= $h::safeHtmlOutput($pts) ?></center></td>
    </tr>
            <?php
        }
        
        $careerPts = (2 * $careerTotals['fgm']) + $careerTotals['ftm'] + $careerTotals['tgm'];
        ?>
    <tr style="font-weight: bold; background-color: #eee;">
        <td><center>Career</center></td>
        <td><center>-</center></td>
        <td><center><?= $h::safeHtmlOutput($careerTotals['games']) ?></center></td>
        <td><center><?= $h::safeHtmlOutput($careerTotals['minutes']) ?></center></td>
        <td><center><?= $h::safeHtmlOutput($careerTotals['fgm']) ?>-<?= $h::safeHtmlOutput($careerTotals['fga']) ?></center></td>
        <td><center><?= $h::safeHtmlOutput($careerTotals['ftm']) ?>-<?= $h::safeHtmlOutput($careerTotals['fta']) ?></center></td>
        <td><center><?= $h::safeHtmlOutput($careerTotals['tgm']) ?>-<?= $h::safeHtmlOutput($careerTotals['tga']) ?></center></td>
        <td><center><?= $h::safeHtmlOutput($careerTotals['orb']) ?></center></td>
        <td><center><?= $h::safeHtmlOutput($careerTotals['reb']) ?></center></td>
        <td><center><?= $h::safeHtmlOutput($careerTotals['ast']) ?></center></td>
        <td><center><?= $h::safeHtmlOutput($careerTotals['stl']) ?></center></td>
        <td><center><?= $h::safeHtmlOutput($careerTotals['tovr']) ?></center></td>
        <td><center><?= $h::safeHtmlOutput($careerTotals['blk']) ?></center></td>
        <td><center><?= $h::safeHtmlOutput($careerTotals['pf']) ?></center></td>
        <td><center><?= $h::safeHtmlOutput($careerPts) ?></center></td>
    </tr>
</table>
        <?php
        return ob_get_clean();
    }
}
