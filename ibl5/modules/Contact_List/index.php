<?php

declare(strict_types=1);

/**
 * Contact_List Module - Display GM contact information
 *
 * Shows a table of all teams with GM names and contact details.
 *
 * Refactored to use the interface-driven architecture pattern.
 *
 * @see ContactList\ContactListRepository For database operations
 * @see ContactList\ContactListView For HTML rendering
 */

if (!defined('MODULE_FILE')) {
    die("You can't access this file directly...");
}

use ContactList\ContactListRepository;
use ContactList\ContactListView;

$module_name = basename(dirname(__FILE__));
get_lang($module_name);

$pagetitle = "- IBL GM Contact List";

global $mysqli_db;

// Initialize services
$repository = new ContactListRepository($mysqli_db);
$view = new ContactListView();

// Get contact list data
$contacts = $repository->getAllTeamContacts();

// Render page
Nuke\Header::header();

echo $view->render($contacts);

Nuke\Footer::footer();
