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

$module_name = basename(dirname(__FILE__));

$pagetitle = "- Master Contract List";

// Initialize services
$factory = \Module\ModuleServices::current()->factory(\Module\Factories\ContractListFactory::class);
$service = $factory->service();
$view = $factory->view();

// Get contract data with calculations
$data = $service->getContractsWithCalculations();

// Render page
PageLayout\PageLayout::header();

echo $view->render($data);

PageLayout\PageLayout::footer();
