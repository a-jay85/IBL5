<?php

declare(strict_types=1);

/**
 * H.E.A.T. Leaderboard Update Script
 *
 * Updates career totals and averages for the H.E.A.T. tournament leaderboard.
 * Combines functionality of heatupdate.php and heatlbupdate.php.
 */

require $_SERVER['DOCUMENT_ROOT'] . '/ibl5/autoloader.php';
include $_SERVER['DOCUMENT_ROOT'] . '/ibl5/config.php';
include $_SERVER['DOCUMENT_ROOT'] . '/ibl5/db/db.php';

use Scripts\LeaderboardRepository;

$repository = new LeaderboardRepository($mysqli_db);

echo "<HTML><HEAD><TITLE>H.E.A.T. LEADERBOARD UPDATE</TITLE></HEAD><BODY>";
echo "<p>H.E.A.T. LEADERBOARD UPDATE (Career Totals)<p>";

// Get all players
$players = $repository->getAllPlayers();
$counter = 0;

foreach ($players as $player) {
    $playerName = $player['name'];
    $playerId = (int) $player['pid'];

    // Get all H.E.A.T. stats for this player
    $stats = $repository->getPlayerStats($playerName, 'ibl_heat_stats');

    // Sum up all stats
    $totals = [
        'games' => 0,
        'minutes' => 0,
        'fgm' => 0,
        'fga' => 0,
        'ftm' => 0,
        'fta' => 0,
        'tgm' => 0,
        'tga' => 0,
        'orb' => 0,
        'reb' => 0,
        'ast' => 0,
        'stl' => 0,
        'tvr' => 0,
        'blk' => 0,
        'pf' => 0,
        'pts' => 0,
    ];

    foreach ($stats as $statRow) {
        $totals['games'] += (int) $statRow['games'];
        $totals['minutes'] += (int) $statRow['minutes'];
        $totals['fgm'] += (int) $statRow['fgm'];
        $totals['fga'] += (int) $statRow['fga'];
        $totals['ftm'] += (int) $statRow['ftm'];
        $totals['fta'] += (int) $statRow['fta'];
        $totals['tgm'] += (int) $statRow['tgm'];
        $totals['tga'] += (int) $statRow['tga'];
        $totals['orb'] += (int) $statRow['orb'];
        $totals['reb'] += (int) $statRow['reb'];
        $totals['ast'] += (int) $statRow['ast'];
        $totals['stl'] += (int) $statRow['stl'];
        $totals['tvr'] += (int) $statRow['tvr'];
        $totals['blk'] += (int) $statRow['blk'];
        $totals['pf'] += (int) $statRow['pf'];
        // Calculate points: FGM*2 + FTM + TGM (3-pointers add 1 extra)
        $totals['pts'] += (int) $statRow['fgm'] * 2 + (int) $statRow['ftm'] + (int) $statRow['tgm'];
    }

    echo "Updating " . htmlspecialchars($playerName) . "'s records... {$totals['games']} total games.<br>";

    // Delete old record and insert new one if player has games
    $repository->deletePlayerCareerTotals($playerName, 'ibl_heat_career_totals');

    if ($totals['games'] > 0) {
        $repository->insertPlayerCareerTotals('ibl_heat_career_totals', [
            'pid' => $playerId,
            'name' => $playerName,
            'games' => $totals['games'],
            'minutes' => $totals['minutes'],
            'fgm' => $totals['fgm'],
            'fga' => $totals['fga'],
            'ftm' => $totals['ftm'],
            'fta' => $totals['fta'],
            'tgm' => $totals['tgm'],
            'tga' => $totals['tga'],
            'orb' => $totals['orb'],
            'reb' => $totals['reb'],
            'ast' => $totals['ast'],
            'stl' => $totals['stl'],
            'tvr' => $totals['tvr'],
            'blk' => $totals['blk'],
            'pf' => $totals['pf'],
            'pts' => $totals['pts'],
        ]);
        $counter++;
    }
}

echo "Updated $counter records";

// Part 2: Calculate career averages
echo "<p>H.E.A.T. LEADERBOARD UPDATE (Career Averages)<p>";

$avgCounter = 0;

foreach ($players as $player) {
    $playerName = $player['name'];
    $playerId = (int) $player['pid'];

    // Get career totals for this player
    $careerTotals = $repository->getPlayerStats($playerName, 'ibl_heat_stats');

    // Sum up career totals
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
        // Points = FGM*2 + FTM + TGM
        $totPts += (int) $row['fgm'] * 2 + (int) $row['ftm'] + (int) $row['tgm'];
    }

    echo "Updating " . htmlspecialchars($playerName) . "'s averages... $totGames total games.<br>";

    // Delete old averages
    $repository->deletePlayerCareerAvgs($playerName, 'ibl_heat_career_avgs');

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

        $repository->insertPlayerCareerAvgs('ibl_heat_career_avgs', $avgData);
        $avgCounter++;
    }
}

echo "Updated $avgCounter records</BODY></HTML>";
