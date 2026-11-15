<?php

declare(strict_types=1);

namespace Player\Stats\Views;

use Player\Stats\PlayerStatsRepository;
use Player\Stats\Contracts\PlayerRegularSeasonAveragesViewInterface;
use Player\Stats\Views\Contracts\PlayerSeasonTableRendererInterface;

/**
 * PlayerRegularSeasonAveragesView - Renders regular season averages table
 * 
 * Shows season-by-season averages with career averages row.
 * Uses PlayerStatsRepository for all database access.
 * 
 * @see PlayerRegularSeasonAveragesViewInterface
 *
 * @phpstan-import-type StatsRow from \Player\Stats\Contracts\PlayerStatsRepositoryInterface
 */
class PlayerRegularSeasonAveragesView implements PlayerRegularSeasonAveragesViewInterface
{
    public function __construct(
        private readonly PlayerStatsRepository $repository,
        private readonly PlayerSeasonTableRendererInterface $renderer,
    ) {
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

        $config = new PlayerSeasonTableConfig(
            mode: PlayerSeasonTableMode::DETAILED_AVERAGES,
            title: 'Regular Season Averages',
            careerLabel: 'Career',
            linkTeamToSeason: true,
        );

        return $this->renderer->render($config, self::withMadeShotPointsFallback($historicalStats), $careerAverages);
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

    /**
     * Replace a stored pts of 0 (e.g. 2006 season) with 2*fgm + ftm + tgm.
     *
     * @param list<StatsRow> $rows
     * @return list<StatsRow>
     */
    private static function withMadeShotPointsFallback(array $rows): array
    {
        foreach ($rows as $i => $row) {
            if ($row['pts'] === 0) {
                $rows[$i]['pts'] = (2 * $row['fgm']) + $row['ftm'] + $row['tgm'];
            }
        }

        return $rows;
    }
}
