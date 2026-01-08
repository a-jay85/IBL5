<?php

declare(strict_types=1);

namespace Player\Views;

use Player\PlayerStatsRepository;
use Player\Contracts\PlayerSimStatsViewInterface;
use BasketballStats\StatsFormatter;
use Utilities\HtmlSanitizer;

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
<table border=1 cellspacing=0 class="sortable" style='margin: 0 auto;'>
    <tr>
        <td colspan=16 style='font-weight:bold;text-align:center;background-color:#00c;color:#fff;'>Sim Averages</td>
    </tr>
    <tr style="font-weight: bold">
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
            $simNumber = $simDate['Sim'];
            $simStartDate = $simDate['Start Date'];
            $simEndDate = $simDate['End Date'];

            $boxScores = $this->repository->getBoxScoresBetweenDates($playerID, $simStartDate, $simEndDate);
            $numberOfGames = count($boxScores);

            if ($numberOfGames === 0) {
                continue;
            }

            // Calculate totals
            $totals = $this->calculateSimTotals($boxScores);
            
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
        <td><?= HtmlSanitizer::safeHtmlOutput((string)$simNumber) ?></td>
        <td><?= HtmlSanitizer::safeHtmlOutput((string)$numberOfGames) ?></td>
        <td><?= HtmlSanitizer::safeHtmlOutput($avgMinutes) ?></td>
        <td><?= HtmlSanitizer::safeHtmlOutput($avgFGP) ?></td>
        <td><?= HtmlSanitizer::safeHtmlOutput($avgFTP) ?></td>
        <td><?= HtmlSanitizer::safeHtmlOutput($avg3GP) ?></td>
        <td><?= HtmlSanitizer::safeHtmlOutput($avgORB) ?></td>
        <td><?= HtmlSanitizer::safeHtmlOutput($avgREB) ?></td>
        <td><?= HtmlSanitizer::safeHtmlOutput($avgAST) ?></td>
        <td><?= HtmlSanitizer::safeHtmlOutput($avgSTL) ?></td>
        <td><?= HtmlSanitizer::safeHtmlOutput($avgTO) ?></td>
        <td><?= HtmlSanitizer::safeHtmlOutput($avgBLK) ?></td>
        <td><?= HtmlSanitizer::safeHtmlOutput($avgPF) ?></td>
        <td><?= HtmlSanitizer::safeHtmlOutput($avgPTS) ?></td>
    </tr>
            <?php
        }
        ?>
</table>
        <?php
        return ob_get_clean();
    }

    /**
     * Calculate totals from box scores
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
            $totals['minutes'] += $row['gameMIN'];
            $totals['fgm'] += $row['game2GM'] + $row['game3GM'];
            $totals['fga'] += $row['game2GA'] + $row['game3GA'];
            $totals['ftm'] += $row['gameFTM'];
            $totals['fta'] += $row['gameFTA'];
            $totals['tgm'] += $row['game3GM'];
            $totals['tga'] += $row['game3GA'];
            $totals['orb'] += $row['gameORB'];
            $totals['drb'] += $row['gameDRB'];
            $totals['ast'] += $row['gameAST'];
            $totals['stl'] += $row['gameSTL'];
            $totals['to'] += $row['gameTOV'];
            $totals['blk'] += $row['gameBLK'];
            $totals['pf'] += $row['gamePF'];
            $totals['pts'] += (2 * $row['game2GM']) + (3 * $row['game3GM']) + $row['gameFTM'];
        }

        return $totals;
    }
}
