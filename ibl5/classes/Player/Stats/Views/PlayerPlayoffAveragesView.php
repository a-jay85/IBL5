<?php

declare(strict_types=1);

namespace Player\Stats\Views;

use Player\Stats\PlayerStatsRepository;
use Player\Stats\Contracts\PlayerPlayoffAveragesViewInterface;
use BasketballStats\StatsFormatter;
use Security\HtmlSanitizer;

/**
 * PlayerPlayoffAveragesView - Renders playoff averages table
 *
 * Shows year-by-year playoff statistics averages with career averages row.
 * Uses PlayerStatsRepository for all database access.
 *
 * @see PlayerPlayoffAveragesViewInterface
 */
class PlayerPlayoffAveragesView implements PlayerPlayoffAveragesViewInterface
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
     * @see PlayerPlayoffAveragesViewInterface::renderAverages()
     */
    public function renderAverages(string $playerName): string
    {
        $playoffStats = $this->repository->getPlayoffStats($playerName);
        $careerAverages = $this->repository->getPlayoffCareerAverages($playerName);

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
            /** @var array{pid: int, name: string, games: int, minutes: float, fgm: float, fga: float, fgpct: float, ftm: float, fta: float, ftpct: float, tgm: float, tga: float, tpct: float, orb: float, reb: float, ast: float, stl: float, tvr: float, blk: float, pf: float, pts: float, retired: int} $careerAverages */
            ?>
    <tr class="player-table-row-bold">
        <td colspan=2>Playoff Career</td>
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
}
