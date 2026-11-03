<?php

declare(strict_types=1);

$phpSelfGuard = $_SERVER['PHP_SELF'] ?? '';
if (stripos(is_string($phpSelfGuard) ? $phpSelfGuard : '', "modules.php") === false) {
    die("You can't access this file directly...");
}

$module_name = basename(dirname(__FILE__));

// Legacy globals previously populated by ConfigBootstrap::extractRequestToGlobals().
// PR2 narrowed that extraction to a 2-key allowlist (newlang, redirect), so module
// inputs are now read from $_REQUEST explicitly here.
$op     = is_string($_REQUEST['op']     ?? null) ? $_REQUEST['op']     : '';
$teamidInput = $_REQUEST['teamid'] ?? $_REQUEST['tid'] ?? $_REQUEST['teamID'] ?? null;
$teamid = is_numeric($teamidInput) ? (int) $teamidInput : 0;

$pagetitle = "- Team Pages";

$docRoot = is_string($_SERVER['DOCUMENT_ROOT'] ?? null) ? $_SERVER['DOCUMENT_ROOT'] : '';

$factory = \Module\ModuleServices::current()->factory(\Module\Factories\TeamFactory::class);
$controller = $factory->controller($docRoot . '/ibl5/images/logo');

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
