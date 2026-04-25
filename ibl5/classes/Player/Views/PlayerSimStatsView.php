<?php

declare(strict_types=1);

namespace Player\Views;

use Player\PlayerStatsRepository;
use Player\Contracts\PlayerSimStatsViewInterface;
use BasketballStats\StatsFormatter;

/**
 * PlayerSimStatsView - Renders sim-by-sim statistics
 * 
 * Shows player averages broken down by simulation period.
 * Uses PlayerStatsRepository for all database access.
 * 
 * @see PlayerSimStatsViewInterface
 */
class PlayerSimStatsView implements PlayerSimStatsViewInterface
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
        // This method requires context - use renderSimStats() instead
        return '';
    }

    /**
     * @see PlayerSimStatsViewInterface::renderSimStats()
     */
    public function renderSimStats(int $playerID): string
    {
        $simDates = $this->repository->getSimDates(20);

        ob_start();
        ?>
<table class="sortable player-table sim-stats-table">
    <tr>
        <td colspan=16 class="player-table-header">Sim Averages</td>
    </tr>
    <tr class="font-bold">
        <th>sim</th>
        <th>g</th>
        <th>min</th>
        <th>FGP</th>
        <th>FTP</th>
        <th>3GP</th>
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
        foreach ($simDates as $simDate) {
            /** @var array{sim: int, start_date: string, end_date: string} $simDate */
            $simNumber = $simDate['sim'];
            $simStartDate = $simDate['start_date'];
            $simEndDate = $simDate['end_date'];

            $boxScores = $this->repository->getBoxScoresBetweenDates($playerID, $simStartDate, $simEndDate);
            $numberOfGames = count($boxScores);

            if ($numberOfGames === 0) {
                continue;
            }

            // Calculate totals
            $totals = $this->calculateSimTotals(array_values($boxScores));

            // Calculate averages
            $avgMinutes = sprintf('%01.1f', $totals['minutes'] / $numberOfGames);
            $avgFGP = StatsFormatter::formatPercentageWithDecimals($totals['fgm'], $totals['fga']);
            $avgFTP = StatsFormatter::formatPercentageWithDecimals($totals['ftm'], $totals['fta']);
            $avg3GP = StatsFormatter::formatPercentageWithDecimals($totals['tgm'], $totals['tga']);
            $avgORB = sprintf('%01.1f', $totals['orb'] / $numberOfGames);
            $avgREB = sprintf('%01.1f', ($totals['orb'] + $totals['drb']) / $numberOfGames);
            $avgAST = sprintf('%01.1f', $totals['ast'] / $numberOfGames);
            $avgSTL = sprintf('%01.1f', $totals['stl'] / $numberOfGames);
            $avgTO = sprintf('%01.1f', $totals['to'] / $numberOfGames);
            $avgBLK = sprintf('%01.1f', $totals['blk'] / $numberOfGames);
            $avgPF = sprintf('%01.1f', $totals['pf'] / $numberOfGames);
            $avgPTS = sprintf('%01.1f', $totals['pts'] / $numberOfGames);
            ?>
    <tr>
        <td><?= $simNumber ?></td>
        <td><?= $numberOfGames ?></td>
        <td><?= $avgMinutes ?></td>
        <td><?= $avgFGP ?></td>
        <td><?= $avgFTP ?></td>
        <td><?= $avg3GP ?></td>
        <td><?= $avgORB ?></td>
        <td><?= $avgREB ?></td>
        <td><?= $avgAST ?></td>
        <td><?= $avgSTL ?></td>
        <td><?= $avgTO ?></td>
        <td><?= $avgBLK ?></td>
        <td><?= $avgPF ?></td>
        <td><?= $avgPTS ?></td>
    </tr>
            <?php
        }
        ?>
</table>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Calculate totals from box scores
     *
     * @param list<array<string, mixed>> $boxScores Box score rows from repository
     * @return array{minutes: int, fgm: int, fga: int, ftm: int, fta: int, tgm: int, tga: int, orb: int, drb: int, ast: int, stl: int, to: int, blk: int, pf: int, pts: int}
     */
    private function calculateSimTotals(array $boxScores): array
    {
        $totals = [
            'minutes' => 0, 'fgm' => 0, 'fga' => 0,
            'ftm' => 0, 'fta' => 0, 'tgm' => 0, 'tga' => 0,
            'orb' => 0, 'drb' => 0, 'ast' => 0, 'stl' => 0,
            'to' => 0, 'blk' => 0, 'pf' => 0, 'pts' => 0
        ];

        foreach ($boxScores as $row) {
            /** @var array{game_min: int, game_2gm: int, game_2ga: int, game_3gm: int, game_3ga: int, game_ftm: int, game_fta: int, game_orb: int, game_drb: int, game_ast: int, game_stl: int, game_tov: int, game_blk: int, game_pf: int} $row */
            $totals['minutes'] += $row['game_min'];
            $totals['fgm'] += $row['game_2gm'] + $row['game_3gm'];
            $totals['fga'] += $row['game_2ga'] + $row['game_3ga'];
            $totals['ftm'] += $row['game_ftm'];
            $totals['fta'] += $row['game_fta'];
            $totals['tgm'] += $row['game_3gm'];
            $totals['tga'] += $row['game_3ga'];
            $totals['orb'] += $row['game_orb'];
            $totals['drb'] += $row['game_drb'];
            $totals['ast'] += $row['game_ast'];
            $totals['stl'] += $row['game_stl'];
            $totals['to'] += $row['game_tov'];
            $totals['blk'] += $row['game_blk'];
            $totals['pf'] += $row['game_pf'];
            $totals['pts'] += (2 * $row['game_2gm']) + (3 * $row['game_3gm']) + $row['game_ftm'];
        }

        return $totals;
    }
}
