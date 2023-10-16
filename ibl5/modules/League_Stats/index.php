<?php

global $db, $cookie;
$sharedFunctions = new Shared($db);

if (!defined('MODULE_FILE')) {
    die("You can't access this file directly...");
}

$module_name = basename(dirname(__FILE__));
get_lang($module_name);
$pagetitle = "- $module_name";

$username = $cookie[1];
$queryTid = "SELECT user_ibl_team FROM nuke_users WHERE username = '$username' LIMIT 1;";
$resultTid = $db->sql_query($queryTid);
$userteam = $db->sql_result($resultTid, 0);
$userTid = $sharedFunctions->getTidFromTeamname($userteam);

$queryteam = "SELECT * FROM ibl_team_info";
$resultteam = $db->sql_query($queryteam);
$numteams = $db->sql_numrows($resultteam);

$n = 0;
while ($n < $numteams) {
    $teamid[$n] = $db->sql_result($resultteam, $n, "teamid");
    $team_city[$n] = $db->sql_result($resultteam, $n, "team_city");
    $team_name[$n] = $db->sql_result($resultteam, $n, "team_name");
    $coach_pts[$n] = $db->sql_result($resultteam, $n, "Contract_Coach");
    $color1[$n] = $db->sql_result($resultteam, $n, "color1");
    $color2[$n] = $db->sql_result($resultteam, $n, "color2");
    $n++;
}

$queryTeamOffenseTotals = "SELECT * FROM ibl_team_offense_stats ORDER BY team ASC";
$resultTeamOffenseTotals = $db->sql_query($queryTeamOffenseTotals);
$numTeamOffenseTotals = $db->sql_numrows($resultTeamOffenseTotals);

