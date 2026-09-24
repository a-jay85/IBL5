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

use Cache\DatabaseCache;
use RecordHolders\RecordHoldersRepository;
use RecordHolders\RecordHoldersService;
use RecordHolders\CachedRecordHoldersService;
use RecordHolders\RecordHoldersView;

$module_name = basename(dirname(__FILE__));
get_lang($module_name);

$rawOp = $_GET['op'] ?? null;
$op = (is_string($rawOp) && $rawOp === 'allstar') ? 'allstar' : 'records';

$pagetitle = $op === 'allstar' ? '- All-Star Appearances' : '- Record Holders';

global $mysqli_db, $leagueContext;

PageLayout\PageLayout::header();

if ($op === 'allstar') {
    $appearancesRepository = new \AllStarAppearances\AllStarAppearancesRepository($mysqli_db);
    $appearancesView = new \AllStarAppearances\AllStarAppearancesView();
    echo $appearancesView->render($appearancesRepository->getAllStarAppearances());
} else {
    $repository = new RecordHoldersRepository($mysqli_db, $leagueContext);
    $innerService = new RecordHoldersService($repository);
    $cache = new DatabaseCache($mysqli_db);
    $service = new CachedRecordHoldersService($innerService, $cache);
    $view = new RecordHoldersView();
    echo $view->render($service->getAllRecords());
}

PageLayout\PageLayout::footer();
