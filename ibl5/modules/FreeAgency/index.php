<?php

declare(strict_types=1);

use FreeAgency\FreeAgencyController;

/************************************************************************/
/*                     IBL Free Agency Module                           */
/*               (c) July 22, 2005 by Spencer Cooley                    */
/************************************************************************/

if (stripos($_SERVER['PHP_SELF'], "modules.php") === false) {
    die("You can't access this file directly...");
}

$module_name = basename(dirname(__FILE__));
get_lang($module_name);

$pagetitle = "- Free Agency System";

$controller = new FreeAgencyController($mysqli_db);
$controller->handleRequest($user, $pa ?? '', (int) ($pid ?? 0));