$t = 0;
while ($t < $numTeamOffenseTotals) {
    $team_off_name = $db->sql_result($resultTeamOffenseTotals, $t, "team");
    $m = 0;
    while ($m < $n) {
        if ($team_off_name == $team_name[$m]) {
            $teamcolor1 = $color1[$m];
            $teamcolor2 = $color2[$m];
            $teamcity = $team_city[$m];
            $tid = $teamid[$m];
        }
        $m++;
    }

    $team_off_games = $db->sql_result($resultTeamOffenseTotals, $t, "games");
    $team_off_minutes = $db->sql_result($resultTeamOffenseTotals, $t, "minutes");
    $team_off_fgm = $db->sql_result($resultTeamOffenseTotals, $t, "fgm");
    $team_off_fga = $db->sql_result($resultTeamOffenseTotals, $t, "fga");
    $team_off_ftm = $db->sql_result($resultTeamOffenseTotals, $t, "ftm");
    $team_off_fta = $db->sql_result($resultTeamOffenseTotals, $t, "fta");
    $team_off_tgm = $db->sql_result($resultTeamOffenseTotals, $t, "tgm");
    $team_off_tga = $db->sql_result($resultTeamOffenseTotals, $t, "tga");
    $team_off_orb = $db->sql_result($resultTeamOffenseTotals, $t, "orb");
    $team_off_reb = $db->sql_result($resultTeamOffenseTotals, $t, "reb");
    $team_off_ast = $db->sql_result($resultTeamOffenseTotals, $t, "ast");
    $team_off_stl = $db->sql_result($resultTeamOffenseTotals, $t, "stl");
    $team_off_tvr = $db->sql_result($resultTeamOffenseTotals, $t, "tvr");
    $team_off_blk = $db->sql_result($resultTeamOffenseTotals, $t, "blk");
    $team_off_pf = $db->sql_result($resultTeamOffenseTotals, $t, "pf");
    $team_off_pts = $team_off_fgm + $team_off_fgm + $team_off_ftm + $team_off_tgm;

    @$team_off_avgfgm = number_format($team_off_fgm / $team_off_games, 2);
    @$team_off_avgfga = number_format($team_off_fga / $team_off_games, 2);
    @$team_off_fgp = number_format($team_off_fgm / $team_off_fga, 3);
    @$team_off_avgftm = number_format($team_off_ftm / $team_off_games, 2);
    @$team_off_avgfta = number_format($team_off_fta / $team_off_games, 2);
    @$team_off_ftp = number_format($team_off_ftm / $team_off_fta, 3);
    @$team_off_avgtgm = number_format($team_off_tgm / $team_off_games, 2);
    @$team_off_avgtga = number_format($team_off_tga / $team_off_games, 2);
    @$team_off_tgp = number_format($team_off_tgm / $team_off_tga, 3);
    @$team_off_avgorb = number_format($team_off_orb / $team_off_games, 2);
    @$team_off_avgreb = number_format($team_off_reb / $team_off_games, 2);
    @$team_off_avgast = number_format($team_off_ast / $team_off_games, 2);
    @$team_off_avgstl = number_format($team_off_stl / $team_off_games, 2);
    @$team_off_avgtvr = number_format($team_off_tvr / $team_off_games, 2);
    @$team_off_avgblk = number_format($team_off_blk / $team_off_games, 2);
    @$team_off_avgpf = number_format($team_off_pf / $team_off_games, 2);
    @$team_off_avgpts = number_format($team_off_pts / $team_off_games, 2);

    $lg_off_games += $team_off_games;
    $lg_off_minutes += $team_off_minutes;
    $lg_off_fgm += $team_off_fgm;
    $lg_off_fga += $team_off_fga;
    $lg_off_ftm += $team_off_ftm;
    $lg_off_fta += $team_off_fta;
    $lg_off_tgm += $team_off_tgm;
    $lg_off_tga += $team_off_tga;
    $lg_off_orb += $team_off_orb;
    $lg_off_reb += $team_off_reb;
    $lg_off_ast += $team_off_ast;
    $lg_off_stl += $team_off_stl;
    $lg_off_tvr += $team_off_tvr;
    $lg_off_blk += $team_off_blk;
    $lg_off_pf += $team_off_pf;
    $lg_off_pts += $team_off_pts;

    if ($userTid == $tid) {
        $trSubstitute = "<tr bgcolor=#DDDD00 align=right>";
    } else {
        $trSubstitute = "<tr align=right>";
    }

    $offense_totals .= "$trSubstitute
        <td bgcolor=\"$teamcolor1\"><a href=\"modules.php?name=Team&op=team&tid=$tid\"><font color=\"$teamcolor2\">$teamcity $team_off_name Offense</font></a></td>
        <td>$team_off_games</td>
        <td>$team_off_fgm</td>
        <td>$team_off_fga</td>
        <td>$team_off_ftm</td>
        <td>$team_off_fta</td>
        <td>$team_off_tgm</td>
        <td>$team_off_tga</td>
        <td>$team_off_orb</td>
        <td>$team_off_reb</td>
        <td>$team_off_ast</td>
        <td>$team_off_stl</td>
        <td>$team_off_tvr</td>
        <td>$team_off_blk</td>
        <td>$team_off_pf</td>
        <td>$team_off_pts</td>
    </tr>";

    $offense_averages .= "$trSubstitute
        <td bgcolor=\"$teamcolor1\"><a href=\"modules.php?name=Team&op=team&tid=$tid\"><font color=\"$teamcolor2\">$teamcity $team_off_name Offense</font></a></td>
        <td>$team_off_avgfgm</td>
        <td>$team_off_avgfga</td>
        <td>$team_off_fgp</td>
        <td>$team_off_avgftm</td>
        <td>$team_off_avgfta</td>
        <td>$team_off_ftp</td>
        <td>$team_off_avgtgm</td>
        <td>$team_off_avgtga</td>
        <td>$team_off_tgp</td>
        <td>$team_off_avgorb</td>
        <td>$team_off_avgreb</td>
        <td>$team_off_avgast</td>
        <td>$team_off_avgstl</td>
        <td>$team_off_avgtvr</td>
        <td>$team_off_avgblk</td>
        <td>$team_off_avgpf</td>
        <td>$team_off_avgpts</td>
    </tr>";

    $teamHeaderCells[$t] = "<td bgcolor=\"$teamcolor1\"><a href=\"modules.php?name=Team&op=team&tid=$tid\"><font color=\"$teamcolor2\">$teamcity $team_off_name Diff</font></a></td>";
    $teamOffenseAveragesArray[$t] = array(
        $team_off_name,
        $team_off_avgfgm,
        $team_off_avgfga,
        $team_off_fgp,
        $team_off_avgftm,
        $team_off_avgfta,
        $team_off_ftp,
        $team_off_avgtgm,
        $team_off_avgtga,
        $team_off_tgp,
        $team_off_avgorb,
        $team_off_avgreb,
        $team_off_avgast,
        $team_off_avgstl,
        $team_off_avgtvr,
        $team_off_avgblk,
        $team_off_avgpf,
        $team_off_avgpts
    );

    $t++;
}

