<?php

declare(strict_types=1);

/**
 * Transaction_History Module - Display transaction history with filters
 *
 * Shows waiver pool moves, trades, contract extensions, free agency signings,
 * rookie extensions, and position changes with category/year/month filtering.
 *
 * @see TransactionHistory\TransactionHistoryRepository For database operations
 * @see TransactionHistory\TransactionHistoryService For business logic
 * @see TransactionHistory\TransactionHistoryView For HTML rendering
 */

if (!defined('MODULE_FILE')) {
    die("You can't access this file directly...");
}

use TransactionHistory\TransactionHistoryRepository;
use TransactionHistory\TransactionHistoryService;
use TransactionHistory\TransactionHistoryView;

$module_name = basename(dirname(__FILE__));
get_lang($module_name);

global $mysqli_db;

$pagetitle = '- Transaction History';

// Initialize services
$repository = new TransactionHistoryRepository($mysqli_db);
$service = new TransactionHistoryService($repository);
$view = new TransactionHistoryView();

// Get page data with filters from query string
$pageData = $service->getPageData($_GET);

// Render page
Nuke\Header::header();

echo $view->render($pageData);

Nuke\Footer::footer();
