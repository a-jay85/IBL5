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

use Contracts\ContractsView;

$module_name = basename(dirname(__FILE__));

$activeTab = ContractsView::resolveTab($_GET['tab'] ?? null);
$pagetitle = '- Contracts: ' . ContractsView::TABS[$activeTab];

if ($activeTab === ContractsView::TAB_PLAYERS) {
    $factory = \Module\ModuleServices::current()->factory(\Module\Factories\ContractListFactory::class);
    $service = $factory->service();
    $tabContentHtml = $factory->view()->render($service->getContractsWithCalculations());
} else {
    $factory = \Module\ModuleServices::current()->factory(\Module\Factories\CapSpaceFactory::class);
    $season = $factory->season();
    $service = $factory->service();
    $displayYears = $service->getDisplayYears($season);
    $tabContentHtml = $factory->view()->render(
        $service->getTeamsCapData($season),
        $displayYears['beginningYear'],
        $displayYears['endingYear']
    );
}

PageLayout\PageLayout::header();

echo \Module\ModuleServices::current()->factory(\Module\Factories\ContractsFactory::class)->view()->render($activeTab, $tabContentHtml);

PageLayout\PageLayout::footer();
