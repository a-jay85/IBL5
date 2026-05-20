<?php

declare(strict_types=1);

namespace Player\Stats\Views;

use Player\Stats\PlayerStatsRepository;
use Player\Stats\Contracts\PlayerPlayoffStatsViewInterface;
use BasketballStats\StatsFormatter;
use Security\HtmlSanitizer;

/**
 * PlayerPlayoffStatsView - Renders playoff statistics (totals/averages)
 *
 * Pure rendering with no database logic - all data fetched via PlayerStatsRepository
 *
 * @see PlayerPlayoffStatsViewInterface
 */
class PlayerPlayoffStatsView implements PlayerPlayoffStatsViewInterface
{
    private PlayerStatsRepository $repository;

    public function __construct(PlayerStatsRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @see PlayerPlayoffStatsViewInterface::renderPlayoffTotals()
     */
    public function renderPlayoffTotals(string $playerName): string
    {
        $playoffStats = $this->repository->getPlayoffStats($playerName);

        ob_start();
        ?>
<table class="ibl-data-table player-view-table">
    <tr>
        <td class="content-header">Team</td>
        <td class="content-header">Year</td>
        <td class="content-header">Games</td>
        <td class="content-header">Min</td>
        <td class="content-header">FGM-FGA</td>
        <td class="content-header">FG%</td>
        <td class="content-header">FTM-FTA</td>
        <td class="content-header">FT%</td>
        <td class="content-header">3GM-3GA</td>
        <td class="content-header">3G%</td>
        <td class="content-header">ORB</td>
        <td class="content-header">DRB</td>
        <td class="content-header">REB</td>
        <td class="content-header">AST</td>
        <td class="content-header">STL</td>
        <td class="content-header">TO</td>
        <td class="content-header">BLK</td>
        <td class="content-header">PF</td>
        <td class="content-header">PTS</td>
    </tr>
        <?php
        foreach ($playoffStats as $stats) {
            /** @var array{year: int, pos: string, pid: int, name: string, team: string, games: int, minutes: int, fgm: int, fga: int, ftm: int, fta: int, tgm: int, tga: int, orb: int, reb: int, ast: int, stl: int, tvr: int, blk: int, pf: int, pts: int} $stats */
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
</table>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Render playoff averages table
     *
     * @param string $playerName Player name to fetch stats for
     * @return string HTML for playoff averages table
     */
    public function renderPlayoffAverages(string $playerName): string
    {
        $playoffStats = $this->repository->getPlayoffStats($playerName);

        ob_start();
        ?>
<table class="ibl-data-table player-view-table">
    <tr>
        <td class="content-header">Team</td>
        <td class="content-header">Year</td>
        <td class="content-header">Games</td>
        <td class="content-header">Min</td>
        <td class="content-header">FG%</td>
        <td class="content-header">FT%</td>
        <td class="content-header">3G%</td>
        <td class="content-header">ORB</td>
        <td class="content-header">DRB</td>
        <td class="content-header">REB</td>
        <td class="content-header">AST</td>
        <td class="content-header">STL</td>
        <td class="content-header">TO</td>
        <td class="content-header">BLK</td>
        <td class="content-header">PF</td>
        <td class="content-header">PTS</td>
    </tr>
        <?php
        foreach ($playoffStats as $stats) {
            /** @var array{year: int, pos: string, pid: int, name: string, team: string, games: int, minutes: int, fgm: int, fga: int, ftm: int, fta: int, tgm: int, tga: int, orb: int, reb: int, ast: int, stl: int, tvr: int, blk: int, pf: int, pts: int} $stats */
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
</table>
        <?php
        return (string) ob_get_clean();
    }
}
