<?php

/************************************************************************/
/* PHP-NUKE: Web Portal System                                          */
/* ===========================                                          */
/*                                                                      */
/* Copyright (c) 2005 by Francisco Burzi                                */
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

global $db, $cookie;

$url = "$_SERVER[REQUEST_URI]";

$boxstuff = "<span class=\"content\">";

if (
    $url != "/"
    AND $url != "/index.php"
    AND $url != "/ibl5/"
    AND $url != "/ibl5/index.php"
) {
    $boxstuff .= '<a href="index.php"><img src="images/ibl/logocorner.jpg" border="0"></a>';
}

$boxstuff .= "</span>";

$content = $boxstuff;

// A-Jay's localhost/production switch for development
if ($cookie[1] == "A-Jay") {
    if ($_SERVER['SERVER_NAME'] != "localhost") {
        echo "<a href=\"http://localhost$url\">switch to localhost</a>";
    } elseif ($_SERVER['SERVER_NAME'] == "localhost") {
        echo "<a href=\"https://www.iblhoops.net$url\">switch to production</a>";
    }
}