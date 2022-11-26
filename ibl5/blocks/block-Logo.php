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

$actual_url = "$_SERVER[REQUEST_URI]";

$boxstuff = "<span class=\"content\">";

if (
    $actual_url != "/"
    AND $actual_url != "/index.php"
    AND $actual_url != "/ibl5/"
    AND $actual_url != "/ibl5/index.php"
) {
    $boxstuff .= '<a href="index.php"><img src="images/ibl/logocorner.jpg" border="0"></a>';
}

$boxstuff .= "</span>";

$content = $boxstuff;

// A-Jay's localhost/production switch for development
if ($cookie[1] == "A-Jay") {
    if ($_SERVER['SERVER_NAME'] != "localhost") {
        $localURL = str_replace("ibl5/", "", $actual_url);
        echo "<a href=\"localhost:8888$localURL\">switch to localhost</a>";
    } elseif ($_SERVER['SERVER_NAME'] == "localhost") {
        echo "<a href=\"http://www.iblhoops.net$actual_url\">switch to production</a>";
    }
}
echo $newURL;