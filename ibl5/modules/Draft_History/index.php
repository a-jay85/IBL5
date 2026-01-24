<?php

declare(strict_types=1);

/**
 * Draft_History Module - Display draft history by year
 *
 * Shows draft picks for a selected year with player info and draft order.
 *
 * Refactored to use the interface-driven architecture pattern.
 *
 * @see DraftHistory\DraftHistoryRepository For database operations
 * @see DraftHistory\DraftHistoryView For HTML rendering
 */

if (!defined('MODULE_FILE')) {
    die("You can't access this file directly...");
}

use DraftHistory\DraftHistoryRepository;
use DraftHistory\DraftHistoryView;

$module_name = basename(dirname(__FILE__));
get_lang($module_name);

global $mysqli_db;

// Initialize services
$repository = new DraftHistoryRepository($mysqli_db);
$view = new DraftHistoryView();

// Get year range
$startYear = $repository->getFirstDraftYear();
$endYear = $repository->getLastDraftYear();

// Get selected year from request
$year = isset($_REQUEST['year']) ? (int) $_REQUEST['year'] : 0;

// Set page title
$pagetitle = "- " . ($year > 0 ? "$year Draft" : "Draft History");

// Get draft picks for selected year
$draftPicks = ($year > 0) ? $repository->getDraftPicksByYear($year) : [];

// Render page
Nuke\Header::header();
OpenTable();

echo $view->render($year, $startYear, $endYear, $draftPicks);

CloseTable();
Nuke\Footer::footer();
