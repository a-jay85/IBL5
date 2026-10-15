<?php

declare(strict_types=1);

$phpSelf = $_SERVER['PHP_SELF'] ?? '';
if (stripos(is_string($phpSelf) ? $phpSelf : '', "modules.php") === false) {
    die("You can't access this file directly...");
}

use Debug\DebugController;

global $authService;
/** @var \Auth\AuthService $authService */

$op = $_REQUEST['op'] ?? '';

switch ($op) {
    case 'toggle_extensions':
        (new DebugController($authService))->handleToggle();
        break;
    default:
        \Utilities\HtmxHelper::redirect('/ibl5/');
}
