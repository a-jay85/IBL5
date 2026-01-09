<?php

declare(strict_types=1);

/**
 * Franchise_History Module - Display franchise history and records
 *
 * Shows all-time and recent (last 5 seasons) win/loss records and titles.
 *
 * Refactored to use the interface-driven architecture pattern.
 *
 * @see FranchiseHistory\FranchiseHistoryRepository For database operations
 * @see FranchiseHistory\FranchiseHistoryView For HTML rendering
 */

if (!defined('MODULE_FILE') && !mb_eregi('modules.php', $_SERVER['PHP_SELF'])) {
    die("You can't access this file directly...");
}

use FranchiseHistory\FranchiseHistoryRepository;
use FranchiseHistory\FranchiseHistoryView;

global $mysqli_db;

$season = new Season($mysqli_db);

Nuke\Header::header();
OpenTable();

// Initialize services
$repository = new FranchiseHistoryRepository($mysqli_db);
$view = new FranchiseHistoryView();

// Get franchise history data
$franchiseData = $repository->getAllFranchiseHistory($season->endingYear);

// Render output
echo $view->render($franchiseData);

CloseTable();
Nuke\Footer::footer();
