<?php

declare(strict_types=1);

namespace Player\Views;

use Player\Contracts\PlayerGameLogViewInterface;
use Player\PlayerRepository;

/**
 * @see PlayerGameLogViewInterface
 */
class PlayerGameLogView implements PlayerGameLogViewInterface
{
    private PlayerRepository $repository;

    public function __construct(PlayerRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Render sim-by-sim statistics table
     * 
     * @param int $playerID Player ID to fetch stats for
     * @return string HTML for sim stats table
     */
    public function renderSimStats(int $playerID): string
    {
        ob_start();
        ?>
<table align=center border=1 cellpadding=3 cellspacing=0 style="text-align: center">
    <tr>
        <td colspan=16><b><font class="content">Sim Averages</font></b></td>
    </tr>
    <tr style="font-weight: bold">
        <td>sim</td>
        <td>g</td>
        <td>min</td>
        <td>FGP</td>
        <td>FTP</td>
        <td>3GP</td>
        <td>orb</td>
        <td>reb</td>
        <td>ast</td>
        <td>stl</td>
        <td>to</td>
        <td>blk</td>
        <td>pf</td>
        <td>pts</td>
    </tr>
        <?php
        $simDates = $this->repository->getAllSimDates();
        
        foreach ($simDates as $simDate) {
            $simNumber = $simDate['Sim'];
            $simStartDate = $simDate['Start Date'];
            $simEndDate = $simDate['End Date'];

            $boxScores = $this->repository->getBoxScoresBetweenDates(
                $playerID,
                $simStartDate,
                $simEndDate
            );

            $numberOfGames = count($boxScores);

            if ($numberOfGames == 0) {
                continue;
            }

            // Calculate totals
            $totalMinutes = 0;
            $totalFGM = 0;
            $totalFGA = 0;
            $totalFTM = 0;
            $totalFTA = 0;
            $total3GM = 0;
            $total3GA = 0;
            $totalORB = 0;
            $totalDRB = 0;
            $totalAST = 0;
            $totalSTL = 0;
            $totalTO = 0;
            $totalBLK = 0;
            $totalPF = 0;
            $totalPTS = 0;

            foreach ($boxScores as $game) {
                $totalMinutes += $game['minutes'];
                $totalFGM += $game['fgm'];
                $totalFGA += $game['fga'];
                $totalFTM += $game['ftm'];
                $totalFTA += $game['fta'];
                $total3GM += $game['tgm'];
                $total3GA += $game['tga'];
                $totalORB += $game['orb'];
                $totalDRB += $game['drb'];
                $totalAST += $game['ast'];
                $totalSTL += $game['stl'];
                $totalTO += $game['tos'];
                $totalBLK += $game['blk'];
                $totalPF += $game['pf'];
                $totalPTS += $game['points'];
            }

            // Calculate averages
            $avgMinutes = number_format($totalMinutes / $numberOfGames, 1);
            $avgFGP = $totalFGA > 0 ? number_format(($totalFGM / $totalFGA) * 100, 1) : '0.0';
            $avgFTP = $totalFTA > 0 ? number_format(($totalFTM / $totalFTA) * 100, 1) : '0.0';
            $avg3GP = $total3GA > 0 ? number_format(($total3GM / $total3GA) * 100, 1) : '0.0';
            $avgORB = number_format($totalORB / $numberOfGames, 1);
            $avgREB = number_format(($totalORB + $totalDRB) / $numberOfGames, 1);
            $avgAST = number_format($totalAST / $numberOfGames, 1);
            $avgSTL = number_format($totalSTL / $numberOfGames, 1);
            $avgTO = number_format($totalTO / $numberOfGames, 1);
            $avgBLK = number_format($totalBLK / $numberOfGames, 1);
            $avgPF = number_format($totalPF / $numberOfGames, 1);
            $avgPTS = number_format($totalPTS / $numberOfGames, 1);

            ?>
    <tr>
        <td><?= htmlspecialchars((string)$simNumber) ?></td>
        <td><?= htmlspecialchars((string)$numberOfGames) ?></td>
        <td><?= htmlspecialchars($avgMinutes) ?></td>
        <td><?= htmlspecialchars($avgFGP) ?></td>
        <td><?= htmlspecialchars($avgFTP) ?></td>
        <td><?= htmlspecialchars($avg3GP) ?></td>
        <td><?= htmlspecialchars($avgORB) ?></td>
        <td><?= htmlspecialchars($avgREB) ?></td>
        <td><?= htmlspecialchars($avgAST) ?></td>
        <td><?= htmlspecialchars($avgSTL) ?></td>
        <td><?= htmlspecialchars($avgTO) ?></td>
        <td><?= htmlspecialchars($avgBLK) ?></td>
        <td><?= htmlspecialchars($avgPF) ?></td>
        <td><?= htmlspecialchars($avgPTS) ?></td>
    </tr>
            <?php
        }
        ?>
</table>
        <?php
        return ob_get_clean();
    }
}
