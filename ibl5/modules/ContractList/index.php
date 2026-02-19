<?php

declare(strict_types=1);

/**
 * Contract_List Module - Display master contract list
 *
 * Shows a table of all player contracts with year-by-year values,
 * cap totals, and average team cap.
 *
 * Refactored to use the interface-driven architecture pattern.
 *
 * @see ContractList\ContractListRepository For database operations
 * @see ContractList\ContractListService For business logic
 * @see ContractList\ContractListView For HTML rendering
 */

if (!defined('MODULE_FILE')) {
    die("You can't access this file directly...");
}

use ContractList\ContractListRepository;
use ContractList\ContractListService;
use ContractList\ContractListView;

$module_name = basename(dirname(__FILE__));
get_lang($module_name);

$pagetitle = "- Master Contract List";

global $mysqli_db;

// Initialize services
$repository = new ContractListRepository($mysqli_db);
$service = new ContractListService($repository);
$view = new ContractListView();

// Get contract data with calculations
$data = $service->getContractsWithCalculations();

// Render page
PageLayout\PageLayout::header();

echo $view->render($data);

PageLayout\PageLayout::footer();
