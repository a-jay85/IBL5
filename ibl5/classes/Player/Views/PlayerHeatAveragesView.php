<?php

declare(strict_types=1);

namespace Player\Views;

use Player\PlayerRepository;
use Player\PlayerStatsRepository;
use Player\Contracts\PlayerHeatAveragesViewInterface;
use BasketballStats\StatsFormatter;
use Utilities\HtmlSanitizer;

/**
 * PlayerHeatAveragesView - Renders H.E.A.T. averages table
 *
 * Shows year-by-year H.E.A.T. statistics averages with career averages row.
 * Uses PlayerRepository and PlayerStatsRepository for all database access.
 *
 * @see PlayerHeatAveragesViewInterface
 */
class PlayerHeatAveragesView implements PlayerHeatAveragesViewInterface
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
     * @see PlayerHeatAveragesViewInterface::renderAverages()
     */
    public function renderAverages(string $playerName): string
    {
        $heatStats = $this->repository->getHeatStats($playerName);
        $careerAverages = $this->statsRepository->getHeatCareerAverages($playerName);

        ob_start();
        ?>
<table class="sortable player-table">
    <tr>
        <td colspan=16 class="player-table-header">H.E.A.T. Averages</td>
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
        foreach ($heatStats as $row) {
            /** @var array{year: int, pos: string, pid: int, name: string, team: string, games: int, minutes: int, fgm: int, fga: int, ftm: int, fta: int, tgm: int, tga: int, orb: int, reb: int, ast: int, stl: int, tvr: int, blk: int, pf: int, pts: int} $row */
            $year = $row['year'];
            $team = HtmlSanitizer::safeHtmlOutput($row['team']);
            $gm = $row['games'];

            if ($gm > 0) {
                $min = StatsFormatter::formatPerGameAverage($row['minutes'], $gm);
                $fgp = StatsFormatter::formatPercentage($row['fgm'], $row['fga']);
                $ftp = StatsFormatter::formatPercentage($row['ftm'], $row['fta']);
                $tgp = StatsFormatter::formatPercentage($row['tgm'], $row['tga']);
                $orb = StatsFormatter::formatPerGameAverage($row['orb'], $gm);
                $reb = StatsFormatter::formatPerGameAverage($row['reb'], $gm);
                $ast = StatsFormatter::formatPerGameAverage($row['ast'], $gm);
                $stl = StatsFormatter::formatPerGameAverage($row['stl'], $gm);
                $tvr = StatsFormatter::formatPerGameAverage($row['tvr'], $gm);
                $blk = StatsFormatter::formatPerGameAverage($row['blk'], $gm);
                $pf = StatsFormatter::formatPerGameAverage($row['pf'], $gm);
                // Calculate points: pts = 2*fgm + ftm + tgm
                $totalPts = StatsFormatter::calculatePoints($row['fgm'], $row['ftm'], $row['tgm']);
                $pts = StatsFormatter::formatPerGameAverage($totalPts, $gm);
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
        if ($careerAverages !== null) {
            /** @var array{pid: int, name: string, games: int, minutes: float, fgm: float, fga: float, fgpct: float, ftm: float, fta: float, ftpct: float, tgm: float, tga: float, tpct: float, orb: float, reb: float, ast: float, stl: float, tvr: float, blk: float, pf: float, pts: float, retired: int} $careerAverages */
            $carFgp = StatsFormatter::formatPercentage((int) $careerAverages['fgm'], (int) $careerAverages['fga']);
            $carFtp = StatsFormatter::formatPercentage((int) $careerAverages['ftm'], (int) $careerAverages['fta']);
            $carTgp = StatsFormatter::formatPercentage((int) $careerAverages['tgm'], (int) $careerAverages['tga']);
            ?>
    <tr class="player-table-row-bold">
        <td colspan=2>H.E.A.T. Career</td>
        <td><?= $careerAverages['games'] ?></td>
        <td><?= StatsFormatter::formatWithDecimals($careerAverages['minutes'], 1) ?></td>
        <td><?= $carFgp ?></td>
        <td><?= $carFtp ?></td>
        <td><?= $carTgp ?></td>
        <td><?= StatsFormatter::formatWithDecimals($careerAverages['orb'], 1) ?></td>
        <td><?= StatsFormatter::formatWithDecimals($careerAverages['reb'], 1) ?></td>
        <td><?= StatsFormatter::formatWithDecimals($careerAverages['ast'], 1) ?></td>
        <td><?= StatsFormatter::formatWithDecimals($careerAverages['stl'], 1) ?></td>
        <td><?= StatsFormatter::formatWithDecimals($careerAverages['tvr'], 1) ?></td>
        <td><?= StatsFormatter::formatWithDecimals($careerAverages['blk'], 1) ?></td>
        <td><?= StatsFormatter::formatWithDecimals($careerAverages['pf'], 1) ?></td>
        <td><?= StatsFormatter::formatWithDecimals($careerAverages['pts'], 1) ?></td>
    </tr>
            <?php
        }
        ?>
</table>
        <?php
        return (string) ob_get_clean();
    }
}
