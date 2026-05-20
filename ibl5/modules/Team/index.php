<?php

declare(strict_types=1);

if (stripos($_SERVER['PHP_SELF'], "modules.php") === false) {
    die("You can't access this file directly...");
}

$module_name = basename(dirname(__FILE__));
get_lang($module_name);

// Legacy globals previously populated by ConfigBootstrap::extractRequestToGlobals().
// PR2 narrowed that extraction to a 2-key allowlist (newlang, redirect), so module
// inputs are now read from $_REQUEST explicitly here.
$op     = is_string($_REQUEST['op']     ?? null) ? $_REQUEST['op']     : '';
$teamid = is_numeric($_REQUEST['teamid'] ?? null) ? (int) $_REQUEST['teamid'] : 0;

$pagetitle = "- Team Pages";

global $mysqli_db, $authService, $leagueContext;

$commonRepo = new \Repositories\TeamIdentityRepository($mysqli_db);
$controller = new Team\TeamController($mysqli_db, $commonRepo, $authService, $leagueContext);

switch ($op) {
    case "team":
        $controller->displayTeamPage($teamid);
        break;

    case "api":
        $handler = new Team\TeamApiHandler($mysqli_db);
        $handler->handle();
        break;

    default:
        $controller->displayMenu();
        break;
}
