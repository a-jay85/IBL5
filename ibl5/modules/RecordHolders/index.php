<?php

declare(strict_types=1);

/**
 * Record_Holders Module - Display all-time IBL records
 *
 * Shows record holders for regular season, playoffs, H.E.A.T.,
 * and team records across all IBL history.
 *
 * @see RecordHolders\RecordHoldersRepository For database queries
 * @see RecordHolders\RecordHoldersService For business logic
 * @see RecordHolders\RecordHoldersView For HTML rendering
 */

if (!defined('MODULE_FILE')) {
    die("You can't access this file directly...");
}

use RecordHolders\RecordHoldersRepository;
use RecordHolders\RecordHoldersService;
use RecordHolders\RecordHoldersView;

$module_name = basename(dirname(__FILE__));
get_lang($module_name);

$pagetitle = '- Record Holders';

global $mysqli_db;

$repository = new RecordHoldersRepository($mysqli_db);
$service = new RecordHoldersService($repository);
$view = new RecordHoldersView();

$records = $service->getAllRecords();

Nuke\Header::header();

echo $view->render($records);

Nuke\Footer::footer();
