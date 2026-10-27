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

$module_name = basename(dirname(__FILE__));

$pagetitle = "- IBL GM Contact List";

// Initialize services
$factory = \Module\ModuleServices::current()->factory(\Module\Factories\GMContactListFactory::class);
$repository = $factory->repository();
$view = $factory->view();

// Get contact list data
$contacts = $repository->getAllTeamContacts();

// Render page
PageLayout\PageLayout::header();

echo $view->render($contacts);

PageLayout\PageLayout::footer();
