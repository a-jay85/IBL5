<?php

declare(strict_types=1);

/**
 * GMContactList Module - Display GM contact information
 *
 * Shows a table of all teams with GM names and contact details.
 *
 * Refactored to use the interface-driven architecture pattern.
 *
 * @see GMContactList\GMContactListRepository For database operations
 * @see GMContactList\GMContactListView For HTML rendering
 */

if (!defined('MODULE_FILE')) {
    die("You can't access this file directly...");
}

use GMContactList\GMContactListRepository;
use GMContactList\GMContactListView;

$module_name = basename(dirname(__FILE__));
get_lang($module_name);

$pagetitle = "- IBL GM Contact List";

global $mysqli_db;

// Initialize services
$repository = new GMContactListRepository($mysqli_db);
$view = new GMContactListView();

// Get contact list data
$contacts = $repository->getAllTeamContacts();

// Render page
Nuke\Header::header();

echo $view->render($contacts);

Nuke\Footer::footer();
