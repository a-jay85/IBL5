<?php

declare(strict_types=1);

namespace Player\Views;

use Player\PlayerRepository;
use Player\Contracts\PlayerGameLogViewInterface;

/**
 * PlayerGameLogView - Renders player game logs by sim
 * 
 * Pure rendering with no database logic - all data fetched via PlayerRepository
 * 
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
     * @see PlayerGameLogViewInterface::renderSimStats()
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

    /**
     * @see PlayerGameLogViewInterface::renderGameLog()
     */
    public function renderGameLog(int $playerID, string $startDate, string $endDate): string
    {
        $boxScores = $this->repository->getBoxScoresBetweenDates($playerID, $startDate, $endDate);

        ob_start();
        ?>
<table class="sortable">
    <tr>
        <th>Date</th>
        <th>MIN</th>
        <th>PTS</th>
        <th>FGM</th>
        <th>FGA</th>
        <th>FG%</th>
        <th>FTM</th>
        <th>FTA</th>
        <th>FT%</th>
        <th>3GM</th>
        <th>3GA</th>
        <th>3G%</th>
        <th>ORB</th>
        <th>DRB</th>
        <th>REB</th>
        <th>AST</th>
        <th>STL</th>
        <th>TO</th>
        <th>BLK</th>
        <th>PF</th>
    </tr>
        <?php
        foreach ($boxScores as $row) {
            $fgm = $row['game2GM'] + $row['game3GM'];
            $fga = $row['game2GA'] + $row['game3GA'];
            $pts = (2 * $row['game2GM']) + (3 * $row['game3GM']) + $row['gameFTM'];
            $reb = $row['gameORB'] + $row['gameDRB'];
            
            $fgPct = $fga > 0 ? number_format($fgm / $fga, 3) : '0.000';
            $ftPct = $row['gameFTA'] > 0 ? number_format($row['gameFTM'] / $row['gameFTA'], 3) : '0.000';
            $tgPct = $row['game3GA'] > 0 ? number_format($row['game3GM'] / $row['game3GA'], 3) : '0.000';
            ?>
    <tr>
        <td class="gamelog"><?= htmlspecialchars($row['Date']) ?></td>
        <td class="gamelog"><?= htmlspecialchars((string)$row['gameMIN']) ?></td>
        <td class="gamelog"><?= htmlspecialchars((string)$pts) ?></td>
        <td class="gamelog"><?= htmlspecialchars((string)$fgm) ?></td>
        <td class="gamelog"><?= htmlspecialchars((string)$fga) ?></td>
        <td class="gamelog"><?= htmlspecialchars($fgPct) ?></td>
        <td class="gamelog"><?= htmlspecialchars((string)$row['gameFTM']) ?></td>
        <td class="gamelog"><?= htmlspecialchars((string)$row['gameFTA']) ?></td>
        <td class="gamelog"><?= htmlspecialchars($ftPct) ?></td>
        <td class="gamelog"><?= htmlspecialchars((string)$row['game3GM']) ?></td>
        <td class="gamelog"><?= htmlspecialchars((string)$row['game3GA']) ?></td>
        <td class="gamelog"><?= htmlspecialchars($tgPct) ?></td>
        <td class="gamelog"><?= htmlspecialchars((string)$row['gameORB']) ?></td>
        <td class="gamelog"><?= htmlspecialchars((string)$row['gameDRB']) ?></td>
        <td class="gamelog"><?= htmlspecialchars((string)$reb) ?></td>
        <td class="gamelog"><?= htmlspecialchars((string)$row['gameAST']) ?></td>
        <td class="gamelog"><?= htmlspecialchars((string)$row['gameSTL']) ?></td>
        <td class="gamelog"><?= htmlspecialchars((string)$row['gameTOV']) ?></td>
        <td class="gamelog"><?= htmlspecialchars((string)$row['gameBLK']) ?></td>
        <td class="gamelog"><?= htmlspecialchars((string)$row['gamePF']) ?></td>
    </tr>
            <?php
        }
        ?>
</table>
        <?php
        return ob_get_clean();
    }
}
