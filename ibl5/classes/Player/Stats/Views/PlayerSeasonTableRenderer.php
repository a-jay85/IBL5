<?php

declare(strict_types=1);

namespace Player\Stats\Views;

use Player\Stats\Views\Contracts\PlayerSeasonTableRendererInterface;
use BasketballStats\StatsFormatter;
use Security\HtmlSanitizer;

class PlayerSeasonTableRenderer implements PlayerSeasonTableRendererInterface
{
    /**
     * @see PlayerSeasonTableRendererInterface::render()
     */
    public function render(PlayerSeasonTableConfig $config, array $seasonRows, ?array $careerAverages = null): string
    {
        return match ($config->mode) {
            PlayerSeasonTableMode::AVERAGES => $this->renderAveragesTable($config, $seasonRows, $careerAverages),
            PlayerSeasonTableMode::TOTALS => $this->renderTotalsTable($config, $seasonRows),
        };
    }

    /**
     * @param list<array{year: int, pos: string, pid: int, name: string, team: string, games: int, minutes: int, fgm: int, fga: int, ftm: int, fta: int, tgm: int, tga: int, orb: int, reb: int, ast: int, stl: int, tvr: int, blk: int, pf: int, pts: int}> $seasonRows
     * @param array{pid: int, name: string, games: int, minutes: float, fgm: float, fga: float, fgpct: float, ftm: float, fta: float, ftpct: float, tgm: float, tga: float, tpct: float, orb: float, reb: float, ast: float, stl: float, tvr: float, blk: float, pf: float, pts: float, retired: int, ...<string, mixed>}|null $careerAverages
     */
    private function renderAveragesTable(PlayerSeasonTableConfig $config, array $seasonRows, ?array $careerAverages): string
    {
        ob_start();
        ?>
<table class="sortable player-table">
    <tr>
        <td colspan=<?= $config->getColspan() ?> class="player-table-header"><?= HtmlSanitizer::e($config->title) ?></td>
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
        foreach ($seasonRows as $row) {
            /** @var array{year: int, pos: string, pid: int, name: string, team: string, games: int, minutes: int, fgm: int, fga: int, ftm: int, fta: int, tgm: int, tga: int, orb: int, reb: int, ast: int, stl: int, tvr: int, blk: int, pf: int, pts: int} $row */
            $gm = $row['games'];
            ?>
    <tr>
        <td><?= (int)$row['year'] ?></td>
        <td><?= HtmlSanitizer::e($row['team']) ?></td>
        <td><?= (int)$gm ?></td>
        <td><?= $gm > 0 ? StatsFormatter::formatPerGameAverage($row['minutes'], $gm) : '0.0' ?></td>
        <td><?= $gm > 0 ? StatsFormatter::formatPercentage($row['fgm'], $row['fga']) : '0.0' ?></td>
        <td><?= $gm > 0 ? StatsFormatter::formatPercentage($row['ftm'], $row['fta']) : '0.0' ?></td>
        <td><?= $gm > 0 ? StatsFormatter::formatPercentage($row['tgm'], $row['tga']) : '0.0' ?></td>
        <td><?= $gm > 0 ? StatsFormatter::formatPerGameAverage($row['orb'], $gm) : '0.0' ?></td>
        <td><?= $gm > 0 ? StatsFormatter::formatPerGameAverage($row['reb'], $gm) : '0.0' ?></td>
        <td><?= $gm > 0 ? StatsFormatter::formatPerGameAverage($row['ast'], $gm) : '0.0' ?></td>
        <td><?= $gm > 0 ? StatsFormatter::formatPerGameAverage($row['stl'], $gm) : '0.0' ?></td>
        <td><?= $gm > 0 ? StatsFormatter::formatPerGameAverage($row['tvr'], $gm) : '0.0' ?></td>
        <td><?= $gm > 0 ? StatsFormatter::formatPerGameAverage($row['blk'], $gm) : '0.0' ?></td>
        <td><?= $gm > 0 ? StatsFormatter::formatPerGameAverage($row['pf'], $gm) : '0.0' ?></td>
        <td><?= $gm > 0 ? StatsFormatter::formatPerGameAverage(StatsFormatter::calculatePoints($row['fgm'], $row['ftm'], $row['tgm']), $gm) : '0.0' ?></td>
    </tr>
            <?php
        }

        // Career averages row
        if ($careerAverages !== null) {
            /** @var array{pid: int, name: string, games: int, minutes: float, fgm: float, fga: float, fgpct: float, ftm: float, fta: float, ftpct: float, tgm: float, tga: float, tpct: float, orb: float, reb: float, ast: float, stl: float, tvr: float, blk: float, pf: float, pts: float, retired: int, ...<string, mixed>} $careerAverages */
            ?>
    <tr class="player-table-row-bold">
        <td colspan=2><?= HtmlSanitizer::e($config->careerLabel) ?></td>
        <td><?= (int)$careerAverages['games'] ?></td>
        <td><?= StatsFormatter::formatWithDecimals($careerAverages['minutes'], 1) ?></td>
        <td><?= StatsFormatter::formatPercentage((int) $careerAverages['fgm'], (int) $careerAverages['fga']) ?></td>
        <td><?= StatsFormatter::formatPercentage((int) $careerAverages['ftm'], (int) $careerAverages['fta']) ?></td>
        <td><?= StatsFormatter::formatPercentage((int) $careerAverages['tgm'], (int) $careerAverages['tga']) ?></td>
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

    /**
     * @param list<array{year: int, pos: string, pid: int, name: string, team: string, games: int, minutes: int, fgm: int, fga: int, ftm: int, fta: int, tgm: int, tga: int, orb: int, reb: int, ast: int, stl: int, tvr: int, blk: int, pf: int, pts: int}> $seasonRows
     */
    private function renderTotalsTable(PlayerSeasonTableConfig $config, array $seasonRows): string
    {
        // Initialize career totals
        $carTotals = [
            'gm' => 0, 'min' => 0, 'fgm' => 0, 'fga' => 0, 'ftm' => 0, 'fta' => 0,
            'tgm' => 0, 'tga' => 0, 'orb' => 0, 'reb' => 0, 'ast' => 0, 'stl' => 0,
            'blk' => 0, 'tvr' => 0, 'pf' => 0, 'pts' => 0
        ];

        ob_start();
        ?>
<table class="sortable player-table">
    <tr>
        <td colspan=<?= $config->getColspan() ?> class="player-table-header"><?= HtmlSanitizer::e($config->title) ?></td>
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
        foreach ($seasonRows as $row) {
            /** @var array{year: int, pos: string, pid: int, name: string, team: string, games: int, minutes: int, fgm: int, fga: int, ftm: int, fta: int, tgm: int, tga: int, orb: int, reb: int, ast: int, stl: int, tvr: int, blk: int, pf: int, pts: int} $row */
            $gm = $row['games'];
            $min = $row['minutes'];
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
            $tvr = $row['tvr'];
            $blk = $row['blk'];
            $pf = $row['pf'];
            $pts = $config->recalculatePoints ? ($fgm + $fgm + $ftm + $tgm) : $row['pts'];

            // Accumulate career totals
            $carTotals['gm'] += $gm;
            $carTotals['min'] += $min;
            $carTotals['fgm'] += $fgm;
            $carTotals['fga'] += $fga;
            $carTotals['ftm'] += $ftm;
            $carTotals['fta'] += $fta;
            $carTotals['tgm'] += $tgm;
            $carTotals['tga'] += $tga;
            $carTotals['orb'] += $orb;
            $carTotals['reb'] += $reb;
            $carTotals['ast'] += $ast;
            $carTotals['stl'] += $stl;
            $carTotals['blk'] += $blk;
            $carTotals['tvr'] += $tvr;
            $carTotals['pf'] += $pf;
            $carTotals['pts'] += $pts;
            ?>
    <tr>
        <td><?= (int)$row['year'] ?></td>
        <td><?= HtmlSanitizer::e($row['team']) ?></td>
        <td><?= (int)$gm ?></td>
        <td><?= (int)$min ?></td>
        <td><?= (int)$fgm ?>-<?= (int)$fga ?></td>
        <td><?= (int)$ftm ?>-<?= (int)$fta ?></td>
        <td><?= (int)$tgm ?>-<?= (int)$tga ?></td>
        <td><?= (int)$orb ?></td>
        <td><?= (int)$reb ?></td>
        <td><?= (int)$ast ?></td>
        <td><?= (int)$stl ?></td>
        <td><?= (int)$tvr ?></td>
        <td><?= (int)$blk ?></td>
        <td><?= (int)$pf ?></td>
        <td><?= (int)$pts ?></td>
    </tr>
            <?php
        }
        ?>
    <tr class="player-table-row-bold">
        <td colspan=2><?= HtmlSanitizer::e($config->careerLabel) ?></td>
        <td><?= (int)$carTotals['gm'] ?></td>
        <td><?= (int)$carTotals['min'] ?></td>
        <td><?= (int)$carTotals['fgm'] ?>-<?= (int)$carTotals['fga'] ?></td>
        <td><?= (int)$carTotals['ftm'] ?>-<?= (int)$carTotals['fta'] ?></td>
        <td><?= (int)$carTotals['tgm'] ?>-<?= (int)$carTotals['tga'] ?></td>
        <td><?= (int)$carTotals['orb'] ?></td>
        <td><?= (int)$carTotals['reb'] ?></td>
        <td><?= (int)$carTotals['ast'] ?></td>
        <td><?= (int)$carTotals['stl'] ?></td>
        <td><?= (int)$carTotals['tvr'] ?></td>
        <td><?= (int)$carTotals['blk'] ?></td>
        <td><?= (int)$carTotals['pf'] ?></td>
        <td><?= (int)$carTotals['pts'] ?></td>
    </tr>
</table>
        <?php
        return (string) ob_get_clean();
    }
}
