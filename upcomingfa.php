<?php

require 'config.php';
mysql_connect($dbhost,$dbuname,$dbpass);
@mysql_select_db($dbname) or die("Unable to select database");

$queryCurrentYear = 'SELECT value FROM nuke_ibl_settings WHERE name = "Current Season Ending Year"';
$resultCurrentYear = mysql_query($queryCurrentYear);
$freeagentyear=mysql_result($resultCurrentYear, 0);

$tid = $_REQUEST['tid'];
$yr = $_REQUEST['yr'];

if ($tid == NULL) {
    if ($freeagentyear != NULL) {
        // === CODE FOR FREE AGENTS

        echo "<html><head><title>Upcoming Free Agents List ($freeagentyear)</title></head><body>
            <style>th{ font-size: 9pt; font-family:Arial; color: white; background-color: navy}td      { text-align: Left; font-size: 9pt; font-family:Arial; color:black; }.tdp { font-weight: bold; text-align: Left; font-size: 9pt; color:black; } </style>
            <center><h2>Players Currently to be Free Agents at the end of the $freeagentyear Season</h2>
            <table border=1 cellspacing=1><tr><th colspan=27><center>Player Ratings</center></th></tr>
            <tr><th>Pos</th><th>Player</th><th>Team</th><th>Age</th><th>Sta</th><th>2ga</th><th>2g%</th><th>fta</th><th>ft%</th><th>3ga</th><th>3g%</th><th>orb</th><th>drb</th><th>ast</th><th>stl</th><th>to</th><th>blk</th><th>foul</th><th>o-o</th><th>d-o</th><th>p-o</th><th>t-o</th><th>o-d</th><th>d-d</th><th>p-d</th><th>t-d</th><th>Inj</th></tr>";

        $query="SELECT * FROM nuke_iblplyr WHERE retired = 0 ORDER BY ordinal ASC";
        $result=mysql_query($query);
        $num=mysql_numrows($result);

        $j=0;

        while ($i < $num) {
            $draftyear=mysql_result($result,$i,"draftyear");
            $exp=mysql_result($result,$i,"exp");
            $cy=mysql_result($result,$i,"cy");
            $cyt=mysql_result($result,$i,"cyt");

            $yearoffreeagency=$draftyear+$exp+$cyt-$cy;

            if ($yearoffreeagency == $freeagentyear) {
                $name=mysql_result($result,$i,"name");
                $team=mysql_result($result,$i,"teamname");
                $tid=mysql_result($result,$i,"tid");
                $pid=mysql_result($result,$i,"pid");
                $pos=mysql_result($result,$i,"pos");
                $age=mysql_result($result,$i,"age");
                $inj=mysql_result($result,$i,"injured");

                $r_2ga=mysql_result($result,$i,"r_fga");
                $r_2gp=mysql_result($result,$i,"r_fgp");
                $r_fta=mysql_result($result,$i,"r_fta");
                $r_ftp=mysql_result($result,$i,"r_ftp");
                $r_3ga=mysql_result($result,$i,"r_tga");
                $r_3gp=mysql_result($result,$i,"r_tgp");
                $r_orb=mysql_result($result,$i,"r_orb");
                $r_drb=mysql_result($result,$i,"r_drb");
                $r_ast=mysql_result($result,$i,"r_ast");
                $r_stl=mysql_result($result,$i,"r_stl");
                $r_blk=mysql_result($result,$i,"r_blk");
                $r_tvr=mysql_result($result,$i,"r_to");
                $r_sta=mysql_result($result,$i,"sta");
                $r_foul=mysql_result($result,$i,"r_foul");
                $r_totoff=mysql_result($result,$i,"oo")+mysql_result($result,$i,"do")+mysql_result($result,$i,"po")+mysql_result($result,$i,"to");
                $r_totdef=mysql_result($result,$i,"od")+mysql_result($result,$i,"dd")+mysql_result($result,$i,"pd")+mysql_result($result,$i,"td");
                $r_oo=mysql_result($result,$i,"oo");
                $r_do=mysql_result($result,$i,"do");
                $r_po=mysql_result($result,$i,"po");
                $r_to=mysql_result($result,$i,"to");
                $r_od=mysql_result($result,$i,"od");
                $r_dd=mysql_result($result,$i,"dd");
                $r_pd=mysql_result($result,$i,"pd");
                $r_td=mysql_result($result,$i,"td");
                $r_foul=mysql_result($result,$i,"r_foul");

                if ($j == 0) {
                    echo "      <tr bgcolor=#ffffff align=center>";
                    $j=1;
                } else {
                    echo "      <tr bgcolor=#e6e7e2 align=center>";
                    $j=0;
                }
                echo "      <td>$pos</td><td><a href=\"modules.php?name=Player&pa=showpage&pid=$pid\">$name</a></td><td><a href=\"team.php?tid=$tid\">$team</a></td><td>$age</td><td>$r_sta</td><td>$r_2ga</td><td>$r_2gp</td><td>$r_fta</td><td>$r_ftp</td><td>$r_3ga</td><td>$r_3gp</td><td>$r_orb</td><td>$r_drb</td><td>$r_ast</td><td>$r_stl</td><td>$r_tvr</td><td>$r_blk</td><td>$r_foul</td><td>$r_oo</td><td>$r_do</td><td>$r_po</td><td>$r_to</td><td>$r_od</td><td>$r_dd</td><td>$r_pd</td><td>$r_td</td><td>$inj</td></tr>
                ";
            }

            $i++;

        }

        echo "
            </table>";
    }
}

?>
