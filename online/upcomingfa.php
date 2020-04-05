<?php

$username = "iblhoops_chibul";
$password = "oliver23";
$database = "iblhoops_iblleague";

mysql_connect(localhost,$username,$password);
@mysql_select_db($database) or die( "Unable to select database");

require_once("mainfile.php");
$module_name = basename(dirname(__FILE__));
get_lang($module_name);

$pagetitle = "- Free Agency System";

function display($nullset) {
    global $prefix, $db, $sitename, $admin, $module_name, $user, $cookie;
    include("header.php");
    OpenTable();

    $sql2 = "SELECT * FROM ".$prefix."_users WHERE username='$cookie[1]'";
    $result2 = $db->sql_query($sql2);
    $num2 = $db->sql_numrows($result2);
    $userinfo = $db->sql_fetchrow($result2);

    $userteam = stripslashes(check_html($userinfo['user_ibl_team'], "nohtml"));

$freeagentyear=1986;

// ==== INSERT LIST OF FREE AGENTS FROM TEAM

echo "
      <tr bgcolor=0000cc><td colspan=25><center><font color=white><b>$userteam Unsigned Free Agents</b> (Note: * and <i>italicized</i> indicates player has Bird Rights)</i></font></center></td><td colspan=6><center><font color=white><b>Contract Amount Sought</b></font></center></td></tr>
      <tr><td>Negotiate</td><td>Pos</td><td>Player</td><td>Team</td><td>Age</td><td>Sta</td><td>2ga</td><td>2g%</td><td>fta</td><td>ft%</td><td>3ga</td><td>3g%</td><td>orb</td><td>drb</td><td>ast</td><td>stl</td><td>to</td><td>blk</td><td>oo</td><td>do</td><td>po</td><td>to</td><td>od</td><td>dd</td><td>pd</td><td>td</td><td>Yr1</td><td>Yr2</td><td>Yr3</td><td>Yr4</td><td>Yr5</td><td>Yr6</td></tr>
";

    $showteam = $db->sql_query("SELECT * FROM ".$prefix."_iblplyr WHERE teamname='$userteam' AND retired='0' ORDER BY ordinal ASC");
    while ($teamlist = $db->sql_fetchrow($showteam)) {

    $draftyear = stripslashes(check_html($teamlist['draftyear'], "nohtml"));
    $exp = stripslashes(check_html($teamlist['exp'], "nohtml"));
    $cy = stripslashes(check_html($teamlist['cy'], "nohtml"));
    $cyt = stripslashes(check_html($teamlist['cyt'], "nohtml"));
    $yearoffreeagency=$draftyear+$exp+$cyt-$cy;

    if ($yearoffreeagency == $freeagentyear)
      {

      $name = stripslashes(check_html($teamlist['name'], "nohtml"));
      $team = stripslashes(check_html($teamlist['teamname'], "nohtml"));
      $tid = stripslashes(check_html($teamlist['tid'], "nohtml"));
      $pid = stripslashes(check_html($teamlist['pid'], "nohtml"));
      $pos = stripslashes(check_html($teamlist['altpos'], "nohtml"));
      $age = stripslashes(check_html($teamlist['age'], "nohtml"));
      $inj = stripslashes(check_html($teamlist['injured'], "nohtml"));
      $bird = stripslashes(check_html($teamlist['bird'], "nohtml"));

    $getdemands = $db->sql_fetchrow($db->sql_query("SELECT * FROM ".$prefix."_ibl_demands WHERE name='$name'"));

      $dem1 = stripslashes(check_html($getdemands['dem1'], "nohtml"));
      $dem2 = stripslashes(check_html($getdemands['dem2'], "nohtml"));
      $dem3 = stripslashes(check_html($getdemands['dem3'], "nohtml"));
      $dem4 = stripslashes(check_html($getdemands['dem4'], "nohtml"));
      $dem5 = stripslashes(check_html($getdemands['dem5'], "nohtml"));
      $dem6 = stripslashes(check_html($getdemands['dem6'], "nohtml"));

      $r_2ga = stripslashes(check_html($teamlist['r_fga'], "nohtml"));
      $r_2gp = stripslashes(check_html($teamlist['r_fgp'], "nohtml"));
      $r_fta = stripslashes(check_html($teamlist['r_fta'], "nohtml"));
      $r_ftp = stripslashes(check_html($teamlist['r_ftp'], "nohtml"));
      $r_3ga = stripslashes(check_html($teamlist['r_tga'], "nohtml"));
      $r_3gp = stripslashes(check_html($teamlist['r_tgp'], "nohtml"));
      $r_orb = stripslashes(check_html($teamlist['r_orb'], "nohtml"));
      $r_drb = stripslashes(check_html($teamlist['r_drb'], "nohtml"));
      $r_ast = stripslashes(check_html($teamlist['r_ast'], "nohtml"));
      $r_stl = stripslashes(check_html($teamlist['r_stl'], "nohtml"));
      $r_blk = stripslashes(check_html($teamlist['r_blk'], "nohtml"));
      $r_tvr = stripslashes(check_html($teamlist['r_to'], "nohtml"));
      $r_sta = stripslashes(check_html($teamlist['sta'], "nohtml"));
      $r_foul = stripslashes(check_html($teamlist['r_drb'], "nohtml"));
      $r_oo = stripslashes(check_html($teamlist['oo'], "nohtml"));
      $r_do = stripslashes(check_html($teamlist['do'], "nohtml"));
      $r_po = stripslashes(check_html($teamlist['po'], "nohtml"));
      $r_to = stripslashes(check_html($teamlist['to'], "nohtml"));
      $r_od = stripslashes(check_html($teamlist['od'], "nohtml"));
      $r_dd = stripslashes(check_html($teamlist['dd'], "nohtml"));
      $r_pd = stripslashes(check_html($teamlist['pd'], "nohtml"));
      $r_td = stripslashes(check_html($teamlist['td'], "nohtml"));
      $r_totoff=$r_oo+$r_do+$r_po+$r_to;
      $r_totdef=$r_od+$r_dd+$r_pd+$r_td;

echo "      <tr><td><a href=\"http://www.iblhoops.net/modules.php?name=Free_Agency&pa=negotiate&pid=$pid\">Negotiate</a></td><td>$pos</td><td><a href=\"http://www.iblhoops.net/modules.php?name=Player&pa=showpage&pid=$pid\">";

// ==== NOTE PLAYERS ON TEAM WITH BIRD RIGHTS

if ($bird > 2)
{
echo "*<i>";
}

echo "$name";

if ($bird > 2)
{
echo "*</i>";
}

// ==== END NOTE BIRD RIGHTS

echo "</a></td><td><a href=\"team.php?tid=$tid\">$team</a></td><td>$age</td><td>$r_sta</td><td>$r_2ga</td><td>$r_2gp</td><td>$r_fta</td><td>$r_ftp</td><td>$r_3ga</td><td>$r_3gp</td><td>$r_orb</td><td>$r_drb</td><td>$r_ast</td><td>$r_stl</td><td>$r_tvr</td><td>$r_blk</td><td>$r_oo</td><td>$r_do</td><td>$r_po</td><td>$r_to</td><td>$r_od</td><td>$r_dd</td><td>$r_pd</td><td>$r_td</td><td>$dem1</td><td>$dem2</td><td>$dem3</td><td>$dem4</td><td>$dem5</td><td>$dem6</td></tr>
";

} else {
}

}

// ==== END INSERT OF LIST OF FREE AGENTS FROM TEAM

// ==== INSERT ALL OTHER FREE AGENTS

echo "
      <tr bgcolor=#0000cc><td colspan=25><center><b><font color=white>All Other Free Agents</font></b></center></td><td colspan=6><center><b><font color=white>Contract Amount Sought</font></b></center></td></tr>
      <tr><td>Negotiate</td><td>Pos</td><td>Player</td><td>Team</td><td>Age</td><td>Sta</td><td>2ga</td><td>2g%</td><td>fta</td><td>ft%</td><td>3ga</td><td>3g%</td><td>orb</td><td>drb</td><td>ast</td><td>stl</td><td>to</td><td>blk</td><td>oo</td><td>do</td><td>po</td><td>to</td><td>od</td><td>dd</td><td>pd</td><td>td</td><td>Yr1</td><td>Yr2</td><td>Yr3</td><td>Yr4</td><td>Yr5</td><td>Yr6</td></tr>
";

    $showteam = $db->sql_query("SELECT * FROM ".$prefix."_iblplyr WHERE teamname!='$userteam' AND retired='0' ORDER BY ordinal ASC");
    while ($teamlist = $db->sql_fetchrow($showteam)) {

    $draftyear = stripslashes(check_html($teamlist['draftyear'], "nohtml"));
    $exp = stripslashes(check_html($teamlist['exp'], "nohtml"));
    $cy = stripslashes(check_html($teamlist['cy'], "nohtml"));
    $cyt = stripslashes(check_html($teamlist['cyt'], "nohtml"));
    $yearoffreeagency=$draftyear+$exp+$cyt-$cy;

    if ($yearoffreeagency == $freeagentyear)
      {

      $name = stripslashes(check_html($teamlist['name'], "nohtml"));
      $team = stripslashes(check_html($teamlist['teamname'], "nohtml"));
      $tid = stripslashes(check_html($teamlist['tid'], "nohtml"));
      $pid = stripslashes(check_html($teamlist['pid'], "nohtml"));
      $pos = stripslashes(check_html($teamlist['altpos'], "nohtml"));
      $age = stripslashes(check_html($teamlist['age'], "nohtml"));
      $inj = stripslashes(check_html($teamlist['injured'], "nohtml"));

    $getdemands = $db->sql_fetchrow($db->sql_query("SELECT * FROM ".$prefix."_ibl_demands WHERE name='$name'"));

      $dem1 = stripslashes(check_html($getdemands['dem1'], "nohtml"));
      $dem2 = stripslashes(check_html($getdemands['dem2'], "nohtml"));
      $dem3 = stripslashes(check_html($getdemands['dem3'], "nohtml"));
      $dem4 = stripslashes(check_html($getdemands['dem4'], "nohtml"));
      $dem5 = stripslashes(check_html($getdemands['dem5'], "nohtml"));
      $dem6 = stripslashes(check_html($getdemands['dem6'], "nohtml"));

      $r_2ga = stripslashes(check_html($teamlist['r_fga'], "nohtml"));
      $r_2gp = stripslashes(check_html($teamlist['r_fgp'], "nohtml"));
      $r_fta = stripslashes(check_html($teamlist['r_fta'], "nohtml"));
      $r_ftp = stripslashes(check_html($teamlist['r_ftp'], "nohtml"));
      $r_3ga = stripslashes(check_html($teamlist['r_tga'], "nohtml"));
      $r_3gp = stripslashes(check_html($teamlist['r_tgp'], "nohtml"));
      $r_orb = stripslashes(check_html($teamlist['r_orb'], "nohtml"));
      $r_drb = stripslashes(check_html($teamlist['r_drb'], "nohtml"));
      $r_ast = stripslashes(check_html($teamlist['r_ast'], "nohtml"));
      $r_stl = stripslashes(check_html($teamlist['r_stl'], "nohtml"));
      $r_blk = stripslashes(check_html($teamlist['r_blk'], "nohtml"));
      $r_tvr = stripslashes(check_html($teamlist['r_to'], "nohtml"));
      $r_sta = stripslashes(check_html($teamlist['sta'], "nohtml"));
      $r_foul = stripslashes(check_html($teamlist['r_drb'], "nohtml"));
      $r_oo = stripslashes(check_html($teamlist['oo'], "nohtml"));
      $r_do = stripslashes(check_html($teamlist['do'], "nohtml"));
      $r_po = stripslashes(check_html($teamlist['po'], "nohtml"));
      $r_to = stripslashes(check_html($teamlist['to'], "nohtml"));
      $r_od = stripslashes(check_html($teamlist['od'], "nohtml"));
      $r_dd = stripslashes(check_html($teamlist['dd'], "nohtml"));
      $r_pd = stripslashes(check_html($teamlist['pd'], "nohtml"));
      $r_td = stripslashes(check_html($teamlist['td'], "nohtml"));
      $r_totoff=$r_oo+$r_do+$r_po+$r_to;
      $r_totdef=$r_od+$r_dd+$r_pd+$r_td;

echo "      <tr><td>";

if ($rosterspots <= 15) {
echo "<a href=\"http://www.iblhoops.net/modules.php?name=Free_Agency&pa=negotiate&pid=$pid\">Negotiate</a>";
}
echo "</td><td>$pos</td><td><a href=\"http://www.iblhoops.net/modules.php?name=Player&pa=showpage&pid=$pid\">$name</a></td><td><a href=\"team.php?tid=$tid\">$team</a></td><td>$age</td><td>$r_sta</td><td>$r_2ga</td><td>$r_2gp</td><td>$r_fta</td><td>$r_ftp</td><td>$r_3ga</td><td>$r_3gp</td><td>$r_orb</td><td>$r_drb</td><td>$r_ast</td><td>$r_stl</td><td>$r_tvr</td><td>$r_blk</td><td>$r_oo</td><td>$r_do</td><td>$r_po</td><td>$r_to</td><td>$r_od</td><td>$r_dd</td><td>$r_pd</td><td>$r_td</td><td>$dem1</td><td>$dem2</td><td>$dem3</td><td>$dem4</td><td>$dem5</td><td>$dem6</td></tr>
";

} else {
}

}

// ==== END INSERT OF ALL OTHER FREE AGENTS

}

?>