<?php

declare(strict_types=1);

if (!defined('MODULE_FILE')) {
    die("You can't access this file directly...");
}

$module_name = basename(dirname(__FILE__));
get_lang($module_name);

// Module inputs are read from $_REQUEST explicitly (PR2 narrowed the legacy
// ConfigBootstrap::extractRequestToGlobals() allowlist).
$op = (is_string($_REQUEST['op'] ?? null) && $_REQUEST['op'] !== '') ? $_REQUEST['op'] : '';

$pagetitle = "- Team Pages";

/**
 * Main entry point for the big-board / mock-draft module.
 *
 * @param mixed $user Current user
 */
function bigboard($user)
{
    global $mysqli_db, $op;

    cookiedecode($user);

    $teamIdentityRepo = new Repositories\TeamIdentityRepository($mysqli_db);
    $boardRepo = new BigBoard\BigBoardRepository($mysqli_db);
    $draftOrderRepo = new ProjectedDraftOrder\ProjectedDraftOrderRepository($mysqli_db);
    $draftOrderService = new ProjectedDraftOrder\ProjectedDraftOrderService($draftOrderRepo);
    $mockDraftService = new BigBoard\MockDraftService($draftOrderService, $boardRepo);
    $service = new BigBoard\BigBoardService($teamIdentityRepo, $boardRepo, $mockDraftService);
    $view = new BigBoard\BigBoardView();
    $nukeCompat = new Utilities\NukeCompat();
    $controller = new BigBoard\BigBoardController($service, $view, $nukeCompat, $mysqli_db);
    $controller->handleRequest($user, $op ?? '');
}

bigboard($user);
