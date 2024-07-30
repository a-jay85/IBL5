<?php

global $db;

$output .= "<tr bgcolor=\"#$team->color1\"><td align=\"center\">
    <font color=\"#$team->color2\"><b>Current Season</b></font>
    </td></tr>
    <tr><td>";
$output .= teamCurrentSeasonStandings($team);
$output .= "</td></tr>";