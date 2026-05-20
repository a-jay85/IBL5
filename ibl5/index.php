<?php
/************************************************************************/
/* PHP-NUKE: Advanced Content Management System                         */
/* ============================================                         */
/*                                                                      */
/* Copyright (c) 2007 by Francisco Burzi                                */
/* http://phpnuke.org                                                   */
/*                                                                      */
/* This program is free software. You can redistribute it and/or modify */
/* it under the terms of the GNU General Public License as published by */
/* the Free Software Foundation; either version 2 of the License.       */
/************************************************************************/

require_once __DIR__ . '/mainfile.php';
global $prefix, $db;

$modpath = '';
if (!defined('MODULE_FILE')) {
    define('MODULE_FILE', true);
}
$_SERVER['PHP_SELF'] = "modules.php";
$name = 'News';
define('HOME_FILE', true);

$rawUrl = $_GET['url'] ?? null;
if (is_string($rawUrl) && $rawUrl !== '' && is_admin()) {
    $url = urldecode($rawUrl);
    if (preg_match('#^https?://#i', $url) !== 1) {
        $url = '';
    }
    if ($url !== '') {
        echo "<meta http-equiv=\"refresh\" content=\"0; url=$url\">";
        die();
    }
}

$rawModFile = $_GET['mod_file'] ?? null;
$mod_file = (is_string($rawModFile) && $rawModFile !== '') ? trim(basename($rawModFile)) : 'index';
if (!preg_match('/^[a-zA-Z0-9_]+$/', $mod_file)) {
    $mod_file = 'index';
}

$ThemeSel = 'IBL';
if (file_exists("themes/$ThemeSel/module.php")) {
    include "themes/$ThemeSel/module.php";
    if (isset($default_module) && is_string($default_module)
        && \Module\ModuleRegistry::isValid($default_module)
        && file_exists("modules/$default_module/" . $mod_file . ".php")
    ) {
        $name = $default_module;
    }
}
if (file_exists("themes/$ThemeSel/modules/$name/" . $mod_file . ".php")) {
    $modpath = "themes/$ThemeSel/";
}
$modpath .= "modules/$name/" . $mod_file . ".php";
if (file_exists($modpath)) {
    include $modpath;
} else {
    define('INDEX_FILE', true);
    PageLayout\PageLayout::header();
    OpenTable();
    echo "<center>" . _HOMEPROBLEMUSER . "</center>";
    CloseTable();
    PageLayout\PageLayout::footer();
}
