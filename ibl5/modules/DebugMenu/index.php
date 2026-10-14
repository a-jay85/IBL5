<?php

declare(strict_types=1);

$phpSelf = $_SERVER['PHP_SELF'] ?? '';
if (stripos(is_string($phpSelf) ? $phpSelf : '', "modules.php") === false) {
    die("You can't access this file directly...");
}

$op = $_REQUEST['op'] ?? '';

$factory = \Module\ModuleServices::current()->factory(\Module\Factories\DebugMenuFactory::class);

switch ($op) {
    case 'toggle_extensions':
        $factory->controller()->handleToggle();
        break;
    default:
        \Utilities\HtmxHelper::redirect('/ibl5/');
}
