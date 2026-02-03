<?php

declare(strict_types=1);

/**
 * Record_Holders Module - Display all-time IBL records
 *
 * Shows record holders for regular season, playoffs, H.E.A.T.,
 * and team records across all IBL history.
 *
 * @see RecordHolders\RecordHoldersView For HTML rendering
 */

if (!defined('MODULE_FILE')) {
    die("You can't access this file directly...");
}

use RecordHolders\RecordHoldersView;

$module_name = basename(dirname(__FILE__));
get_lang($module_name);

$pagetitle = '- Record Holders';

$view = new RecordHoldersView();

Nuke\Header::header();

echo $view->render();

Nuke\Footer::footer();
