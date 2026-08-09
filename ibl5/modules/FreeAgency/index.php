<?php

declare(strict_types=1);

use FreeAgency\FreeAgencyController;

/************************************************************************/
/*                     IBL Free Agency Module                           */
/*               (c) July 22, 2005 by Spencer Cooley                    */
/************************************************************************/

if (stripos($_SERVER['PHP_SELF'], "modules.php") === false) {
    die("You can't access this file directly...");
}

$module_name = basename(dirname(__FILE__));
get_lang($module_name);

$pagetitle = "- Free Agency System";

global $authService;

// Legacy globals previously populated by ConfigBootstrap::extractRequestToGlobals().
// PR2 narrowed that extraction to a 2-key allowlist (newlang, redirect), so module
// inputs are now read from $_REQUEST explicitly here.
$pa  = is_string($_REQUEST['pa']  ?? null) ? $_REQUEST['pa']  : '';
$pid = is_numeric($_REQUEST['pid'] ?? null) ? (int) $_REQUEST['pid'] : 0;

$commonRepo = new Repositories\TeamIdentityRepository($mysqli_db);
$repository = new FreeAgency\FreeAgencyRepository($mysqli_db);
$demandRepository = new FreeAgency\FreeAgencyDemandRepository($mysqli_db);
$tableRenderer = new FreeAgency\FreeAgencyTableRendererView($commonRepo);
$service = new FreeAgency\FreeAgencyService($repository, $demandRepository, $mysqli_db);
$view = new FreeAgency\FreeAgencyView(
    new FreeAgency\FreeAgencyUnderContractSectionView($tableRenderer),
    new FreeAgency\FreeAgencyContractOffersSectionView($tableRenderer),
    new FreeAgency\FreeAgencyTeamFreeAgentsSectionView($tableRenderer),
    new FreeAgency\FreeAgencyOtherFreeAgentsSectionView($tableRenderer)
);
$processor = new FreeAgency\FreeAgencyProcessor($mysqli_db, $commonRepo);
$controller = new FreeAgencyController($mysqli_db, $commonRepo, $authService, $service, $view, $processor);
$controller->handleRequest($user, $pa, $pid);
