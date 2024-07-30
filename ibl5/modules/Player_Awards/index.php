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

if (!defined('MODULE_FILE')) {
    die("You can't access this file directly...");
}

$module_name = basename(dirname(__FILE__));
get_lang($module_name);

$pagetitle = "- $module_name";

global $db;

NukeHeader::header();
OpenTable();
UI::playerMenu();

// ============== GET POST DATA

$as_name = stripslashes(check_html($_POST['aw_name'], "nohtml"));
$as_Award = stripslashes(check_html($_POST['aw_Award'], "nohtml"));
$as_year = stripslashes(check_html($_POST['aw_year'], "nohtml"));
$as_sortby = stripslashes(check_html($_POST['aw_sortby'], "nohtml"));

// ========= SEARCH PARAMETERS

echo "Partial matches on a name or award are okay and are <b>not</b> case sensitive (e.g., entering \"Dard\" will match with \"Darden\" and \"Bedard\").<p>

    <form name=\"Search\" method=\"post\" action=\"modules.php?name=Player_Awards\">
    <table border=1>
        <tr>
            <td>NAME: <input type=\"text\" name=\"aw_name\" size=\"32\" value=\"$as_name\"></td>
            <td>AWARD: <input type=\"text\" name=\"aw_Award\" size=\"32\" value=\"$as_Award\"></td>
            <td>Year: <input type=\"text\" name=\"aw_year\" size=\"4\" value=\"$as_year\"></td>
        </tr>
        <tr>
            <td colspan=3>SORT BY:";

if ($as_sortby == 1) {
    echo "<input type=\"radio\" name=\"aw_sortby\" value=\"1\" checked> Name |";
} else {
    echo "<input type=\"radio\" name=\"aw_sortby\" value=\"1\"> Name |";
}

if ($as_sortby == 2) {
    echo "<input type=\"radio\" name=\"aw_sortby\" value=\"2\" checked> Award Name |";
} else {
    echo "<input type=\"radio\" name=\"aw_sortby\" value=\"2\"> Award Name |";
}

if ($as_sortby == 3) {
    echo "<input type=\"radio\" name=\"aw_sortby\" value=\"3\" checked> Year |";
} else {
    echo "<input type=\"radio\" name=\"aw_sortby\" value=\"3\"> Year |";
}

echo "</td></tr></table>
    <input type=\"submit\" value=\"Search for Matches!\">
</form>";

// ========= SET QUERY BASED ON SEARCH PARAMETERS

$continuequery = 0;
$query = "SELECT * FROM ibl_awards";

if ($as_year != null) {
    $query .= " WHERE year = '$as_year'";
    $continuequery = 1;
}

if ($continuequery == 0) {
    if ($as_Award != null) {
        $query .= " WHERE Award LIKE '%$as_Award%'";
        $continuequery = 1;
    }
} else {
    if ($as_Award != null) {
        $query .= " AND Award LIKE '%$as_Award%'";
    }
}

if ($continuequery == 0) {
    if ($as_name != null) {
        $query .= " WHERE name LIKE '%$as_name%'";
        $continuequery = 1;
    }
} else {
    if ($as_name != null) {
        $query .= " AND name LIKE '%$as_name%'";
    }
}

$orderby = 'Year';

if ($as_sortby == 1) {
    $orderby = 'name';
}
if ($as_sortby == 2) {
    $orderby = 'Award';
}
if ($as_sortby == 3) {
    $orderby = 'year';
}

$query .= " ORDER BY $orderby ASC";

// =============== EXECUTE QUERY

$result = $db->sql_query($query);
@$num = $db->sql_numrows($result);

echo "<table border=1 cellpadding=0 cellspacing=0>
    <tr>
        <td colspan=3><center><i>Search Results</i></center></td>
    </tr>
    <tr>
        <th>Year</th>
        <th>Player</th>
        <th>Award</th>
    </tr>";

// ========== FILL RESULTS

$i = 0;

while ($i < $num) {
    $a_name = $db->sql_result($result, $i, "name");
    $a_Award = $db->sql_result($result, $i, "Award");
    $a_year = $db->sql_result($result, $i, "year");
    if ($i % 2) {
        echo "<tr bgcolor=#ffffff>";
    } else {
        echo "<tr bgcolor=#e6e7e2>";
    }
    echo "<tr>
        <td><center>$a_year</center></td>
        <td><center>$a_name</a></center></td>
        <td><center>$a_Award</center></td>
    </tr>";

    $i++;
}

echo "</table></center>";

CloseTable();
include "footer.php";
