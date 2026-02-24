<?php

declare(strict_types=1);

namespace Player\Views;

use Player\PlayerRepository;
use Player\Contracts\PlayerOlympicsStatsViewInterface;
use BasketballStats\StatsFormatter;
use Utilities\HtmlSanitizer;

/**
 * PlayerOlympicsStatsView - Renders Olympics tournament statistics
 *
 * Pure rendering with no database logic - all data fetched via PlayerRepository
 *
 * @see PlayerOlympicsStatsViewInterface
 */
class PlayerOlympicsStatsView implements PlayerOlympicsStatsViewInterface
{
    private PlayerRepository $repository;

    public function __construct(PlayerRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @see PlayerOlympicsStatsViewInterface::renderOlympicsTotals()
     */
    public function renderOlympicsTotals(string $playerName): string
    {
        $olympicsStats = $this->repository->getOlympicsStats($playerName);

        ob_start();
        ?>
<table class="stats-table">
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
        foreach ($olympicsStats as $stats) {
            /** @var array{team: string, year: int, games: int, minutes: int, fgm: int, fga: int, ftm: int, fta: int, tgm: int, tga: int, orb: int, reb: int, ast: int, stl: int, tvr: int, blk: int, pf: int, pts: int} $stats */
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
</table>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Render Olympics averages table
     *
     * @param string $playerName Player name to fetch stats for
     * @return string HTML for Olympics averages table
     */
    public function renderOlympicsAverages(string $playerName): string
    {
        $olympicsStats = $this->repository->getOlympicsStats($playerName);

        ob_start();
        ?>
<table class="stats-table">
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
        foreach ($olympicsStats as $stats) {
            /** @var array{team: string, year: int, games: int, minutes: int, fgm: int, fga: int, ftm: int, fta: int, tgm: int, tga: int, orb: int, reb: int, ast: int, stl: int, tvr: int, blk: int, pf: int, pts: int} $stats */
            $games = $stats['games'];
            if ($games === 0) {
                continue;
            }

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
</table>
        <?php
        return (string) ob_get_clean();
    }
}
