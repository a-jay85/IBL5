<?php

declare(strict_types=1);

/**
 * Contracts Module - Tabbed salary cap and contract display
 *
 * Teams tab (default) shows per-team cap space; Players tab shows the master
 * contract list. Only the active tab's stack is built, so each request runs
 * one tab's queries.
 *
 * @see Contracts\ContractsView For the tab nav wrapper
 * @see CapSpace\CapSpaceService For Teams tab business logic
 * @see ContractList\ContractListService For Players tab business logic
 */

if (!defined('MODULE_FILE')) {
    die("You can't access this file directly...");
}

use CapSpace\CapSpaceRepository;
use CapSpace\CapSpaceService;
use CapSpace\CapSpaceView;
use ContractList\ContractListRepository;
use ContractList\ContractListService;
use ContractList\ContractListView;
use Contracts\ContractsView;

global $mysqli_db;
/** @var \mysqli $mysqli_db */

$module_name = basename(dirname(__FILE__));

$activeTab = ContractsView::resolveTab($_GET['tab'] ?? null);
$pagetitle = '- Contracts: ' . ContractsView::TABS[$activeTab];

if ($activeTab === ContractsView::TAB_PLAYERS) {
    $service = new ContractListService(new ContractListRepository($mysqli_db));
    $tabContentHtml = (new ContractListView())->render($service->getContractsWithCalculations());
} else {
    $season = new \Season\Season($mysqli_db);
    $service = new CapSpaceService(new CapSpaceRepository($mysqli_db), $mysqli_db);
    $displayYears = $service->getDisplayYears($season);
    $tabContentHtml = (new CapSpaceView())->render(
        $service->getTeamsCapData($season),
        $displayYears['beginningYear'],
        $displayYears['endingYear']
    );
}

PageLayout\PageLayout::header();

echo (new ContractsView())->render($activeTab, $tabContentHtml);

PageLayout\PageLayout::footer();
