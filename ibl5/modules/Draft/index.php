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

global $user;

$httpRequest = \Module\ModuleServices::current()->request();
$op = is_string($httpRequest->request('op')) ? $httpRequest->request('op') : '';

$factory = \Module\ModuleServices::current()->factory(\Module\Factories\DraftFactory::class);
$controller = $factory->controller();

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
