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

PageLayout\PageLayout::header();

$factory = \Module\ModuleServices::current()->factory(\Module\Factories\ActivityTrackerFactory::class);
$repository = $factory->repository();
$view = $factory->view();

$teams = $repository->getTeamActivity();
echo $view->render($teams);

PageLayout\PageLayout::footer();
