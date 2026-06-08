<?php

declare(strict_types=1);

if (stripos($_SERVER['PHP_SELF'], "modules.php") === false) {
    die("You can't access this file directly...");
}

use Debug\DebugController;

global $authService;

$op = $_REQUEST['op'] ?? '';

switch ($op) {
    case 'toggle_extensions':
        (new DebugController($authService))->handleToggle();
        break;
    default:
        \Utilities\HtmxHelper::redirect('/ibl5/');
}
