<?php

declare(strict_types=1);

/**
 * All_Star_Appearances Module - Display all-star appearance counts
 *
 * Shows a table of players and their all-star appearance counts.
 *
 * Refactored to use the interface-driven architecture pattern.
 *
 * @see AllStarAppearances\AllStarAppearancesRepository For database operations
 * @see AllStarAppearances\AllStarAppearancesView For HTML rendering
 */

if (!defined('MODULE_FILE')) {
    die("You can't access this file directly...");
}

use AllStarAppearances\AllStarAppearancesRepository;
use AllStarAppearances\AllStarAppearancesView;

$module_name = basename(dirname(__FILE__));
get_lang($module_name);

$pagetitle = "- All-Star Appearances";

global $mysqli_db;

// Initialize services
$repository = new AllStarAppearancesRepository($mysqli_db);
$view = new AllStarAppearancesView();

// Get all-star appearances data
$appearances = $repository->getAllStarAppearances();

// Render page
PageLayout\PageLayout::header();

echo $view->render($appearances);

PageLayout\PageLayout::footer();
