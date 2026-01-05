<?php

declare(strict_types=1);

/**
 * Standings Module - Display league standings
 *
 * Dynamically generates standings from the database using the Standings module classes.
 * This replaces the previous static HTML that was stored in nuke_pages.
 *
 * @see \Standings\StandingsRepository For data access
 * @see \Standings\StandingsView For HTML rendering
 */

if (!mb_eregi("modules.php", $_SERVER['PHP_SELF'])) {
    die("You can't access this file directly...");
}

// Get database connection from the global context
global $mysqli_db;

// Ensure database connection is available
if (!isset($mysqli_db) || !$mysqli_db) {
    echo '<p>Error: Database connection not available.</p>';
    return;
}

// Create repository and view instances
$repository = new Standings\StandingsRepository($mysqli_db);
$view = new Standings\StandingsView($repository);

// Render and output the standings
    Nuke\Header::header();
    OpenTable();
    
    echo $view->render();

    CloseTable();
    Nuke\Footer::footer();
