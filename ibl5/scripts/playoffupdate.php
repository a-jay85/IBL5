<?php

declare(strict_types=1);

/**
 * Playoff Leaderboard Update Script
 *
 * Updates career totals for the playoff leaderboard.
 */

require $_SERVER['DOCUMENT_ROOT'] . '/ibl5/autoloader.php';
include $_SERVER['DOCUMENT_ROOT'] . '/ibl5/config.php';
include $_SERVER['DOCUMENT_ROOT'] . '/ibl5/db/db.php';

use Scripts\LeaderboardRepository;

$repository = new LeaderboardRepository($mysqli_db);

echo "<HTML><HEAD><TITLE>PLAYOFF LEADERBOARD UPDATE</TITLE></HEAD><BODY>";

// Get all players
$players = $repository->getAllPlayers();
$counter = 0;

foreach ($players as $player) {
    $playerName = $player['name'];
    $playerId = (int) $player['pid'];

    // Get all playoff stats for this player
    $stats = $repository->getPlayerStats($playerName, 'ibl_playoff_stats');

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
    $repository->deletePlayerCareerTotals($playerName, 'ibl_playoff_career_totals');

    if ($totals['games'] > 0) {
        $repository->insertPlayerCareerTotals('ibl_playoff_career_totals', [
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

echo "Updated $counter records</BODY></HTML>";
