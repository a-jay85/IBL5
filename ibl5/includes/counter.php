<?php

/************************************************************************/
/* PHP-NUKE: Web Portal System                                          */
/* ===========================                                          */
/*                                                                      */
/* Copyright (c) 2007 by Francisco Burzi                                */
/* http://phpnuke.org                                                   */
/*                                                                      */
/* Based on NukeStats Module Version 1.0                                */
/* Copyright (c) 2002 by Harry Mangindaan (sens@indosat.net) and        */
/*                    Sudirman (sudirman@akademika.net)                 */
/* http://www.nuketest.com                                              */
/*                                                                      */
/* This program is free software. You can redistribute it and/or modify */
/* it under the terms of the GNU General Public License as published by */
/* the Free Software Foundation; either version 2 of the License.       */
/************************************************************************/

if (stristr(htmlentities($_SERVER['PHP_SELF']), "counter.php")) {
    Header("Location: index.php");
    die();
}
global $prefix, $db;

/* Get the Browser data */

if ((str_contains($_SERVER["HTTP_USER_AGENT"], "Nav")) || (str_contains($_SERVER["HTTP_USER_AGENT"], "Gold")) || (str_contains($_SERVER["HTTP_USER_AGENT"], "X11")) || (str_contains($_SERVER["HTTP_USER_AGENT"], "Mozilla")) || (str_contains($_SERVER["HTTP_USER_AGENT"], "Netscape")) and (!str_contains($_SERVER["HTTP_USER_AGENT"], "MSIE")) and (!str_contains($_SERVER["HTTP_USER_AGENT"], "Konqueror")) and (!str_contains($_SERVER["HTTP_USER_AGENT"], "Yahoo")) and (!str_contains($_SERVER["HTTP_USER_AGENT"], "Firefox"))) {
    $browser = "Netscape";
} elseif (str_contains($_SERVER["HTTP_USER_AGENT"], "Firefox")) {
    $browser = "FireFox";
} elseif (str_contains($_SERVER["HTTP_USER_AGENT"], "MSIE")) {
    $browser = "MSIE";
} elseif (str_contains($_SERVER["HTTP_USER_AGENT"], "Lynx")) {
    $browser = "Lynx";
} elseif (str_contains($_SERVER["HTTP_USER_AGENT"], "Opera")) {
    $browser = "Opera";
} elseif (str_contains($_SERVER["HTTP_USER_AGENT"], "WebTV")) {
    $browser = "WebTV";
} elseif (str_contains($_SERVER["HTTP_USER_AGENT"], "Konqueror")) {
    $browser = "Konqueror";
} elseif ((stripos($_SERVER["HTTP_USER_AGENT"], "bot") !== false) || (str_contains($_SERVER["HTTP_USER_AGENT"], "Google")) || (str_contains($_SERVER["HTTP_USER_AGENT"], "Slurp")) || (str_contains($_SERVER["HTTP_USER_AGENT"], "Scooter")) || (stripos($_SERVER["HTTP_USER_AGENT"], "Spider") !== false) || (stripos($_SERVER["HTTP_USER_AGENT"], "Infoseek") !== false)) {
    $browser = "Bot";
} else {
    $browser = "Other";
}

/* Get the Operating System data */

if (str_contains($_SERVER["HTTP_USER_AGENT"], "Win")) {
    $os = "Windows";
} elseif ((str_contains($_SERVER["HTTP_USER_AGENT"], "Mac")) || (str_contains($_SERVER["HTTP_USER_AGENT"], "PPC"))) {
    $os = "Mac";
} elseif (str_contains($_SERVER["HTTP_USER_AGENT"], "Linux")) {
    $os = "Linux";
} elseif (str_contains($_SERVER["HTTP_USER_AGENT"], "FreeBSD")) {
    $os = "FreeBSD";
} elseif (str_contains($_SERVER["HTTP_USER_AGENT"], "SunOS")) {
    $os = "SunOS";
} elseif (str_contains($_SERVER["HTTP_USER_AGENT"], "IRIX")) {
    $os = "IRIX";
} elseif (str_contains($_SERVER["HTTP_USER_AGENT"], "BeOS")) {
    $os = "BeOS";
} elseif (str_contains($_SERVER["HTTP_USER_AGENT"], "OS/2")) {
    $os = "OS/2";
} elseif (str_contains($_SERVER["HTTP_USER_AGENT"], "AIX")) {
    $os = "AIX";
} else {
    $os = "Other";
}

