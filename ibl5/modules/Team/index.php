<?php

declare(strict_types=1);

if (stripos($_SERVER['PHP_SELF'], "modules.php") === false) {
    die("You can't access this file directly...");
}

$module_name = basename(dirname(__FILE__));
get_lang($module_name);

$pagetitle = "- Team Pages";

global $mysqli_db;

$teamid = isset($teamid) ? (int) $teamid : 0;

$controller = new Team\TeamController($mysqli_db);

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
