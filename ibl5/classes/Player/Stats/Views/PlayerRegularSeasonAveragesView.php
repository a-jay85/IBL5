<?php

declare(strict_types=1);

namespace Player\Stats\Views;

use Player\Stats\PlayerStatsRepository;
use Player\Stats\Contracts\PlayerRegularSeasonAveragesViewInterface;
use BasketballStats\StatsFormatter;
use Security\HtmlSanitizer;

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

        $careerAverages = $this->repository->getSeasonCareerAveragesById($playerID)
            ?? self::computeCareerAveragesFromHistory($historicalStats);

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
            $gm = $row['games'];

            // Calculate points if pts is 0 (e.g., 2006 season)
            // Formula: 2*fgm + ftm + tgm
            $ptsTotal = $row['pts'];
            if ($ptsTotal === 0) {
                $ptsTotal = (2 * $row['fgm']) + $row['ftm'] + $row['tgm'];
            }
            ?>
    <tr>
        <td><?= (int)$row['year'] ?></td>
        <td><a href="modules.php?name=Team&op=team&teamid=<?= (int)$row['teamid'] ?>&yr=<?= (int)$row['year'] ?>"><?= HtmlSanitizer::e($row['team']) ?></a></td>
        <td><?= (int)$gm ?></td>
        <td><?= $gm > 0 ? StatsFormatter::formatPerGameAverage((float)$row['minutes'], $gm) : '0.0' ?></td>
        <td><?= $gm > 0 ? StatsFormatter::formatPerGameAverage((float)$row['fgm'], $gm) : '0.0' ?></td>
        <td><?= $gm > 0 ? StatsFormatter::formatPerGameAverage((float)$row['fga'], $gm) : '0.0' ?></td>
        <td><?= $gm > 0 ? StatsFormatter::formatPercentageWithDecimals($row['fgm'], $row['fga']) : '0.0' ?></td>
        <td><?= $gm > 0 ? StatsFormatter::formatPerGameAverage((float)$row['ftm'], $gm) : '0.0' ?></td>
        <td><?= $gm > 0 ? StatsFormatter::formatPerGameAverage((float)$row['fta'], $gm) : '0.0' ?></td>
        <td><?= $gm > 0 ? StatsFormatter::formatPercentageWithDecimals($row['ftm'], $row['fta']) : '0.0' ?></td>
        <td><?= $gm > 0 ? StatsFormatter::formatPerGameAverage((float)$row['tgm'], $gm) : '0.0' ?></td>
        <td><?= $gm > 0 ? StatsFormatter::formatPerGameAverage((float)$row['tga'], $gm) : '0.0' ?></td>
        <td><?= $gm > 0 ? StatsFormatter::formatPercentageWithDecimals($row['tgm'], $row['tga']) : '0.0' ?></td>
        <td><?= $gm > 0 ? StatsFormatter::formatPerGameAverage((float)$row['orb'], $gm) : '0.0' ?></td>
        <td><?= $gm > 0 ? StatsFormatter::formatPerGameAverage((float)$row['reb'], $gm) : '0.0' ?></td>
        <td><?= $gm > 0 ? StatsFormatter::formatPerGameAverage((float)$row['ast'], $gm) : '0.0' ?></td>
        <td><?= $gm > 0 ? StatsFormatter::formatPerGameAverage((float)$row['stl'], $gm) : '0.0' ?></td>
        <td><?= $gm > 0 ? StatsFormatter::formatPerGameAverage((float)$row['tvr'], $gm) : '0.0' ?></td>
        <td><?= $gm > 0 ? StatsFormatter::formatPerGameAverage((float)$row['blk'], $gm) : '0.0' ?></td>
        <td><?= $gm > 0 ? StatsFormatter::formatPerGameAverage((float)$row['pf'], $gm) : '0.0' ?></td>
        <td><?= $gm > 0 ? StatsFormatter::formatPerGameAverage((float)$ptsTotal, $gm) : '0.0' ?></td>
    </tr>
            <?php
        }

        // Career averages row
        if ($careerAverages !== null) {
            ?>
    <tr class="player-table-row-bold">
        <td colspan=2>Career</td>
        <td><?= (int)$careerAverages['games'] ?></td>
        <td><?= StatsFormatter::formatWithDecimals((float)$careerAverages['minutes'], 1) ?></td>
        <td><?= StatsFormatter::formatWithDecimals((float)$careerAverages['fgm'], 1) ?></td>
        <td><?= StatsFormatter::formatWithDecimals((float)$careerAverages['fga'], 1) ?></td>
        <td><?= StatsFormatter::formatPercentageWithDecimals((float)$careerAverages['fgm'], (float)$careerAverages['fga']) ?></td>
        <td><?= StatsFormatter::formatWithDecimals((float)$careerAverages['ftm'], 1) ?></td>
        <td><?= StatsFormatter::formatWithDecimals((float)$careerAverages['fta'], 1) ?></td>
        <td><?= StatsFormatter::formatPercentageWithDecimals((float)$careerAverages['ftm'], (float)$careerAverages['fta']) ?></td>
        <td><?= StatsFormatter::formatWithDecimals((float)$careerAverages['tgm'], 1) ?></td>
        <td><?= StatsFormatter::formatWithDecimals((float)$careerAverages['tga'], 1) ?></td>
        <td><?= StatsFormatter::formatPercentageWithDecimals((float)$careerAverages['tgm'], (float)$careerAverages['tga']) ?></td>
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
        return (string) ob_get_clean();
    }

    /**
     * Compute career averages from per-season historical totals.
     *
     * Produces the same shape as PlayerStatsRepository::getSeasonCareerAveragesById()
     * without querying ibl_box_scores. Used when ibl_plr_snapshots is empty and the
     * ibl_hist VIEW would be needlessly expensive.
     *
     * @param list<array{pid: int, name: string, games: int, minutes: int, fgm: int, fga: int, ftm: int, fta: int, tgm: int, tga: int, orb: int, reb: int, ast: int, stl: int, blk: int, tvr: int, pf: int, pts: int, ...}> $historicalStats
     * @return array{pid: int, name: string, games: int, minutes: float, fgm: float, fga: float, fgpct: float, ftm: float, fta: float, ftpct: float, tgm: float, tga: float, tpct: float, orb: float, reb: float, ast: float, stl: float, tvr: float, blk: float, pf: float, pts: float, retired: int}|null
     */
    private static function computeCareerAveragesFromHistory(array $historicalStats): ?array
    {
        if ($historicalStats === []) {
            return null;
        }

        $totalGames = 0;
        $minutes = 0;
        $fgm = 0;
        $fga = 0;
        $ftm = 0;
        $fta = 0;
        $tgm = 0;
        $tga = 0;
        $orb = 0;
        $reb = 0;
        $ast = 0;
        $stl = 0;
        $tvr = 0;
        $blk = 0;
        $pf = 0;
        $pts = 0;

        foreach ($historicalStats as $row) {
            $totalGames += $row['games'];
            $minutes += $row['minutes'];
            $fgm += $row['fgm'];
            $fga += $row['fga'];
            $ftm += $row['ftm'];
            $fta += $row['fta'];
            $tgm += $row['tgm'];
            $tga += $row['tga'];
            $orb += $row['orb'];
            $reb += $row['reb'];
            $ast += $row['ast'];
            $stl += $row['stl'];
            $tvr += $row['tvr'];
            $blk += $row['blk'];
            $pf += $row['pf'];

            // Correct pts=0 rows (e.g. 2006 season) the same way the view does
            $rowPts = $row['pts'];
            if ($rowPts === 0) {
                $rowPts = (2 * $row['fgm']) + $row['ftm'] + $row['tgm'];
            }
            $pts += $rowPts;
        }

        if ($totalGames === 0) {
            return null;
        }

        return [
            'pid' => $historicalStats[0]['pid'],
            'name' => $historicalStats[0]['name'],
            'games' => $totalGames,
            'minutes' => round($minutes / $totalGames, 2),
            'fgm' => round($fgm / $totalGames, 2),
            'fga' => round($fga / $totalGames, 2),
            'fgpct' => $fga > 0 ? round($fgm / $fga, 3) : 0.0,
            'ftm' => round($ftm / $totalGames, 2),
            'fta' => round($fta / $totalGames, 2),
            'ftpct' => $fta > 0 ? round($ftm / $fta, 3) : 0.0,
            'tgm' => round($tgm / $totalGames, 2),
            'tga' => round($tga / $totalGames, 2),
            'tpct' => $tga > 0 ? round($tgm / $tga, 3) : 0.0,
            'orb' => round($orb / $totalGames, 2),
            'reb' => round($reb / $totalGames, 2),
            'ast' => round($ast / $totalGames, 2),
            'stl' => round($stl / $totalGames, 2),
            'tvr' => round($tvr / $totalGames, 2),
            'blk' => round($blk / $totalGames, 2),
            'pf' => round($pf / $totalGames, 2),
            'pts' => round($pts / $totalGames, 2),
            'retired' => 0,
        ];
    }
}