/* Save on the databases the obtained values */

$db->sql_query("UPDATE " . $prefix . "_counter SET count=count+1 WHERE (type='total' AND var='hits') OR (var='$browser' AND type='browser') OR (var='$os' AND type='os')");
update_points(13);

/* Start Detailed Statistics */

$dot = date("d-m-Y-H");
$now = explode("-", $dot);
$nowHour = $now[3];
$nowYear = $now[2];
$nowMonth = $now[1];
$nowDate = $now[0];
$sql = "SELECT year FROM " . $prefix . "_stats_year WHERE year='$nowYear'";
$resultyear = $db->sql_query($sql);
$jml = $db->sql_numrows($resultyear);
if ($jml <= 0) {
    $sql = "INSERT INTO " . $prefix . "_stats_year VALUES ('$nowYear','0')";
    $db->sql_query($sql);
    for ($i = 1; $i <= 12; $i++) {
        $db->sql_query("INSERT INTO " . $prefix . "_stats_month VALUES ('$nowYear','$i','0')");
        if ($i == 1) {
            $TotalDay = 31;
        }

        if ($i == 2) {
            if (date("L") == true) {
                $TotalDay = 29;
            } else {
                $TotalDay = 28;
            }
        }
        if ($i == 3) {
            $TotalDay = 31;
        }

        if ($i == 4) {
            $TotalDay = 30;
        }

        if ($i == 5) {
            $TotalDay = 31;
        }

        if ($i == 6) {
            $TotalDay = 30;
        }

        if ($i == 7) {
            $TotalDay = 31;
        }

        if ($i == 8) {
            $TotalDay = 31;
        }

        if ($i == 9) {
            $TotalDay = 30;
        }

        if ($i == 10) {
            $TotalDay = 31;
        }

        if ($i == 11) {
            $TotalDay = 30;
        }

        if ($i == 12) {
            $TotalDay = 31;
        }

        for ($k = 1; $k <= $TotalDay; $k++) {
            $db->sql_query("INSERT INTO " . $prefix . "_stats_date VALUES ('$nowYear','$i','$k','0')");
        }
    }
}

$sql = "SELECT hour FROM " . $prefix . "_stats_hour WHERE (year='$nowYear') AND (month='$nowMonth') AND (date='$nowDate')";
$result = $db->sql_query($sql);
$numrows = $db->sql_numrows($result);

if ($numrows <= 0) {
    for ($z = 0; $z <= 23; $z++) {
        $db->sql_query("INSERT INTO " . $prefix . "_stats_hour VALUES ('$nowYear','$nowMonth','$nowDate','$z','0')");
    }
}

$db->sql_query("UPDATE " . $prefix . "_stats_year SET hits=hits+1 WHERE year='$nowYear'");
$db->sql_query("UPDATE " . $prefix . "_stats_month SET hits=hits+1 WHERE (year='$nowYear') AND (month='$nowMonth')");
$db->sql_query("UPDATE " . $prefix . "_stats_date SET hits=hits+1 WHERE (year='$nowYear') AND (month='$nowMonth') AND (date='$nowDate')");
$db->sql_query("UPDATE " . $prefix . "_stats_hour SET hits=hits+1 WHERE (year='$nowYear') AND (month='$nowMonth') AND (date='$nowDate') AND (hour='$nowHour')");
