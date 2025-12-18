<?php

use Services\DatabaseService;
use Utilities\HtmlSanitizer;

require $_SERVER['DOCUMENT_ROOT'] . '/ibl5/mainfile.php';
$season = new Season($mysqli_db);

$tid = $_REQUEST['tid'];
$yr = $_REQUEST['yr'];

if ($tid == null) {
    if ($season->endingYear != null) {
        // === CODE FOR FREE AGENTS

        echo "<html><head><title>Upcoming Free Agents List ($season->endingYear)</title></head><body>
            <style>th{ font-size: 9pt; font-family:Arial; color: white; background-color: navy}td      { text-align: Left; font-size: 9pt; font-family:Arial; color:black; }.tdp { font-weight: bold; text-align: Left; font-size: 9pt; color:black; } </style>
            <center><h2>Players Currently to be Free Agents at the end of the $season->endingYear Season</h2>
            <table border=1 cellspacing=1><tr><th colspan=33><center>Player Ratings</center></th></tr>
            <tr><th>Pos</th>
                <th>Player</th>
                <th>Team</th>
                <th>Age</th>
                <th>2ga</th>
                <th>2g%</th>
                <th>fta</th>
                <th>ft%</th>
                <th>3ga</th>
                <th>3g%</th>
                <th>orb</th>
                <th>drb</th>
                <th>ast</th>
                <th>stl</th>
                <th>to</th>
                <th>blk</th>
                <th>foul</th>
                <th>o-o</th>
                <th>d-o</th>
                <th>p-o</th>
                <th>t-o</th>
                <th>o-d</th>
                <th>d-d</th>
                <th>p-d</th>
                <th>t-d</th>
                <th>Loy</th>
                <th>PFW</th>
                <th>PT</th>
                <th>Sec</th>
                <th>Trad</th>
            </tr>";

        $query = "SELECT * FROM ibl_plr WHERE retired = 0 ORDER BY ordinal ASC";
        $result = $mysqli_db->query($query);
        $num = ($result instanceof mysqli_result) ? $result->num_rows : 0;

        $i = 0;
        $j = 0;

        if ($result instanceof mysqli_result) {
            while ($row = $result->fetch_assoc()) {
                $draftyear = $row["draftyear"] ?? 0;
                $exp = $row["exp"] ?? 0;
                $cy = $row["cy"] ?? 0;
                $cyt = $row["cyt"] ?? 0;

                $yearoffreeagency = $draftyear + $exp + $cyt - $cy;

                if ($yearoffreeagency == $season->endingYear) {
                    $name = HtmlSanitizer::safeHtmlOutput($row["name"] ?? '');
                    $team = HtmlSanitizer::safeHtmlOutput($row["teamname"] ?? '');
                    $tid = $row["tid"] ?? '';
                    $pid = $row["pid"] ?? '';
                    $pos = $row["pos"] ?? '';
                    $age = $row["age"] ?? '';

                    $r_2ga = $row["r_fga"] ?? 0;
                    $r_2gp = $row["r_fgp"] ?? 0;
                    $r_fta = $row["r_fta"] ?? 0;
                    $r_ftp = $row["r_ftp"] ?? 0;
                    $r_3ga = $row["r_tga"] ?? 0;
                    $r_3gp = $row["r_tgp"] ?? 0;
                    $r_orb = $row["r_orb"] ?? 0;
                    $r_drb = $row["r_drb"] ?? 0;
                    $r_ast = $row["r_ast"] ?? 0;
                    $r_stl = $row["r_stl"] ?? 0;
                    $r_blk = $row["r_blk"] ?? 0;
                    $r_tvr = $row["r_to"] ?? 0;
                    $r_foul = $row["r_foul"] ?? 0;
                    $r_totoff = ($row["oo"] ?? 0) + ($row["do"] ?? 0) + ($row["po"] ?? 0) + ($row["to"] ?? 0);
                    $r_totdef = ($row["od"] ?? 0) + ($row["dd"] ?? 0) + ($row["pd"] ?? 0) + ($row["td"] ?? 0);
                    $r_oo = $row["oo"] ?? 0;
                    $r_do = $row["do"] ?? 0;
                    $r_po = $row["po"] ?? 0;
                    $r_to = $row["to"] ?? 0;
                    $r_od = $row["od"] ?? 0;
                    $r_dd = $row["dd"] ?? 0;
                    $r_pd = $row["pd"] ?? 0;
                    $r_td = $row["td"] ?? 0;
                    $r_foul = $row["r_foul"] ?? 0;
                    $loyalty = $row["loyalty"] ?? 0;
                    $playForWinner = $row["winner"] ?? 0;
                    $playingTime = $row["playingTime"] ?? 0;
                    $security = $row["security"] ?? 0;
                    $tradition = $row["tradition"] ?? 0;

                if ($j == 0) {
                    echo "      <tr bgcolor=#ffffff align=center>";
                    $j = 1;
                } else {
                    echo "      <tr bgcolor=#e6e7e2 align=center>";
                    $j = 0;
                }
                echo "<td>$pos</td>
                    <td><a href=\"modules.php?name=Player&pa=showpage&pid=$pid\">$name</a></td>
                    <td><a href=\"team.php?tid=$tid\">$team</a></td>
                    <td>$age</td>
                    <td>$r_2ga</td>
                    <td>$r_2gp</td>
                    <td>$r_fta</td>
                    <td>$r_ftp</td>
                    <td>$r_3ga</td>
                    <td>$r_3gp</td>
                    <td>$r_orb</td>
                    <td>$r_drb</td>
                    <td>$r_ast</td>
                    <td>$r_stl</td>
                    <td>$r_tvr</td>
                    <td>$r_blk</td>
                    <td>$r_foul</td>
                    <td>$r_oo</td>
                    <td>$r_do</td>
                    <td>$r_po</td>
                    <td>$r_to</td>
                    <td>$r_od</td>
                    <td>$r_dd</td>
                    <td>$r_pd</td>
                    <td>$r_td</td>
                    <td>$loyalty</td>
                    <td>$playForWinner</td>
                    <td>$playingTime</td>
                    <td>$security</td>
                    <td>$tradition</td>
                </tr>
                ";
                }

                $i++;
            }
        }

        echo "
            </table>";
    }
}
