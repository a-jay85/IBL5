<?php

declare(strict_types=1);

namespace Player\Stats\Views;

use Player\Stats\PlayerStatsRepository;
use Player\Stats\Contracts\PlayerSeasonStatsViewInterface;
use BasketballStats\StatsFormatter;
use Security\HtmlSanitizer;

/**
 * PlayerSeasonStatsView - Renders regular season statistics (totals/averages)
 *
 * Pure rendering with no database logic - all data fetched via PlayerStatsRepository
 *
 * @see PlayerSeasonStatsViewInterface
 */
class PlayerSeasonStatsView implements PlayerSeasonStatsViewInterface
{
    private PlayerStatsRepository $repository;

    public function __construct(PlayerStatsRepository $repository)
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
<table class="ibl-data-table player-view-table">
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
            /** @var array{pid: int, name: string, year: int, team: string, teamid: int, games: int, minutes: int, fgm: int, fga: int, ftm: int, fta: int, tgm: int, tga: int, orb: int, reb: int, ast: int, stl: int, blk: int, tvr: int, pf: int, pts: int, ...} $stats */
            $drb = $stats['reb'] - $stats['orb'];
            ?>
    <tr>
        <td><?= HtmlSanitizer::e($stats['team']) ?></td>
        <td><?= (int)$stats['year'] ?></td>
        <td><?= (int)$stats['games'] ?></td>
        <td><?= (int)$stats['minutes'] ?></td>
        <td><?= (int)$stats['fgm'] ?>-<?= (int)$stats['fga'] ?></td>
        <td><?= StatsFormatter::formatPercentage($stats['fgm'], $stats['fga']) ?></td>
        <td><?= (int)$stats['ftm'] ?>-<?= (int)$stats['fta'] ?></td>
        <td><?= StatsFormatter::formatPercentage($stats['ftm'], $stats['fta']) ?></td>
        <td><?= (int)$stats['tgm'] ?>-<?= (int)$stats['tga'] ?></td>
        <td><?= StatsFormatter::formatPercentage($stats['tgm'], $stats['tga']) ?></td>
        <td><?= (int)$stats['orb'] ?></td>
        <td><?= (int)$drb ?></td>
        <td><?= (int)$stats['reb'] ?></td>
        <td><?= (int)$stats['ast'] ?></td>
        <td><?= (int)$stats['stl'] ?></td>
        <td><?= (int)$stats['tvr'] ?></td>
        <td><?= (int)$stats['blk'] ?></td>
        <td><?= (int)$stats['pf'] ?></td>
        <td><?= (int)$stats['pts'] ?></td>
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
<table class="ibl-data-table player-view-table">
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
            /** @var array{pid: int, name: string, year: int, team: string, teamid: int, games: int, minutes: int, fgm: int, fga: int, ftm: int, fta: int, tgm: int, tga: int, orb: int, reb: int, ast: int, stl: int, blk: int, tvr: int, pf: int, pts: int, ...} $stats */
            $games = $stats['games'];
            if ($games === 0) {
                continue;
            }

            $drb = $stats['reb'] - $stats['orb'];
            ?>
    <tr>
        <td><?= HtmlSanitizer::e($stats['team']) ?></td>
        <td><?= (int)$stats['year'] ?></td>
        <td><?= (int)$games ?></td>
        <td><?= StatsFormatter::formatPerGameAverage($stats['minutes'], $games) ?></td>
        <td><?= StatsFormatter::formatPercentage($stats['fgm'], $stats['fga']) ?></td>
        <td><?= StatsFormatter::formatPercentage($stats['ftm'], $stats['fta']) ?></td>
        <td><?= StatsFormatter::formatPercentage($stats['tgm'], $stats['tga']) ?></td>
        <td><?= StatsFormatter::formatPerGameAverage($stats['orb'], $games) ?></td>
        <td><?= StatsFormatter::formatPerGameAverage($drb, $games) ?></td>
        <td><?= StatsFormatter::formatPerGameAverage($stats['reb'], $games) ?></td>
        <td><?= StatsFormatter::formatPerGameAverage($stats['ast'], $games) ?></td>
        <td><?= StatsFormatter::formatPerGameAverage($stats['stl'], $games) ?></td>
        <td><?= StatsFormatter::formatPerGameAverage($stats['tvr'], $games) ?></td>
        <td><?= StatsFormatter::formatPerGameAverage($stats['blk'], $games) ?></td>
        <td><?= StatsFormatter::formatPerGameAverage($stats['pf'], $games) ?></td>
        <td><?= StatsFormatter::formatPerGameAverage($stats['pts'], $games) ?></td>
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
