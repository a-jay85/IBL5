<?php

declare(strict_types=1);

/**
 * Cap_Info Module - Salary cap information display
 *
 * Displays salary cap availability and roster slots for all teams.
 *
 * Refactored to use the interface-driven architecture pattern.
 *
 * @see CapInfo\CapInfoService For business logic
 * @see CapInfo\CapInfoRepository For database operations
 * @see CapInfo\CapInfoView For HTML rendering
 */

if (!defined('MODULE_FILE') && !mb_eregi('modules.php', $_SERVER['PHP_SELF'])) {
    die("You can't access this file directly...");
}

use CapInfo\CapInfoRepository;
use CapInfo\CapInfoService;
use CapInfo\CapInfoView;

global $cookie, $mysqli_db;

$commonRepository = new Services\CommonMysqliRepository($mysqli_db);
$season = new Season($mysqli_db);

$username = strval($cookie[1] ?? '');
$userTeamName = $commonRepository->getTeamnameFromUsername($username);
$userTeamId = $userTeamName ? $commonRepository->getTidFromTeamname($userTeamName) : null;

$module_name = basename(dirname(__FILE__));
get_lang($module_name);

Nuke\Header::header();
OpenTable();

// Initialize services
$repository = new CapInfoRepository($mysqli_db);
$service = new CapInfoService($repository, $mysqli_db);
$view = new CapInfoView();

// Get data
$teamsData = $service->getTeamsCapData($season);
$displayYears = $service->getDisplayYears($season);

// Render output
echo $view->render(
    $teamsData,
    $displayYears['beginningYear'],
    $displayYears['endingYear'],
    $userTeamId
);

CloseTable();
Nuke\Footer::footer();
