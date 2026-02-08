<?php

declare(strict_types=1);

/**
 * Season Leaderboard Update Script
 *
 * Calculates season career averages from the player table (car_* columns).
 */

require $_SERVER['DOCUMENT_ROOT'] . '/ibl5/autoloader.php';
include $_SERVER['DOCUMENT_ROOT'] . '/ibl5/config.php';
include $_SERVER['DOCUMENT_ROOT'] . '/ibl5/db/db.php';

use Scripts\LeaderboardRepository;

$repository = new LeaderboardRepository($mysqli_db);

echo "<HTML><HEAD><TITLE>SEASON LEADERBOARD UPDATE</TITLE></HEAD><BODY>";

// Get all players
$players = $repository->getAllPlayers();
$counter = 0;

foreach ($players as $player) {
    $playerName = $player['name'];
    $playerId = (int) $player['pid'];

    // Get career stats from ibl_plr table
    $careerStats = $repository->getPlayerCareerStats($playerName);

    if ($careerStats === null) {
        continue;
    }

    $totGames = (int) $careerStats['car_gm'];
    $totMinutes = (int) $careerStats['car_min'];
    $totFgm = (int) $careerStats['car_fgm'];
    $totFga = (int) $careerStats['car_fga'];
    $totFtm = (int) $careerStats['car_ftm'];
    $totFta = (int) $careerStats['car_fta'];
    $totTgm = (int) $careerStats['car_tgm'];
    $totTga = (int) $careerStats['car_tga'];
    $totOrb = (int) $careerStats['car_orb'];
    $totReb = (int) $careerStats['car_reb'];
    $totAst = (int) $careerStats['car_ast'];
    $totStl = (int) $careerStats['car_stl'];
    $totTvr = (int) $careerStats['car_to'];
    $totBlk = (int) $careerStats['car_blk'];
    $totPf = (int) $careerStats['car_pf'];
    $totPts = (int) $careerStats['car_pts'];

    echo "Updating " . htmlspecialchars($playerName) . "'s records... $totGames total games.<br>";

    // Delete old averages
    $repository->deletePlayerCareerAvgs($playerName, 'ibl_season_career_avgs');

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

        $repository->insertPlayerCareerAvgs('ibl_season_career_avgs', $avgData);
        $counter++;
    }
}

echo "Updated $counter records</BODY></HTML>";
