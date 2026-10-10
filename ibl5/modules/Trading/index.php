<?php

declare(strict_types=1);

if (stripos($_SERVER['PHP_SELF'], "modules.php") === false) {
    die("You can't access this file directly...");
}

$module_name = basename(dirname(__FILE__));

// Legacy globals previously populated by ConfigBootstrap::extractRequestToGlobals().
// PR2 narrowed that extraction to a 2-key allowlist (newlang, redirect), so module
// inputs are now read from $_REQUEST explicitly here.
$op      = is_string($_REQUEST['op']      ?? null) ? $_REQUEST['op']      : '';
$partner = is_string($_REQUEST['partner'] ?? null) ? $_REQUEST['partner'] : null;

$pagetitle = "- Team Pages";

$serverName = is_string($_SERVER['SERVER_NAME'] ?? null) ? $_SERVER['SERVER_NAME'] : '';
$factory = \Module\ModuleServices::current()->factory(\Module\Factories\TradingFactory::class);
$controller = $factory->controller($serverName);

switch ($op) {
    case "reviewtrade":
        $controller->handleTradeReview($user);
        break;
    case "offertrade":
        $controller->handleTradeOffer($user, $partner);
        break;
    case "roster-preview-api":
        $controller->handleRosterPreviewApi($user);
        break;
    default:
        $controller->handleTradeReview($user);
        break;
}
