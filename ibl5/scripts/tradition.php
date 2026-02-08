<?php

declare(strict_types=1);

/**
 * Update Tradition Script
 *
 * Updates the tradition factors (average W/L over last 5 complete seasons)
 * used in free agency calculations.
 */

require $_SERVER['DOCUMENT_ROOT'] . '/ibl5/autoloader.php';
include $_SERVER['DOCUMENT_ROOT'] . '/ibl5/config.php';
include $_SERVER['DOCUMENT_ROOT'] . '/ibl5/db/db.php';

use Scripts\MaintenanceRepository;

$repository = new MaintenanceRepository($mysqli_db);

echo "<HTML><HEAD><TITLE>Update Tradition for Free Agency Factors</TITLE></HEAD><BODY>";

// Get all teams
$teams = $repository->getAllTeams();

foreach ($teams as $team) {
    $teamName = $team['team_name'];

    // Get last 5 complete seasons
    $seasons = $repository->getTeamRecentCompleteSeasons($teamName, 5);

    $totalWins = 0;
    $totalLosses = 0;
    $seasonCount = count($seasons);

    foreach ($seasons as $season) {
        $totalWins += (int) $season['wins'];
        $totalLosses += (int) $season['losses'];
    }

    echo "Updating " . htmlspecialchars($teamName) . " Tradition Information... $totalWins wins, $totalLosses losses, in $seasonCount seasons.<br>";

    if ($seasonCount > 0) {
        $avgWins = (int) round($totalWins / $seasonCount);
        $avgLosses = (int) round($totalLosses / $seasonCount);

        echo " Tradition: $avgWins - $avgLosses<br>";

        $repository->updateTeamTradition($teamName, $avgWins, $avgLosses);
    }
}

echo "</BODY></HTML>";