$queryTeamDefenseTotals = "SELECT * FROM ibl_team_defense_stats ORDER BY team ASC";
$resultTeamDefenseTotals = $db->sql_query($queryTeamDefenseTotals);
$numTeamDefenseTotals = $db->sql_numrows($resultTeamDefenseTotals);

$t = 0;
while ($t < $numTeamDefenseTotals) {
    $team_def_name = $db->sql_result($resultTeamDefenseTotals, $t, "team");
    $m = 0;
    while ($m < $n) {
        if ($team_def_name == $team_name[$m]) {
            $teamcolor1 = $color1[$m];
            $teamcolor2 = $color2[$m];
            $teamcity = $team_city[$m];
            $tid = $teamid[$m];
        }
        $m++;
    }

    $team_def_games = $db->sql_result($resultTeamDefenseTotals, $t, "games");
    $team_def_fgm = $db->sql_result($resultTeamDefenseTotals, $t, "fgm");
    $team_def_fga = $db->sql_result($resultTeamDefenseTotals, $t, "fga");
    $team_def_ftm = $db->sql_result($resultTeamDefenseTotals, $t, "ftm");
    $team_def_fta = $db->sql_result($resultTeamDefenseTotals, $t, "fta");
    $team_def_tgm = $db->sql_result($resultTeamDefenseTotals, $t, "tgm");
    $team_def_tga = $db->sql_result($resultTeamDefenseTotals, $t, "tga");
    $team_def_orb = $db->sql_result($resultTeamDefenseTotals, $t, "orb");
    $team_def_reb = $db->sql_result($resultTeamDefenseTotals, $t, "reb");
    $team_def_ast = $db->sql_result($resultTeamDefenseTotals, $t, "ast");
    $team_def_stl = $db->sql_result($resultTeamDefenseTotals, $t, "stl");
    $team_def_tvr = $db->sql_result($resultTeamDefenseTotals, $t, "tvr");
    $team_def_blk = $db->sql_result($resultTeamDefenseTotals, $t, "blk");
    $team_def_pf = $db->sql_result($resultTeamDefenseTotals, $t, "pf");
    $team_def_pts = $team_def_fgm + $team_def_fgm + $team_def_ftm + $team_def_tgm;

    @$team_def_avgfgm = number_format($team_def_fgm / $team_def_games, 2);
    @$team_def_avgfga = number_format($team_def_fga / $team_def_games, 2);
    @$team_def_fgp = number_format($team_def_fgm / $team_def_fga, 3);
    @$team_def_avgftm = number_format($team_def_ftm / $team_def_games, 2);
    @$team_def_avgfta = number_format($team_def_fta / $team_def_games, 2);
    @$team_def_ftp = number_format($team_def_ftm / $team_def_fta, 3);
    @$team_def_avgtgm = number_format($team_def_tgm / $team_def_games, 2);
    @$team_def_avgtga = number_format($team_def_tga / $team_def_games, 2);
    @$team_def_tgp = number_format($team_def_tgm / $team_def_tga, 3);
    @$team_def_avgorb = number_format($team_def_orb / $team_def_games, 2);
    @$team_def_avgreb = number_format($team_def_reb / $team_def_games, 2);
    @$team_def_avgast = number_format($team_def_ast / $team_def_games, 2);
    @$team_def_avgstl = number_format($team_def_stl / $team_def_games, 2);
    @$team_def_avgtvr = number_format($team_def_tvr / $team_def_games, 2);
    @$team_def_avgblk = number_format($team_def_blk / $team_def_games, 2);
    @$team_def_avgpf = number_format($team_def_pf / $team_def_games, 2);
    @$team_def_avgpts = number_format($team_def_pts / $team_def_games, 2);

    if ($userTid == $tid) {
        $trSubstitute = "<tr bgcolor=#DDDD00 align=right>";
    } else {
        $trSubstitute = "<tr align=right>";
    }

    $defense_totals .= "$trSubstitute
        <td bgcolor=\"$teamcolor1\"><a href=\"modules.php?name=Team&op=team&tid=$tid\"><font color=\"$teamcolor2\">$teamcity $team_def_name Defense</font></a></td>
        <td>$team_def_games</td>
        <td>$team_def_fgm</td>
        <td>$team_def_fga</td>
        <td>$team_def_ftm</td>
        <td>$team_def_fta</td>
        <td>$team_def_tgm</td>
        <td>$team_def_tga</td>
        <td>$team_def_orb</td>
        <td>$team_def_reb</td>
        <td>$team_def_ast</td>
        <td>$team_def_stl</td>
        <td>$team_def_tvr</td>
        <td>$team_def_blk</td>
        <td>$team_def_pf</td>
        <td>$team_def_pts</td>
    </tr>";

    $defense_averages .= "$trSubstitute
        <td bgcolor=\"$teamcolor1\"><a href=\"modules.php?name=Team&op=team&tid=$tid\"><font color=\"$teamcolor2\">$teamcity $team_def_name Defense</font></a></td>
        <td>$team_def_avgfgm</td>
        <td>$team_def_avgfga</td>
        <td>$team_def_fgp</td>
        <td>$team_def_avgftm</td>
        <td>$team_def_avgfta</td>
        <td>$team_def_ftp</td>
        <td>$team_def_avgtgm</td>
        <td>$team_def_avgtga</td>
        <td>$team_def_tgp</td>
        <td>$team_def_avgorb</td>
        <td>$team_def_avgreb</td>
        <td>$team_def_avgast</td>
        <td>$team_def_avgstl</td>
        <td>$team_def_avgtvr</td>
        <td>$team_def_avgblk</td>
        <td>$team_def_avgpf</td>
        <td>$team_def_avgpts</td>
    </tr>";

    $teamDefenseAveragesArray[$t] = array(
        $team_def_name,
        $team_def_avgfgm,
        $team_def_avgfga,
        $team_def_fgp,
        $team_def_avgftm,
        $team_def_avgfta,
        $team_def_ftp,
        $team_def_avgtgm,
        $team_def_avgtga,
        $team_def_tgp,
        $team_def_avgorb,
        $team_def_avgreb,
        $team_def_avgast,
        $team_def_avgstl,
        $team_def_avgtvr,
        $team_def_avgblk,
        $team_def_avgpf,
        $team_def_avgpts
    );

    $t++;
}

