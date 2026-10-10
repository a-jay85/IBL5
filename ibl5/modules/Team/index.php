<?php

declare(strict_types=1);

if (stripos($_SERVER['PHP_SELF'], "modules.php") === false) {
    die("You can't access this file directly...");
}

$module_name = basename(dirname(__FILE__));

// Legacy globals previously populated by ConfigBootstrap::extractRequestToGlobals().
// PR2 narrowed that extraction to a 2-key allowlist (newlang, redirect), so module
// inputs are now read from $_REQUEST explicitly here.
$op     = is_string($_REQUEST['op']     ?? null) ? $_REQUEST['op']     : '';
$teamid = is_numeric($_REQUEST['teamid'] ?? null) ? (int) $_REQUEST['teamid'] : 0;

$pagetitle = "- Team Pages";

$factory = \Module\ModuleServices::current()->factory(\Module\Factories\TeamFactory::class);
$controller = $factory->controller();

switch ($op) {
    case "team":
        $controller->displayTeamPage($teamid);
        break;

    case "api":
        $handler = $factory->apiHandler();
        $handler->handle();
        break;

    default:
        $controller->displayMenu();
        break;
}
