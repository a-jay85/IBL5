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

require_once $_SERVER['DOCUMENT_ROOT'] . '/ibl5/mainfile.php';
global $prefix, $db, $admin_file;

$modpath = '';
define('MODULE_FILE', true);
$_SERVER['PHP_SELF'] = "modules.php";
$row = $db->sql_fetchrow($db->sql_query("SELECT main_module from " . $prefix . "_main"));
$name = $row['main_module'];
define('HOME_FILE', true);

if (isset($url) and is_admin($admin)) {
    $url = urldecode($url);
    echo "<meta http-equiv=\"refresh\" content=\"0; url=$url\">";
    die();
}

if ($httpref == 1) {
    if (isset($_SERVER['HTTP_REFERER'])) {
        $referer = $_SERVER['HTTP_REFERER'];
        $referer = check_html($referer, "nohtml");
        if (stripos($referer, "nuke_") !== false && stripos($referer, "into") !== false && stripos($referer, "from") !== false) {
            $referer = "";
        }
    }
    if (!empty($referer) && !str_contains($referer, "unknown") && !str_contains($referer, "bookmark") && !str_contains($referer, $_SERVER['HTTP_HOST'])) {
        $result = $db->sql_query("INSERT INTO " . $prefix . "_referer VALUES (NULL, '" . $referer . "')");
    }
    $numrows = $db->sql_numrows($db->sql_query("SELECT * FROM " . $prefix . "_referer"));
    if ($numrows >= $httprefmax) {
        $result2 = $db->sql_query("DELETE FROM " . $prefix . "_referer");
    }
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
        if (is_admin($admin)) {
            echo "<center><font class=\"\"><b>" . _HOMEPROBLEM . "</b></font><br><br>[ <a href=\"" . $admin_file . ".php?op=modules\">" . _ADDAHOME . "</a> ]</center>";
        } else {
            echo "<center>" . _HOMEPROBLEMUSER . "</center>";
        }
        CloseTable();
        PageLayout\PageLayout::footer();
    }
}
