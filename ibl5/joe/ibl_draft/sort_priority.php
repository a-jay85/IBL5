<?
/***************************************************************************
 *                                sort_priority.php
 *                            -------------------
 *   begin                : Friday, Mar 28, 2008
 *   copyright            : (C) 2008 J. David Baker
 *   email                : me@jdavidbaker.com
 *
 ***************************************************************************/

/***************************************************************************
 *
 *   This program is free software; you can redistribute it and/or modify
 *   it under the terms of the GNU General Public License as published by
 *   the Free Software Foundation; either version 2 of the License, or
 *   (at your option) any later version.
 *
 ***************************************************************************/

include "includes/classes.inc.php";

// Re-sort the priority list for this user
$statement = "select * from selection where team_id = '" . $login->team_id() . "' and
selection_priority != '0'
order by selection_priority";
$result = mysql_query($statement);
$current = 10;
while ($row = mysql_fetch_array($result)) {
    $statement = "update selection set selection_priority = '$current' where
team_id = '" . $row['team_id'] . "' and player_id = '" . $row['player_id'] . "'";
    mysql_query($statement);
    $current += 10;
}

header("Location: priority.php");
