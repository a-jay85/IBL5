<?php

if (!mb_eregi("modules.php", $_SERVER['PHP_SELF'])) {
    die("You can't access this file directly...");
}

$module_name = basename(dirname(__FILE__));
get_lang($module_name);

$pagetitle = "- Team Pages";

global $mysqli_db;

$teamID = isset($teamID) ? (int) $teamID : 0;

$controller = new Team\TeamController($mysqli_db);

switch ($op) {
    case "team":
        $controller->displayTeamPage($teamID);
        break;

    default:
        $controller->displayMenu();
        break;
}
