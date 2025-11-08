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

if (!defined('BLOCK_FILE')) {
    Header("Location: ../index.php");
    die();
}

global $user, $cookie, $prefix, $db, $user_prefix;

cookiedecode($user);
if (isset($_SERVER['REMOTE_ADDR'])) {$ip = $_SERVER['REMOTE_ADDR'];}
if (is_user($user)) {
    $uname = $cookie[1];
    $guest = 0;
} else {
    if (!empty($ip)) {
        $uname = $ip;
    } else {
        $uname = "";
    }
    $guest = 1;
}

$guest_online_num = $db->sql_numrows($db->sql_query("SELECT * FROM " . $prefix . "_session WHERE guest='1'"));
$member_online_num = $db->sql_numrows($db->sql_query("SELECT * FROM " . $prefix . "_session WHERE guest='0'"));

$who_online_num = $guest_online_num + $member_online_num;
$who_online = "<div style='padding:10px'><div align=\"center\"><span class=\"content\">" . _CURRENTLY . " $guest_online_num " . _GUESTS . " $member_online_num " . _MEMBERS . "<br>";

$content = "$who_online";

$row2 = $db->sql_fetchrow($db->sql_query("SELECT title FROM " . $prefix . "_blocks WHERE bkey='online'"));
$title = filter($row2['title'], "nohtml");

if (is_user($user)) {
    $content .= "<br>" . _YOUARELOGGED . " <b>$uname</b>.<br>";
    $content .= "</span></div>";
} else {
    $content .= "<br>" . _YOUAREANON . "</span></div>";
}
$content .= "</div>";