@$lg_off_avgfgm = number_format($lg_off_fgm / $lg_off_games, 2);
@$lg_off_avgfga = number_format($lg_off_fga / $lg_off_games, 2);
@$lg_off_fgp = number_format($lg_off_fgm / $lg_off_fga, 3);
@$lg_off_avgftm = number_format($lg_off_ftm / $lg_off_games, 2);
@$lg_off_avgfta = number_format($lg_off_fta / $lg_off_games, 2);
@$lg_off_ftp = number_format($lg_off_ftm / $lg_off_fta, 3);
@$lg_off_avgtgm = number_format($lg_off_tgm / $lg_off_games, 2);
@$lg_off_avgtga = number_format($lg_off_tga / $lg_off_games, 2);
@$lg_off_tgp = number_format($lg_off_tgm / $lg_off_tga, 3);
@$lg_off_avgorb = number_format($lg_off_orb / $lg_off_games, 2);
@$lg_off_avgreb = number_format($lg_off_reb / $lg_off_games, 2);
@$lg_off_avgast = number_format($lg_off_ast / $lg_off_games, 2);
@$lg_off_avgstl = number_format($lg_off_stl / $lg_off_games, 2);
@$lg_off_avgtvr = number_format($lg_off_tvr / $lg_off_games, 2);
@$lg_off_avgblk = number_format($lg_off_blk / $lg_off_games, 2);
@$lg_off_avgpf = number_format($lg_off_pf / $lg_off_games, 2);
@$lg_off_avgpts = number_format($lg_off_pts / $lg_off_games, 2);

$league_totals = "<tr style=\"font-weight:bold\">
    <td>LEAGUE TOTALS</td>
    <td>$lg_off_games</td>
    <td>$lg_off_fgm</td>
    <td>$lg_off_fga</td>
    <td>$lg_off_ftm</td>
    <td>$lg_off_fta</td>
    <td>$lg_off_tgm</td>
    <td>$lg_off_tga</td>
    <td>$lg_off_orb</td>
    <td>$lg_off_reb</td>
    <td>$lg_off_ast</td>
    <td>$lg_off_stl</td>
    <td>$lg_off_tvr</td>
    <td>$lg_off_blk</td>
    <td>$lg_off_pf</td>
    <td>$lg_off_pts</td>
</tr>";

