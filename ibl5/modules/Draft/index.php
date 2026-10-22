<?php

declare(strict_types=1);

/************************************************************************/
/* ibl College Scout Module added by Spencer Cooley                     */
/* 3/22/2005                                                            */
/************************************************************************/

$phpSelfGuard = $_SERVER['PHP_SELF'] ?? '';
if (stripos(is_string($phpSelfGuard) ? $phpSelfGuard : '', "modules.php") === false) {
    die("You can't access this file directly...");
}

$module_name = basename(dirname(__FILE__));

global $mysqli_db, $user;
/** @var \mysqli $mysqli_db */

$httpRequest = \Http\HttpRequest::fromGlobals();
$op = is_string($httpRequest->request('op')) ? $httpRequest->request('op') : '';

$commonRepository = new \Repositories\TeamIdentityRepository($mysqli_db);
$season = \Season\SharedSeason::get($mysqli_db);
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

$postData = [];
foreach ($_POST as $key => $value) {
    $postData[(string) $key] = $value;
}

switch ($op) {
    case 'select':
        echo $controller->submitSelection($postData, $user);
        break;
    default:
        $controller->main($user);
        break;
}
