<?php

/************************************************************************/
/* PHP-NUKE: Web Portal System                                          */
/* ===========================                                          */
/*                                                                      */
/* Copyright (c) 2006 by Francisco Burzi                                */
/* http://phpnuke.org                                                   */
/*                                                                      */
/* This program is free software. You can redistribute it and/or modify */
/* it under the terms of the GNU General Public License as published by */
/* the Free Software Foundation; either version 2 of the License.       */
/************************************************************************/

if (!mb_eregi("modules.php", $_SERVER['PHP_SELF'])) {
    die("You can't access this file directly...");
}

require_once "mainfile.php";
$module_name = basename(dirname(__FILE__));
get_lang($module_name);
$userpage = 1;
include "header.php";

$queryEasternFrontcourt = "select count(name) as votes,name from (select East_F1 as name from IBL_ASG_Votes union all select East_F2 from IBL_ASG_Votes union all select East_C from IBL_ASG_Votes) as tbl group by name having count(name) > 0 order by 1 desc;";
$queryEasternBackcourt = "select count(name) as votes,name from (select East_G1 as name from IBL_ASG_Votes union all select East_G2 from IBL_ASG_Votes) as tbl group by name having count(name) > 0 order by 1 desc;";
$queryWesternFrontcourt = "select count(name) as votes,name from (select West_F1 as name from IBL_ASG_Votes union all select West_F2 from IBL_ASG_Votes union all select West_C from IBL_ASG_Votes) as tbl group by name having count(name) > 0 order by 1 desc;";
$queryWesternBackcourt = "select count(name) as votes,name from (select West_G1 as name from IBL_ASG_Votes union all select West_G2 from IBL_ASG_Votes) as tbl group by name having count(name) > 0 order by 1 desc;";

function displayVotingResultsTable($query)
{
    global $db;

    $i = 0;
    $result = $db->sql_query($query);
    $num_rows = $db->sql_numrows($result);

    while ($i < $num_rows) {
        $player[$i] = $db->sql_result($result, $i, "name");
        $votes[$i] = $db->sql_result($result, $i);

        $row .= "<tr><td>" . $player[$i] . "</td><td>" . $votes[$i] . "</td></tr>";

        $i++;
    }

    echo "
	<table class=\"sortable\" border=1>
		<tr>
			<th>Player</th>
			<th>Votes</th>
		</tr>
		$row
	</table>
	<br><br>";
}

OpenTable();

echo "<h2>Eastern Conference Frontcourt</h2>";
displayVotingResultsTable($queryEasternFrontcourt);
echo "<h2>Eastern Conference Backcourt</h2>";
displayVotingResultsTable($queryEasternBackcourt);
echo "<h2>Western Conference Frontcourt</h2>";
displayVotingResultsTable($queryWesternFrontcourt);
echo "<h2>Western Conference Backcourt</h2>";
displayVotingResultsTable($queryWesternBackcourt);

CloseTable();

include "footer.php";
