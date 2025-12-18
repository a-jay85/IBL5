<?php

use Utilities\HtmlSanitizer;

require $_SERVER['DOCUMENT_ROOT'] . '/ibl5/mainfile.php';

global $mysqli_db;

$queryfirstyear = "SELECT draftyear FROM ibl_plr ORDER BY draftyear ASC";
$resultfirstyear = $mysqli_db->query($queryfirstyear);
$rowfirst = $resultfirstyear->fetch_assoc();
$startyear = $rowfirst['draftyear'];

$querylastyear = "SELECT draftyear FROM ibl_plr ORDER BY draftyear DESC";
$resultlastyear = $mysqli_db->query($querylastyear);
$rowlast = $resultlastyear->fetch_assoc();
$endyear = $rowlast['draftyear'];

$year = $_REQUEST['year'];

$stmt = $mysqli_db->prepare("SELECT * FROM ibl_plr WHERE draftyear = ? AND draftround > 0 ORDER BY draftround, draftpickno ASC");
$stmt->bind_param('i', $year);
$stmt->execute();
$result = $stmt->get_result();
$num = $result->num_rows;

echo "<html><head><title>Overview of $year IBL Draft</title></head><body>
<style>th{ font-size: 9pt; font-family:Arial; color:white; background-color: navy}td      { text-align: Left; font-size: 9pt; font-family:Arial; color:black; }.tdp { font-weight: bold; text-align: Left; font-size: 9pt; color:black; } </style>
";

echo "<center><h2>$year Draft</h2>
";

$startyear = 1988; // magic number reasoning: we kept players' real life draft years intact, but IBLv5's first non-dispersal draft was held in 1988.

while ($startyear < $endyear + 1) {
    echo "<a href=\"" . basename(__FILE__) . "?year=$startyear\">$startyear</a> |
";
    $startyear++;
}

if ($num == 0) {
    echo "<br><br> Please select a draft year.";
} else {
    echo "<table>
<th>ROUND</th><th>PICK</th><th>Player</th><th>Selected by</th><th>Pic</th><th>College</th></tr>
";

    $i = 0;

    while ($row = $result->fetch_assoc()) {
        $draftedby = HtmlSanitizer::safeHtmlOutput($row['draftedby']); // Safely escape for HTML
        $name = HtmlSanitizer::safeHtmlOutput($row['name']); // Safely escape for HTML
        $pid = $row['pid'];
        $round = $row['draftround'];
        $draftpickno = $row['draftpickno'];
        $college = HtmlSanitizer::safeHtmlOutput($row['college']); // Safely escape for HTML

        if ($i % 2) {
            echo "<tr bgcolor=#ffffff>";
        } else {
            echo "<tr bgcolor=#e6e7e2>";
        }

        echo "<td>$round</td>
            <td>$draftpickno</td>
            <td><a href=\"/ibl5/modules.php?name=Player&pa=showpage&pid=$pid\">$name</a></td>
            <td>$draftedby</td>
            <td><img height=50 src=\"/ibl5/images/player/$pid.jpg\"></td>
            <td>$college</td>
        </tr>
";
    }
}

echo "</table></center></body></html>";
