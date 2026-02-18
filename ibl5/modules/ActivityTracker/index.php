<?php

declare(strict_types=1);

/**
 * ActivityTracker Module - Display team activity status
 *
 * Shows depth chart updates, sim depth chart status, and voting status for all teams.
 *
 * @see ActivityTracker\ActivityTrackerRepository For database operations
 * @see ActivityTracker\ActivityTrackerView For HTML rendering
 */

if (stripos($_SERVER['PHP_SELF'], "modules.php") === false) {
    die("You can't access this file directly...");
}

$module_name = basename(dirname(__FILE__));
get_lang($module_name);

use ActivityTracker\ActivityTrackerRepository;
use ActivityTracker\ActivityTrackerView;

global $mysqli_db;

PageLayout\PageLayout::header();

$repository = new ActivityTrackerRepository($mysqli_db);
$view = new ActivityTrackerView();

$teams = $repository->getTeamActivity();
echo $view->render($teams);

PageLayout\PageLayout::footer();
