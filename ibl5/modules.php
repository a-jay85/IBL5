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
    $name = addslashes(trim($name));
    $modstring = strtolower($_SERVER['QUERY_STRING']);
    if (stripos_clone($name, "..") or ((stripos_clone($modstring, "&file=nickpage") || stripos_clone($modstring, "&user=")) and ($name == "Forums" or $name == "Members_List"))) {
        header("Location: index.php");
    }

    global $nukeuser, $db, $prefix, $user;
    if (is_user($user)) {
        $nukeuser = base64_decode($user);
        $nukeuser = addslashes($nukeuser);
    } else {
        $nukeuser = "";
    }
    $result = $db->sql_query("SELECT active, view FROM " . $prefix . "_modules WHERE title='" . addslashes($name) . "'");
    list($mod_active, $view) = $db->sql_fetchrow($result);
    $mod_active = intval($mod_active);
    $view = intval($view);
    if (($mod_active == 1) or ($mod_active == 0 and is_admin($admin))) {
        if (!isset($mop) or $mop != $_REQUEST['mop']) {
            $mop = "modload";
        }

        if (!isset($file) or $file != $_REQUEST['file']) {
            $file = "index";
        }

        if (stripos_clone($file, "..") or stripos_clone($mop, "..")) {
            die("You are so cool...");
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
            $result2 = $db->sql_query("SELECT mod_group FROM " . $prefix . "_modules WHERE title='" . addslashes($name) . "'");
            list($mod_group) = $db->sql_fetchrow($result2);
            if ($mod_group != 0) {
                $result3 = $db->sql_query("SELECT name FROM " . $prefix . "_groups WHERE id='" . intval($mod_group) . "'");
                $row3 = $db->sql_fetchrow($result3);
                echo _ADDITIONALYGRP . ": <b>" . $row3['name'] . "</b><br><br>";
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
