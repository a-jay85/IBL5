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

$httpRequest = \Http\HttpRequest::fromGlobals();
$op = is_string($httpRequest->request('op')) ? $httpRequest->request('op') : '';

$commonRepository = new \Repositories\TeamIdentityRepository($mysqli_db);
$season = new \Season\Season($mysqli_db);
$validator = new \Draft\DraftValidator();
$repository = new \Draft\DraftRepository($mysqli_db, $commonRepository);
$processor = new \Draft\DraftProcessor();
$view = new \Draft\DraftView();
$service = new \Draft\DraftService($mysqli_db, $commonRepository, $season);
$nukeCompat = new \Utilities\NukeCompat();
$controller = new \Draft\DraftController(
    $mysqli_db, $commonRepository, $season,
    $validator, $repository, $processor, $view, $service,
    null, null, $nukeCompat
);

switch ($op) {
    case 'select':
        echo $controller->submitSelection($_POST, $user);
        break;
    default:
        $controller->main($user);
        break;
}
