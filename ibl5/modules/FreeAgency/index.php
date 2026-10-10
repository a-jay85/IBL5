<?php

declare(strict_types=1);

/************************************************************************/
/*                     IBL Free Agency Module                           */
/*               (c) July 22, 2005 by Spencer Cooley                    */
/************************************************************************/

if (stripos($_SERVER['PHP_SELF'], "modules.php") === false) {
    die("You can't access this file directly...");
}

$module_name = basename(dirname(__FILE__));

$pagetitle = "- Free Agency System";

// Legacy globals previously populated by ConfigBootstrap::extractRequestToGlobals().
// PR2 narrowed that extraction to a 2-key allowlist (newlang, redirect), so module
// inputs are now read from $_REQUEST explicitly here.
$pa  = is_string($_REQUEST['pa']  ?? null) ? $_REQUEST['pa']  : '';
$pid = is_numeric($_REQUEST['pid'] ?? null) ? (int) $_REQUEST['pid'] : 0;

$factory = \Module\ModuleServices::current()->factory(\Module\Factories\FreeAgencyFactory::class);
$factory->controller()->handleRequest($user, $pa, $pid);
