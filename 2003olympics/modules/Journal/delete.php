<?php

/************************************************************************/
/* Journal &#167 ZX                                                     */
/* ================                                                     */
/*                                                                      */
/* Original work done by Joseph Howard known as Member's Journal, which */
/* was based on Trevor Scott's vision of Atomic Journal.                */
/*                                                                      */
/* Modified on 25 May 2002 by Paul Laudanski (paul@computercops.biz)    */
/* Copyright (c) 2002 Modifications by Computer Cops.                   */
/* http://computercops.biz                                              */
/*                                                                      */
/* Required: PHPNuke 5.5 ( http://www.phpnuke.org/ ) and phpbb2         */
/* ( http://bbtonuke.sourceforge.net/ ) forums port.                    */
/*                                                                      */
/* Member's Journal did not work on a PHPNuke 5.5 portal which had      */
/* phpbb2 port integrated.  Thus was Journal &#167 ZX created with the  */
/* Member's Journal author's blessings.                                 */
/*                                                                      */
/* To install, backup everything first and then FTP the Journal package */
/* files into your site's module directory.  Also run the tables.sql    */
/* script so the proper tables and fields can be created and used.  The */
/* default table prefix is "nuke" which is hard-coded throughout the    */
/* entire system as a left-over from Member's Journal.  If a demand     */
/* exists, that can be changed for a future release.                    */
/*                                                                      */
/* This program is free software. You can redistribute it and/or modify */
/* it under the terms of the GNU General Public License as published by */
/* the Free Software Foundation; either version 2 of the License.       */
/************************************************************************/
/* Journal 2.0 Enhanced and Debugged 2004                               */
/* by sixonetonoffun -- http://www.netflake.com --                      */
/* Images Created by GanjaUK -- http://www.GanjaUK.com                  */
/************************************************************************/
if (!defined('MODULE_FILE')) {
    die("You can't access this file directly...");
}

require_once "mainfile.php";
$module_name = basename(dirname(__FILE__));
get_lang($module_name);

if (!isset($jid) or !is_numeric($jid)) {die("No journal specified.");}
$pagetitle = "- " . _USERSJOURNAL . "";
NukeHeader::header();
include "modules/$module_name/functions.php";
if (is_user($user) || is_admin($admin)) {
    cookiedecode($user);
    $username = $cookie[1];
    $username = filter($username, "nohtml");
    $sitename = filter($sitename, "nohtml");
    $debug = filter($debug, "nohtml");
    if ($debug == "true"):
        echo ("UserName:$username<br>SiteName: $sitename");
    endif;
    $jid = intval($jid);
    startjournal($sitename, $user);
    echo ("<br>");
    OpenTable();
    echo ("<div align=center class=title><strong>" . _ABOUTTODELETE . "</strong><br><br><img src=\"modules/$module_name/images/trash.gif\">&nbsp;&nbsp;&nbsp;<img src=\"modules/$module_name/images/trash.gif\">&nbsp;&nbsp;&nbsp;<img src=\"modules/$module_name/images/trash.gif\"></div>");
    echo ("<br><div align=center>" . _SUREDELJOURNAL . "<br><br>[ <a href=\"modules.php?name=$module_name&file=deleteyes&jid=$jid\">" . _YES . "</a> | <a href=\"modules.php?name=$module_name&file=edit\">" . _NO . "</a> ]</div><br><br>");
    echo ("<div align=center>" . _YOUCANTSAVE . "</div>");
    CloseTable();
    journalfoot();
}
if (!is_user($user) && !is_admin($admin)) {
    $pagetitle = "- " . _YOUMUSTBEMEMBER . "";
    $pagetitle = filter($pagetitle, "nohtml");
    OpenTable();
    echo "<center><b>" . _YOUMUSTBEMEMBER . "</b></center>";
    CloseTable();
    include "footer.php";
    die();
}
