<?php

declare(strict_types=1);

/************************************************************************/
/* ibl College Scout Module added by Spencer Cooley                     */
/* 3/22/2005                                                            */
/************************************************************************/

if (stripos($_SERVER['PHP_SELF'], "modules.php") === false) {
    die("You can't access this file directly...");
}

$module_name = basename(dirname(__FILE__));
get_lang($module_name);

global $mysqli_db, $user;

$op = is_string($_REQUEST['op'] ?? null) ? $_REQUEST['op'] : '';

$commonRepository = new \Repositories\TeamIdentityRepository($mysqli_db);
$season = new \Season\Season($mysqli_db);
$controller = new \Draft\DraftController($mysqli_db, $commonRepository, $season);

switch ($op) {
    case 'select':
        echo $controller->submitSelection($_POST, $user);
        break;
    default:
        $controller->main($user);
        break;
}