$league_averages = "<tr style=\"font-weight:bold\">
    <td>LEAGUE AVERAGES</td>
    <td>$lg_off_avgfgm</td>
    <td>$lg_off_avgfga</td>
    <td>$lg_off_fgp</td>
    <td>$lg_off_avgftm</td>
    <td>$lg_off_avgfta</td>
    <td>$lg_off_ftp</td>
    <td>$lg_off_avgtgm</td>
    <td>$lg_off_avgtga</td>
    <td>$lg_off_tgp</td>
    <td>$lg_off_avgorb</td>
    <td>$lg_off_avgreb</td>
    <td>$lg_off_avgast</td>
    <td>$lg_off_avgstl</td>
    <td>$lg_off_avgtvr</td>
    <td>$lg_off_avgblk</td>
    <td>$lg_off_avgpf</td>
    <td>$lg_off_avgpts</td>
</tr>";

$i = 0;
while ($i < $numteams - 1) {
    if ($userteam == $teamOffenseAveragesArray[$i][0]) {
        $trSubstitute = "<tr bgcolor=#DDDD00 align=right>";
    } else {
        $trSubstitute = "<tr align=right>";
    }

    $league_differentials .= $trSubstitute;
    $league_differentials .= $teamHeaderCells[$i];

    $j = 1;
    while ($j < sizeof($teamOffenseAveragesArray[$i])) {
        $differential = $teamOffenseAveragesArray[$i][$j] - $teamDefenseAveragesArray[$i][$j];
        $league_differentials .= "<td align='right'>" . number_format($differential, 2) . "</td>";

        $j++;
    }
    $league_differentials .= "</tr>";

    $i++;
}

include "header.php";
OpenTable();

echo "<center>
    <h1>League-wide Statistics</h1>

    <h2>Team Offense Totals</h2>
    <table class=\"sortable\">
    <thead><tr><th>Team</th><th>Gm</th><th>FGM</th><th>FGA</th><th>FTM</th><th>FTA</th><th>3GM</th><th>3GA</th><th>ORB</th><th>REB</th><th>AST</th><th>STL</th><th>TVR</th><th>BLK</th><th>PF</th><th>PTS</th></tr></thead>
    <tbody>$offense_totals</tbody>
    <tfoot>$league_totals</tfoot>
    </table>

    <h2>Team Defense Totals</h2>
    <table class=\"sortable\">
    <thead><tr><th>Team</th><th>Gm</th><th>FGM</th><th>FGA</th><th>FTM</th><th>FTA</th><th>3GM</th><th>3GA</th><th>ORB</th><th>REB</th><th>AST</th><th>STL</th><th>TVR</th><th>BLK</th><th>PF</th><th>PTS</th></tr></thead>
    <tbody>$defense_totals</tbody>
    <tfoot>$league_totals</tfoot>
    </table>

    <h2>Team Offense Averages</h2>
    <table class=\"sortable\">
    <thead><tr><th>Team</th><th>FGM</th><th>FGA</th><th>FGP</th><th>FTM</th><th>FTA</th><th>FTP</th><th>3GM</th><th>3GA</th><th>3GP</th><th>ORB</th><th>REB</th><th>AST</th><th>STL</th><th>TVR</th><th>BLK</th><th>PF</th><th>PTS</th></tr></thead>
    <tbody>$offense_averages</tbody>
    <tfoot>$league_averages</tfoot>
    </table>

    <h2>Team Defense Averages</h2>
    <table class=\"sortable\">
    <thead><tr><th>Team</th><th>FGM</th><th>FGA</th><th>FGP</th><th>FTM</th><th>FTA</th><th>FTP</th><th>3GM</th><th>3GA</th><th>3GP</th><th>ORB</th><th>REB</th><th>AST</th><th>STL</th><th>TVR</th><th>BLK</th><th>PF</th><th>PTS</th></tr></thead>
    <tbody>$defense_averages</tbody>
    <tfoot>$league_averages</tfoot>
    </table>

    <h2>Team Off/Def Average Differentials</h2>
    <table class=\"sortable\">
    <thead><tr><th>Team</th><th>FGM</th><th>FGA</th><th>FGP</th><th>FTM</th><th>FTA</th><th>FTP</th><th>3GM</th><th>3GA</th><th>3GP</th><th>ORB</th><th>REB</th><th>AST</th><th>STL</th><th>TVR</th><th>BLK</th><th>PF</th><th>PTS</th></tr></thead>
    <tbody>$league_differentials</tbody>
    </table>";

CloseTable();
include "footer.php";
