<?php

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

define('MODULE_FILE', true);
require_once $_SERVER['DOCUMENT_ROOT'] . '/ibl5/mainfile.php';

if (isset($name) && $name == $_REQUEST['name']) {
    $name = trim($name);

    // SECURITY: Validate module name - must be alphanumeric with underscores only
    // Also use basename() to strip any path components
    $name = basename($name);
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $name)) {
        header("Location: index.php");
        exit;
    }

    // Legacy check for path traversal (kept for defense in depth)
    if (str_contains($name, "..")) {
        header("Location: index.php");
        exit;
    }

    // Phase-based access control (replaces nuke_modules query)
    global $mysqli_db, $admin, $leagueContext;
    $season = new Season($mysqli_db);
    $accessControl = new Module\ModuleAccessControl($season, $leagueContext, $mysqli_db);

    if (!$accessControl->isModuleAccessible($name) && !is_admin($admin)) {
        PageLayout\PageLayout::header();
        OpenTable();
        echo "<center>" . _MODULENOTACTIVE . "<br><br>" . _GOBACK . "</center>";
        CloseTable();
        PageLayout\PageLayout::footer();
    } else {
        if (!isset($file) or $file != $_REQUEST['file']) {
            $file = "index";
        }

        // SECURITY: Validate file name - must be alphanumeric with underscores only
        // Also use basename() to strip any path components
        $file = basename($file);
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $file)) {
            die("Invalid file name");
        }

        // Legacy check for path traversal (kept for defense in depth)
        if (str_contains($file, "..")) {
            die("You are so cool...");
        }

        $ThemeSel = get_theme();
        if (file_exists("themes/$ThemeSel/modules/$name/" . $file . ".php")) {
            $modpath = "themes/$ThemeSel/";
        } else {
            $modpath = "";
        }

        $modpath .= "modules/$name/" . $file . ".php";
        if (file_exists($modpath)) {
            include $modpath;
        } else {
            PageLayout\PageLayout::header();
            OpenTable();
            echo "<br><center>Sorry, such file doesn't exist...</center><br>";
            CloseTable();
            PageLayout\PageLayout::footer();
        }
    }
} else {
    header("Location: index.php");
    exit;
}
