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

if (stristr(htmlentities($_SERVER['PHP_SELF']), "meta.php")) {
    Header("Location: ../index.php");
    die();
}

global $commercial_license, $sitename, $slogan;
##################################################
# Include for Meta Tags generation               #
##################################################

$metastring = "<META HTTP-EQUIV=\"Content-Type\" CONTENT=\"text/html; charset=" . _CHARSET . "\">\n";
$metastring .= "<meta id=\"viewport-meta\" name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\n";
$metastring .= "<script>(function(){try{if(localStorage.getItem('ibl_desktop_view')==='1'){document.getElementById('viewport-meta').setAttribute('content','width=1024');document.documentElement.classList.add('desktop-view-active');}}catch(e){}})()</script>\n";
$metastring .= "<META HTTP-EQUIV=\"EXPIRES\" CONTENT=\"0\">\n";
$metastring .= "<META NAME=\"RESOURCE-TYPE\" CONTENT=\"DOCUMENT\">\n";
$metastring .= "<META NAME=\"DISTRIBUTION\" CONTENT=\"GLOBAL\">\n";
$metastring .= "<META NAME=\"AUTHOR\" CONTENT=\"$sitename\">\n";
$metastring .= "<META NAME=\"COPYRIGHT\" CONTENT=\"Copyright (c) by $sitename\">\n";
$metastring .= "<META NAME=\"KEYWORDS\" CONTENT=\"basketball, fantasy basketball, basketball league, IBL, Internet Basketball League, basketball simulation, basketball stats, NBA, basketball draft, free agency, basketball trading, basketball standings, basketball schedule\">\n";
$metastring .= "<META NAME=\"DESCRIPTION\" CONTENT=\"$slogan\">\n";
$metastring .= "<META NAME=\"ROBOTS\" CONTENT=\"INDEX, FOLLOW\">\n";
$metastring .= "<META NAME=\"REVISIT-AFTER\" CONTENT=\"1 DAYS\">\n";
$metastring .= "<META NAME=\"RATING\" CONTENT=\"GENERAL\">\n";

echo $metastring;
