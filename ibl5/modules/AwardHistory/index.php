<?php

declare(strict_types=1);

/**
 * Award History Module
 *
 * Provides search functionality for player award history.
 *
 * @see AwardHistory\AwardHistoryService For business logic
 * @see AwardHistory\AwardHistoryView For HTML rendering
 */

if (!defined('MODULE_FILE')) {
    die("You can't access this file directly...");
}

$module_name = basename(dirname(__FILE__));
get_lang($module_name);

$pagetitle = "- $module_name";

global $mysqli_db;

// Initialize classes
$validator = new \AwardHistory\AwardHistoryValidator();
$repository = new \AwardHistory\AwardHistoryRepository($mysqli_db);
$service = new \AwardHistory\AwardHistoryService($validator, $repository);
$view = new \AwardHistory\AwardHistoryView($service);

// Get and validate search parameters from POST
$searchResult = $service->search($_POST);

// Render page
PageLayout\PageLayout::header();

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

PageLayout\PageLayout::footer();
