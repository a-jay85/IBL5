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
define('MODULE_FILE', true);
$_SERVER['PHP_SELF'] = "modules.php";
$name = 'News';
define('HOME_FILE', true);

if (isset($url) and is_admin()) {
    $url = urldecode($url);
    echo "<meta http-equiv=\"refresh\" content=\"0; url=$url\">";
    die();
}

if (!isset($mop)) {$mop = "modload";}
if (!isset($mod_file)) {$mod_file = "index";}
$name = trim($name);
if (isset($file)) {$file = trim($file);}
$mod_file = trim($mod_file);
$mop = trim($mop);
if (str_contains($name, "..") || (isset($file) && str_contains($file, "..")) || str_contains($mod_file, "..") || str_contains($mop, "..")) {
    die("You are so cool...");
} else {
    $ThemeSel = get_theme();
    if (file_exists("themes/$ThemeSel/module.php")) {
        include "themes/$ThemeSel/module.php";
        if (is_active("$default_module") and file_exists("modules/$default_module/" . $mod_file . ".php")) {
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
}
