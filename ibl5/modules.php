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

    global $nukeuser, $db, $mysqli_db, $prefix, $user;
    if (is_user($user)) {
        $nukeuser = base64_decode($user);
        $nukeuser = addslashes($nukeuser);
    } else {
        $nukeuser = "";
    }
    $stmt = $mysqli_db->prepare("SELECT active, view FROM " . $prefix . "_modules WHERE title = ?");
    $stmt->bind_param('s', $name);
    $stmt->execute();
    $modResult = $stmt->get_result();
    $modRow = $modResult->fetch_row();
    $mod_active = (int)($modRow[0] ?? 0);
    $view = (int)($modRow[1] ?? 0);
    $stmt->close();
    if (($mod_active == 1) or ($mod_active == 0 and is_admin($admin))) {
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

        // Check if module is enabled for current league
        global $leagueContext;
        if (isset($leagueContext) && !$leagueContext->isModuleEnabled($name)) {
            // Redirect to homepage if module not available for current league
            header('Location: index.php');
            exit;
        }

        $ThemeSel = get_theme();
        if (file_exists("themes/$ThemeSel/modules/$name/" . $file . ".php")) {
            $modpath = "themes/$ThemeSel/";
        } else {
            $modpath = "";
        }
        if ($view == 0) {
            $modpath .= "modules/$name/" . $file . ".php";
            if (file_exists($modpath)) {
                include $modpath;
            } else {
                Nuke\Header::header();
                OpenTable();
                echo "<br><center>Sorry, such file doesn't exist...</center><br>";
                CloseTable();
                Nuke\Footer::footer();
            }
        } elseif ($view == 1 and (is_user($user) or is_group($user, $name)) or is_admin($admin)) {
            $modpath .= "modules/$name/" . $file . ".php";
            if (file_exists($modpath)) {
                include $modpath;
            } else {
                Nuke\Header::header();
                OpenTable();
                echo "<br><center>Sorry, such file doesn't exist...</center><br>";
                CloseTable();
                Nuke\Footer::footer();
            }
        } elseif ($view == 1 and !is_user($user) and !is_admin($admin)) {
            $pagetitle = "- " . _ACCESSDENIED;
            Nuke\Header::header();
            title($sitename . ": " . _ACCESSDENIED);
            OpenTable();
            echo "<center><strong>" . _RESTRICTEDAREA . "</strong><br><br>" . _MODULEUSERS;
            $stmt2 = $mysqli_db->prepare("SELECT mod_group FROM " . $prefix . "_modules WHERE title = ?");
            $stmt2->bind_param('s', $name);
            $stmt2->execute();
            $modGroupResult = $stmt2->get_result();
            $modGroupRow = $modGroupResult->fetch_row();
            $mod_group = (int)($modGroupRow[0] ?? 0);
            $stmt2->close();
            if ($mod_group !== 0) {
                $stmt3 = $mysqli_db->prepare("SELECT name FROM " . $prefix . "_groups WHERE id = ?");
                $stmt3->bind_param('i', $mod_group);
                $stmt3->execute();
                $groupResult = $stmt3->get_result();
                $row3 = $groupResult->fetch_assoc();
                $stmt3->close();
                echo _ADDITIONALYGRP . ": <b>" . \Utilities\HtmlSanitizer::safeHtmlOutput($row3['name']) . "</b><br><br>";
            }
            echo _GOBACK;
            CloseTable();
            Nuke\Footer::footer();
        } elseif ($view == 2 and is_admin($admin)) {
            $modpath .= "modules/$name/" . $file . ".php";
            if (file_exists($modpath)) {
                include $modpath;
            } else {
                Nuke\Header::header();
                OpenTable();
                echo "<br><center>Sorry, such file doesn't exist...</center><br>";
                CloseTable();
                Nuke\Footer::footer();
            }
        } elseif ($view == 2 and !is_admin($admin)) {
            $pagetitle = "- " . _ACCESSDENIED;
            Nuke\Header::header();
            title($sitename . ": " . _ACCESSDENIED);
            OpenTable();
            echo "<center><b>" . _RESTRICTEDAREA . "</b><br><br>" . _MODULESADMINS . "" . _GOBACK;
            CloseTable();
            Nuke\Footer::footer();
        } elseif ($view == 3 and paid()) {
            $modpath .= "modules/$name/" . $file . ".php";
            if (file_exists($modpath)) {
                include $modpath;
            } else {
                Nuke\Header::header();
                OpenTable();
                echo "<br><center>Sorry, such file doesn't exist...</center><br>";
                CloseTable();
                Nuke\Footer::footer();
            }
        } else {
            $pagetitle = "- " . _ACCESSDENIED . "";
            Nuke\Header::header();
            title($sitename . ": " . _ACCESSDENIED . "");
            OpenTable();
            echo "<center><strong>" . _RESTRICTEDAREA . "</strong><br><br>" . _MODULESSUBSCRIBER;
            if (!empty($subscription_url)) {
                echo "<br>" . _SUBHERE;
            }

            echo "<br><br>" . _GOBACK;
            CloseTable();
            Nuke\Footer::footer();
        }
    } else {
        Nuke\Header::header();
        OpenTable();
        echo "<center>" . _MODULENOTACTIVE . "<br><br>" . _GOBACK . "</center>";
        CloseTable();
        Nuke\Footer::footer();
    }
} else {
    header("Location: index.php");
    exit;
}
