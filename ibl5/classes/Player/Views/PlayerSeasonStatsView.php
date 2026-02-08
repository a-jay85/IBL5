<?php

declare(strict_types=1);

namespace Player\Views;

use Player\PlayerRepository;
use Player\Contracts\PlayerSeasonStatsViewInterface;
use BasketballStats\StatsFormatter;
use Utilities\HtmlSanitizer;

/**
 * PlayerSeasonStatsView - Renders regular season statistics (totals/averages)
 * 
 * Pure rendering with no database logic - all data fetched via PlayerRepository
 * 
 * @see PlayerSeasonStatsViewInterface
 */
class PlayerSeasonStatsView implements PlayerSeasonStatsViewInterface
{
    private PlayerRepository $repository;

    public function __construct(PlayerRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @see PlayerSeasonStatsViewInterface::renderSeasonTotals()
     */
    public function renderSeasonTotals(int $playerID): string
    {
        $historicalStats = $this->repository->getHistoricalStats($playerID);

        ob_start();
        ?>
<table class="stats-table">
    <thead>
        <tr>
            <th>Team</th>
            <th>Year</th>
            <th>Games</th>
            <th>Min</th>
            <th>FGM-FGA</th>
            <th>FG%</th>
            <th>FTM-FTA</th>
            <th>FT%</th>
            <th>3GM-3GA</th>
            <th>3G%</th>
            <th>ORB</th>
            <th>DRB</th>
            <th>REB</th>
            <th>AST</th>
            <th>STL</th>
            <th>TO</th>
            <th>BLK</th>
            <th>PF</th>
            <th>PTS</th>
        </tr>
    </thead>
    <tbody>
        <?php
        foreach ($historicalStats as $stats) {
            /** @var array{team: string, year: int, games: int, minutes: int, fgm: int, fga: int, ftm: int, fta: int, tgm: int, tga: int, orb: int, reb: int, ast: int, stl: int, tvr: int, blk: int, pf: int, pts: int} $stats */
            /** @var string $team */
            $team = HtmlSanitizer::safeHtmlOutput($stats['team']);
            $drb = $stats['reb'] - $stats['orb'];

            $fgPercent = StatsFormatter::formatPercentage($stats['fgm'], $stats['fga']);
            $ftPercent = StatsFormatter::formatPercentage($stats['ftm'], $stats['fta']);
            $tgPercent = StatsFormatter::formatPercentage($stats['tgm'], $stats['tga']);
            ?>
    <tr>
        <td><?= $team ?></td>
        <td><?= $stats['year'] ?></td>
        <td><?= $stats['games'] ?></td>
        <td><?= $stats['minutes'] ?></td>
        <td><?= $stats['fgm'] ?>-<?= $stats['fga'] ?></td>
        <td><?= $fgPercent ?></td>
        <td><?= $stats['ftm'] ?>-<?= $stats['fta'] ?></td>
        <td><?= $ftPercent ?></td>
        <td><?= $stats['tgm'] ?>-<?= $stats['tga'] ?></td>
        <td><?= $tgPercent ?></td>
        <td><?= $stats['orb'] ?></td>
        <td><?= $drb ?></td>
        <td><?= $stats['reb'] ?></td>
        <td><?= $stats['ast'] ?></td>
        <td><?= $stats['stl'] ?></td>
        <td><?= $stats['tvr'] ?></td>
        <td><?= $stats['blk'] ?></td>
        <td><?= $stats['pf'] ?></td>
        <td><?= $stats['pts'] ?></td>
    </tr>
            <?php
        }
        ?>
    </tbody>
</table>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * @see PlayerSeasonStatsViewInterface::renderSeasonAverages()
     */
    public function renderSeasonAverages(int $playerID): string
    {
        $historicalStats = $this->repository->getHistoricalStats($playerID);

        ob_start();
        ?>
<table class="stats-table">
    <thead>
        <tr>
            <th>Team</th>
            <th>Year</th>
            <th>Games</th>
            <th>Min</th>
            <th>FG%</th>
            <th>FT%</th>
            <th>3G%</th>
            <th>ORB</th>
            <th>DRB</th>
            <th>REB</th>
            <th>AST</th>
            <th>STL</th>
            <th>TO</th>
            <th>BLK</th>
            <th>PF</th>
            <th>PTS</th>
        </tr>
    </thead>
    <tbody>
        <?php
        foreach ($historicalStats as $stats) {
            /** @var array{team: string, year: int, games: int, minutes: int, fgm: int, fga: int, ftm: int, fta: int, tgm: int, tga: int, orb: int, reb: int, ast: int, stl: int, tvr: int, blk: int, pf: int, pts: int} $stats */
            $games = $stats['games'];
            if ($games === 0) {
                continue;
            }

            /** @var string $team */
            $team = HtmlSanitizer::safeHtmlOutput($stats['team']);
            $drb = $stats['reb'] - $stats['orb'];

            $avgMinutes = StatsFormatter::formatPerGameAverage($stats['minutes'], $games);
            $fgPercent = StatsFormatter::formatPercentage($stats['fgm'], $stats['fga']);
            $ftPercent = StatsFormatter::formatPercentage($stats['ftm'], $stats['fta']);
            $tgPercent = StatsFormatter::formatPercentage($stats['tgm'], $stats['tga']);
            $avgOrb = StatsFormatter::formatPerGameAverage($stats['orb'], $games);
            $avgDrb = StatsFormatter::formatPerGameAverage($drb, $games);
            $avgReb = StatsFormatter::formatPerGameAverage($stats['reb'], $games);
            $avgAst = StatsFormatter::formatPerGameAverage($stats['ast'], $games);
            $avgStl = StatsFormatter::formatPerGameAverage($stats['stl'], $games);
            $avgTo = StatsFormatter::formatPerGameAverage($stats['tvr'], $games);
            $avgBlk = StatsFormatter::formatPerGameAverage($stats['blk'], $games);
            $avgPf = StatsFormatter::formatPerGameAverage($stats['pf'], $games);
            $avgPts = StatsFormatter::formatPerGameAverage($stats['pts'], $games);
            ?>
    <tr>
        <td><?= $team ?></td>
        <td><?= $stats['year'] ?></td>
        <td><?= $games ?></td>
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
    </tbody>
</table>
        <?php
        return (string) ob_get_clean();
    }
}
