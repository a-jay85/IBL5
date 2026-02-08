<?php

declare(strict_types=1);

/**
 * Playoff Leaderboard Averages Update Script
 *
 * Calculates career averages from career totals for the playoff leaderboard.
 */

require $_SERVER['DOCUMENT_ROOT'] . '/ibl5/autoloader.php';
include $_SERVER['DOCUMENT_ROOT'] . '/ibl5/config.php';
include $_SERVER['DOCUMENT_ROOT'] . '/ibl5/db/db.php';

use Scripts\LeaderboardRepository;

$repository = new LeaderboardRepository($mysqli_db);

echo "<HTML><HEAD><TITLE>PLAYOFF LEADERBOARD UPDATE (Averages)</TITLE></HEAD><BODY>";

// Get all players
$players = $repository->getAllPlayers();
$counter = 0;

foreach ($players as $player) {
    $playerName = $player['name'];
    $playerId = (int) $player['pid'];

    // Get career totals for this player
    $careerTotals = $repository->getPlayerStats($playerName, 'ibl_playoff_career_totals');

    // Sum up all totals (typically one row per player)
    $totGames = 0;
    $totMinutes = 0;
    $totFgm = 0;
    $totFga = 0;
    $totFtm = 0;
    $totFta = 0;
    $totTgm = 0;
    $totTga = 0;
    $totOrb = 0;
    $totReb = 0;
    $totAst = 0;
    $totStl = 0;
    $totTvr = 0;
    $totBlk = 0;
    $totPf = 0;
    $totPts = 0;

    foreach ($careerTotals as $row) {
        $totGames += (int) $row['games'];
        $totMinutes += (int) $row['minutes'];
        $totFgm += (int) $row['fgm'];
        $totFga += (int) $row['fga'];
        $totFtm += (int) $row['ftm'];
        $totFta += (int) $row['fta'];
        $totTgm += (int) $row['tgm'];
        $totTga += (int) $row['tga'];
        $totOrb += (int) $row['orb'];
        $totReb += (int) $row['reb'];
        $totAst += (int) $row['ast'];
        $totStl += (int) $row['stl'];
        $totTvr += (int) $row['tvr'];
        $totBlk += (int) $row['blk'];
        $totPf += (int) $row['pf'];
        $totPts += (int) $row['pts'];
    }

    echo "Updating " . htmlspecialchars($playerName) . "'s records... $totGames total games.<br>";

    // Delete old averages
    $repository->deletePlayerCareerAvgs($playerName, 'ibl_playoff_career_avgs');

    if ($totGames > 0) {
        // Calculate averages
        $avgData = [
            'pid' => $playerId,
            'name' => $playerName,
            'games' => $totGames,
            'minutes' => round($totMinutes / $totGames, 2),
            'fgm' => round($totFgm / $totGames, 2),
            'fga' => round($totFga / $totGames, 2),
            'fgpct' => $totFga > 0 ? round($totFgm / $totFga, 3) : 0.000,
            'ftm' => round($totFtm / $totGames, 2),
            'fta' => round($totFta / $totGames, 2),
            'ftpct' => $totFta > 0 ? round($totFtm / $totFta, 3) : 0.000,
            'tgm' => round($totTgm / $totGames, 2),
            'tga' => round($totTga / $totGames, 2),
            'tpct' => $totTga > 0 ? round($totTgm / $totTga, 3) : 0.000,
            'orb' => round($totOrb / $totGames, 2),
            'reb' => round($totReb / $totGames, 2),
            'ast' => round($totAst / $totGames, 2),
            'stl' => round($totStl / $totGames, 2),
            'tvr' => round($totTvr / $totGames, 2),
            'blk' => round($totBlk / $totGames, 2),
            'pf' => round($totPf / $totGames, 2),
            'pts' => round($totPts / $totGames, 2),
        ];

        $repository->insertPlayerCareerAvgs('ibl_playoff_career_avgs', $avgData);
        $counter++;
    }
}

echo "Updated $counter records</BODY></HTML>";
