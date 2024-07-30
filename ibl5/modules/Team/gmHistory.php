<?php

global $db;

$owner_award_code = $team->ownerName . " (" . $team->name . ")";
$querydec = "SELECT * FROM ibl_gm_history WHERE name LIKE '$owner_award_code' ORDER BY year ASC";
$resultdec = $db->sql_query($querydec);
$numdec = $db->sql_numrows($resultdec);
if ($numdec > 0) {
    $dec = 0;
}

$output .= "<tr bgcolor=\"#$team->color1\"><td align=\"center\">
    <font color=\"#$team->color2\"><b>GM History</b></font>
    </td></tr>
    <tr><td>";
while ($dec < $numdec) {
    $dec_year = $db->sql_result($resultdec, $dec, "year");
    $dec_Award = $db->sql_result($resultdec, $dec, "Award");
    $output .= "<table border=0 cellpadding=0 cellspacing=0><tr><td>$dec_year $dec_Award</td></tr></table>";
    $dec++;
}
$output .= "</td></tr>";