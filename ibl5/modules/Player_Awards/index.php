<?php

declare(strict_types=1);

/**
 * Player Awards Module
 *
 * Provides search functionality for player award history.
 *
 * @see PlayerAwards\PlayerAwardsService For business logic
 * @see PlayerAwards\PlayerAwardsView For HTML rendering
 */

if (!defined('MODULE_FILE')) {
    die("You can't access this file directly...");
}

$module_name = basename(dirname(__FILE__));
get_lang($module_name);

$pagetitle = "- $module_name";

global $mysqli_db;

// Initialize classes
$validator = new \PlayerAwards\PlayerAwardsValidator();
$repository = new \PlayerAwards\PlayerAwardsRepository($mysqli_db);
$service = new \PlayerAwards\PlayerAwardsService($validator, $repository);
$view = new \PlayerAwards\PlayerAwardsView($service);

// Get and validate search parameters from POST
$searchResult = $service->search($_POST);

// Render page
Nuke\Header::header();

echo '<h2 class="ibl-title">Player Awards</h2>';
echo $view->renderSearchForm($searchResult['params']);
echo $view->renderTableHeader();

if ($searchResult['count'] > 0) {
    $rowIndex = 0;
    foreach ($searchResult['awards'] as $award) {
        echo $view->renderAwardRow($award, $rowIndex);
        $rowIndex++;
    }
}

echo $view->renderTableFooter();

Nuke\Footer::footer();
