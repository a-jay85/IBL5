<?php

declare(strict_types=1);

/**
 * Team_Schedule Module - Redirect to unified Schedule module
 *
 * This module now redirects to the Schedule module with the teamID parameter.
 * The Schedule module handles both league-wide and team-specific schedules.
 *
 * @deprecated Use modules.php?name=Schedule&teamID=X instead
 * @see modules/Schedule/index.php For the unified schedule module
 */

if (!defined('MODULE_FILE')) {
    die("You can't access this file directly...");
}

global $cookie, $mysqli_db;

// Get team ID from request or user's team
$teamID = isset($_GET['teamID']) ? (int)$_GET['teamID'] : 0;

if (!$teamID && !empty($cookie[1])) {
    $commonRepository = new \Services\CommonMysqliRepository($mysqli_db);
    $userTeamName = $commonRepository->getTeamnameFromUsername(strval($cookie[1] ?? ''));
    $teamID = $commonRepository->getTidFromTeamname($userTeamName);
}

// Redirect to unified Schedule module
$redirectUrl = 'modules.php?name=Schedule';
if ($teamID > 0) {
    $redirectUrl .= '&teamID=' . $teamID;
}

header('Location: ' . $redirectUrl);
exit;
