<?php

declare(strict_types=1);

/**
 * Record_Holders Module - Display all-time IBL records
 *
 * Shows record holders for regular season, playoffs, H.E.A.T.,
 * and team records across all IBL history. With op=allstar, shows
 * the full all-star appearances list.
 *
 * @see RecordHolders\RecordHoldersRepository For database queries
 * @see RecordHolders\RecordHoldersService For business logic
 * @see RecordHolders\RecordHoldersView For HTML rendering
 * @see AllStarAppearances\AllStarAppearancesRepository For all-star queries
 * @see AllStarAppearances\AllStarAppearancesView For all-star rendering
 */

if (!defined('MODULE_FILE')) {
    die("You can't access this file directly...");
}

$module_name = basename(dirname(__FILE__));

$rawOp = $_GET['op'] ?? null;
$op = (is_string($rawOp) && $rawOp === 'allstar') ? 'allstar' : 'records';

$pagetitle = $op === 'allstar' ? '- All-Star Appearances' : '- Record Holders';

PageLayout\PageLayout::header();

$factory = \Module\ModuleServices::current()->factory(\Module\Factories\RecordHoldersFactory::class);

if ($op === 'allstar') {
    $appearancesRepository = $factory->allStarRepository();
    $appearancesView = $factory->allStarView();
    echo $appearancesView->render($appearancesRepository->getAllStarAppearances());
} else {
    $service = $factory->service();
    $view = $factory->view();
    echo $view->render($service->getAllRecords());
}

PageLayout\PageLayout::footer();
