<?php

declare(strict_types=1);

if (!defined('MODULE_FILE')) {
    die("You can't access this file directly...");
}

$module_name = basename(dirname(__FILE__));

global $user;

$pagetitle = "- Team Pages";

cookiedecode($user);

$factory = \Module\ModuleServices::current()->factory(\Module\Factories\WaiversFactory::class);
$factory->controller()->handleWaiverRequest($user);
