<?php

declare(strict_types=1);

/************************************************************************/
/* PHP-NUKE: Web Portal System                                          */
/* ===========================                                          */
/*                                                                      */
/* Copyright (c) 2007 by Francisco Burzi                                */
/* http://phpnuke.org                                                   */
/*                                                                      */
/* This program is free software. You can redistribute it and/or modify */
/* it under the terms of the GNU General Public License as published by */
/* the Free Software Foundation; either version 2 of the License.       */
/************************************************************************/

if (!defined('MODULE_FILE')) {
    die("You can't access this file directly...");
}

if (!defined('INDEX_FILE')) {
    define('INDEX_FILE', true);
}
$module_name = basename(dirname(__FILE__));
get_lang($module_name);

$controller = new \Topics\News\NewsController();

// Legacy globals previously populated by ConfigBootstrap::extractRequestToGlobals().
// PR2 narrowed that extraction to a 2-key allowlist (newlang, redirect), so module
// inputs are now read from $_REQUEST explicitly here.
$op        = is_string($_REQUEST['op']        ?? null) ? $_REQUEST['op']        : '';
$new_topic = is_numeric($_REQUEST['new_topic'] ?? null) ? (int) $_REQUEST['new_topic'] : 0;

switch ($op) {

    default:
        $controller->main($new_topic);
        break;

}
