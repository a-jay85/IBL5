<?php

/************************************************************************/
/* PHP-NUKE: Web Portal System                                          */
/* ===========================                                          */
/*                                                                      */
/* Copyright (c) 2002 by Francisco Burzi                                */
/* http://phpnuke.org                                                   */
/*                                                                      */
/* This program is free software. You can redistribute it and/or modify */
/* it under the terms of the GNU General Public License as published by */
/* the Free Software Foundation; either version 2 of the License.       */
/************************************************************************/
/*         Additional security & Abstraction layer conversion           */
/*                           2003 chatserv                              */
/*      http://www.nukefixes.com -- http://www.nukeresources.com        */
/************************************************************************/

if (!eregi("modules.php", $_SERVER['PHP_SELF'])) {
    die ("You can't access this file directly...");
}

require_once("mainfile.php");
include_once "sharedFunctions.php";

$module_name = basename(dirname(__FILE__));
get_lang($module_name);

$pagetitle = "- Team Pages";

echo "<script src='./jslib/sorttable.js'></script>";

function displaytopmenu($tid)
{
    $queryteam="SELECT * FROM nuke_ibl_team_info WHERE teamid = '$tid' ";
    $resultteam=mysql_query($queryteam);

    $color1=mysql_result($resultteam,0,"color1");
    $color2=mysql_result($resultteam,0,"color2");
echo "<table width=600 border=0><tr>";
echo "<td nowrap=\"nowrap\"><a style=\"font:bold 11px Helvetica;text-decoration: none;background-color: #$color2;color: #$color1;padding: 2px 6px 2px 6px;border-top: 1px solid #000000;border-right: 1px solid #000000;border-bottom: 1px solid #000000;border-left: 1px solid #000000;\" href=\"modules.php?name=Team&op=team&tid=$tid\">Team Page</a></td>";
echo "<td nowrap=\"nowrap\"><a style=\"font:bold 11px Helvetica;text-decoration: none;background-color: #$color2;color: #$color1;padding: 2px 6px 2px 6px;border-top: 1px solid #000000;border-right: 1px solid #000000;border-bottom: 1px solid #000000;border-left: 1px solid #000000;\" href=\"modules.php?name=Team&op=drafthistory&tid=$tid\">Draft History</a></td>";
echo "<td nowrap=\"nowrap\"><a style=\"font:bold 11px Helvetica;text-decoration: none;background-color: #$color2;color: #$color1;padding: 2px 6px 2px 6px;border-top: 1px solid #000000;border-right: 1px solid #000000;border-bottom: 1px solid #000000;border-left: 1px solid #000000;\" href=\"modules.php?name=Team&op=schedule&tid=$tid\">Schedule</a></td>";
echo "<td nowrap=\"nowrap\" valign=center><font style=\"font:bold 14px Helvetica;text-decoration: none;\"> | </td>";
echo "<td nowrap=\"nowrap\"><a style=\"font:bold 11px Helvetica;text-decoration: none;background-color: #$color2;color: #$color1;padding: 2px 6px 2px 6px;border-top: 1px solid #000000;border-right: 1px solid #000000;border-bottom: 1px solid #000000;border-left: 1px solid #000000;\" href=\"modules.php?name=Depth_Chart_Entry\">Depth Chart Entry</a></td>";
echo "<td nowrap=\"nowrap\"><a style=\"font:bold 11px Helvetica;text-decoration: none;background-color: #$color2;color: #$color1;padding: 2px 6px 2px 6px;border-top: 1px solid #000000;border-right: 1px solid #000000;border-bottom: 1px solid #000000;border-left: 1px solid #000000;\" href=\"modules.php?name=Team&op=reviewtrades\">Trades/Waiver Moves</a></td>";
echo "<td nowrap=\"nowrap\"><a style=\"font:bold 11px Helvetica;text-decoration: none;background-color: #$color2;color: #$color1;padding: 2px 6px 2px 6px;border-top: 1px solid #000000;border-right: 1px solid #000000;border-bottom: 1px solid #000000;border-left: 1px solid #000000;\" href=\"modules.php?name=Team&op=seteditor\">Offensive Set Editor</a></td>";
echo "<td nowrap=\"nowrap\" valign=center><font style=\"font:bold 14px Helvetica;text-decoration: none;\"> | </td>";
echo "<td nowrap=\"nowrap\"><a style=\"font:bold 11px Helvetica;text-decoration: none;background-color: #$color2;color: #$color1;padding: 2px 6px 2px 6px;border-top: 1px solid #000000;border-right: 1px solid #000000;border-bottom: 1px solid #000000;border-left: 1px solid #000000;\" href=\"modules.php?name=Team&op=team&tid=0\">Free Agent List</a></td>";
echo "<td nowrap=\"nowrap\"><a style=\"font:bold 11px Helvetica;text-decoration: none;background-color: #$color2;color: #$color1;padding: 2px 6px 2px 6px;border-top: 1px solid #000000;border-right: 1px solid #000000;border-bottom: 1px solid #000000;border-left: 1px solid #000000;\" href=\"modules.php?name=Team&op=injuries&tid=$tid\">Injuries</a></td></tr>";

echo "</tr></table>";
echo "<hr>";

// Use double-slashes to disable the Offense Set Editor and Training Preference links during season.
}

/************************************************************************/
/* BEGIN DRAFT HISTORY FUNCTION                                         */
/************************************************************************/

function drafthistory($tid)
{
    global $prefix, $db, $sitename, $admin, $module_name, $user, $cookie;

    include("header.php");
    OpenTable();
    displaytopmenu($tid);

    $sqlc = "SELECT * FROM nuke_ibl_team_info WHERE teamid = $tid";
    $resultc = $db->sql_query($sqlc);
    $rowc = $db->sql_fetchrow($resultc);
    $teamname = $rowc[team_name];

    $sqld = "SELECT * FROM nuke_iblplyr WHERE draftedbycurrentname LIKE '$teamname' ORDER BY draftyear DESC, draftround, draftpickno ASC ";
    $resultd = $db->sql_query($sqld);
    $numd = $db->sql_numrows($resultd);

echo "$teamname Draft History<table><tr><th>Player</th><th>Pos</th><th>Year</th><th>Round</th><th>Pick</th></tr>
";

    while($rowd = $db->sql_fetchrow($resultd))
  {
    $player_pid = $rowd[pid];
    $player_name = $rowd[name];
    $player_pos = $rowd[pos];
    $player_draftyear = $rowd[draftyear];
    $player_draftround = $rowd[draftround];
    $player_draftpickno = $rowd[draftpickno];
    $player_retired = $rowd[retired];

    if ($player_retired == 1)
    {
    echo "<tr><td><a href=\"modules.php?name=Player&pa=showpage&pid=$player_pid\">$player_name</a> (retired)</td><td>$player_pos</td><td>$player_draftyear</td><td>$player_draftround</td><td>$player_draftpickno</td></tr>
";
    } else {
    echo "<tr><td><a href=\"modules.php?name=Player&pa=showpage&pid=$player_pid\">$player_name</a></td><td>$player_pos</td><td>$player_draftyear</td><td>$player_draftround</td><td>$player_draftpickno</td></tr>
";
    }

  }

echo "</table
";

    CloseTable();
    include("footer.php");

}


/************************************************************************/
/* BEGIN TRADE OFFER FUNCTION                                           */
/************************************************************************/

function tradeoffer($username, $bypass=0, $hid=0, $url=0) {
    global $user, $cookie, $sitename, $prefix, $user_prefix, $db, $admin, $broadcast_msg, $my_headlines, $module_name, $subscription_url, $partner;
    $sql = "SELECT * FROM ".$prefix."_bbconfig";
    $result = $db->sql_query($sql);
    while ( $row = $db->sql_fetchrow($result) )
    {
    $board_config[$row['config_name']] = $row['config_value'];
    }
    $sql2 = "SELECT * FROM ".$user_prefix."_users WHERE username='$username'";
    $result2 = $db->sql_query($sql2);
    $num = $db->sql_numrows($result2);
    $userinfo = $db->sql_fetchrow($result2);
    if(!$bypass) cookiedecode($user);
    include("header.php");

// === CODE TO INSERT TRADE STUFF ===

    OpenTable();

    displaytopmenu($tid);

    $teamlogo = $userinfo[user_ibl_team];
    $sql7 = "SELECT * FROM nuke_ibl_team_info ORDER BY teamid ASC ";
    $result7 = $db->sql_query($sql7);

    $sql8 = "SELECT * FROM nuke_iblplyr WHERE teamname='$userinfo[user_ibl_team]' AND retired = '0' ORDER BY ordinal ASC ";
	$result8 = $db->sql_query($sql8);

    $sql8a = "SELECT * FROM ibl_draft_picks WHERE ownerofpick='$userinfo[user_ibl_team]' ORDER BY year,round ASC ";
    $result8a = $db->sql_query($sql8a);

    echo "<hr>
    <form name=\"Trade_Offer\" method=\"post\" action=\"../maketradeoffer.php\">
    <input type=\"hidden\" name=\"Team_Name\" value=\"$teamlogo\">
    <center><img src=\"online/teamgrfx/$teamlogo.jpg\"><br><table border=1 cellspacing=0 cellpadding=0><tr><th colspan=3><center>TRADING MENU</center></th></tr>
<tr><td valign=top><center><B><u>$userinfo[user_ibl_team]</u></b></center>";

$k=0;

    while($row8 = $db->sql_fetchrow($result8)) {
	$player_pos = $row8[pos];
	$player_name = $row8[name];
	$player_pid = $row8[pid];
	$contract_year = $row8[cy];
	$bird_years = $row8[bird];
	$player_contract = $row8["cy$contract_year"];

        echo "<input type=\"hidden\" name=\"index$k\" value=\"$player_pid\"><input type=\"hidden\" name=\"contract$k\" value=\"$player_contract\"><input type=\"hidden\" name=\"type$k\" value=\"1\">";
		if ($bird_years >= 0)
		{
			echo"<input type=\"checkbox\" name=\"check$k\"> $player_contract $player_pos $player_name <br>";
		}else{
			echo"$player_contract $player_pos $player_name <br>";
		}
$k++;
    }

    while($row8a = $db->sql_fetchrow($result8a)) {
	$pick_year = $row8a[year];
	$pick_team = $row8a[teampick];
	$pick_round = $row8a[round];
	$pick_id = $row8a[pickid];

        echo "<input type=\"hidden\" name=\"index$k\" value=\"$pick_id\"><input type=\"hidden\" name=\"type$k\" value=\"0\"><input type=\"checkbox\" name=\"check$k\"> $pick_year $pick_team Round $pick_round
<br>";
$k++;
    }


    echo "        </td><td valign=top><input type=\"hidden\" name=\"half\" value=\"$k\"><input type=\"hidden\" name=\"Team_Name2\" value=\"$partner\">
<center><B><U>$partner</U></B></center>";


    $sql9 = "SELECT * FROM nuke_iblplyr WHERE teamname='$partner' AND retired = '0' ORDER BY ordinal ASC ";
    $result9 = $db->sql_query($sql9);

    $sql9a = "SELECT * FROM ibl_draft_picks WHERE ownerofpick='$partner' ORDER BY year,round ASC ";
    $result9a = $db->sql_query($sql9a);

    while($row9 = $db->sql_fetchrow($result9)) {
	$player_pos = $row9[pos];
	$player_name = $row9[name];
	$player_pid = $row9[pid];
	$contract_year = $row9[cy];
	$bird_years = $row9[bird];
	$player_contract = $row9["cy$contract_year"];

        echo "<input type=\"hidden\" name=\"index$k\" value=\"$player_pid\"><input type=\"hidden\" name=\"contract$k\" value=\"$player_contract\"><input type=\"hidden\" name=\"type$k\" value=\"1\">";
		if ($bird_years >= 0)
		{
			echo"<input type=\"checkbox\" name=\"check$k\"> $player_contract $player_pos $player_name <br>";
		}else{
			echo"$player_contract $player_pos $player_name <br>";
		}
$k++;
    }

    while($row9a = $db->sql_fetchrow($result9a)) {
	$pick_year = $row9a[year];
	$pick_team = $row9a[teampick];
	$pick_round = $row9a[round];
	$pick_id = $row9a[pickid];

        echo "<input type=\"hidden\" name=\"index$k\" value=\"$pick_id\"><input type=\"hidden\" name=\"type$k\" value=\"0\"><input type=\"checkbox\" name=\"check$k\"> $pick_year $pick_team Round $pick_round
<br>";
$k++;
    }

$k--;

echo "<input type=\"hidden\" name=\"counterfields\" value=\"$k\"></td><td valign=top><center><b><u>Make Trade Offer To...</u></b></center>";

    while($row7 = $db->sql_fetchrow($result7)) {
	$team_name = $row7[team_name];
	$team_city = $row7[team_city];
	$team_id = $row7[teamid];


//-------Deadline Code---------
        echo "<a href=\"modules.php?name=Team&op=offertrade&partner=$team_name\">$team_city $team_name</a><br>";
    }

echo "</td></tr><tr><td colspan=3><center><input type=\"submit\" value=\"Make Trade Offer\"></td></tr></form></center></table></center>";
    CloseTable();

// === END INSERT OF TRADE STUFF ===

    include("footer.php");
}
/************************************************************************/
/* END TRADE INFO FUNCTION                                              */
/************************************************************************/

/************************************************************************/
/* BEGIN TRADE REVIEW FUNCTION                                          */
/************************************************************************/

function tradereview($username, $bypass=0, $hid=0, $url=0) {
    global $user, $cookie, $sitename, $prefix, $user_prefix, $db, $admin, $broadcast_msg, $my_headlines, $module_name, $subscription_url, $attrib, $step, $player;
    $sql = "SELECT * FROM ".$prefix."_bbconfig";
    $result = $db->sql_query($sql);
    while ( $row = $db->sql_fetchrow($result) )
    {
    $board_config[$row['config_name']] = $row['config_value'];
    }

// ==== PICKUP LOGGED-IN USER INFO

    $sql2 = "SELECT * FROM ".$user_prefix."_users WHERE username='$username'";
    $result2 = $db->sql_query($sql2);
    $num = $db->sql_numrows($result2);
    $userinfo = $db->sql_fetchrow($result2);
    if(!$bypass) cookiedecode($user);

// ===== END OF INFO PICKUP

    include("header.php");

    OpenTable();

    displaytopmenu($tid);

// === GRAB TEAM INFORMATION FOR LOGGED-IN USER===

    $teamlogo = $userinfo[user_ibl_team];

    echo "<hr>
    <center><img src=\"online/teamgrfx/$teamlogo.jpg\"><br>
";

    $sql3 = "SELECT * FROM nuke_ibl_trade_info ORDER BY tradeofferid ASC ";
    $result3 = $db->sql_query($sql3);
    $num3 = $db->sql_numrows($result3);

        $tradeworkingonnow=0;

echo "<table><tr><td valign=top>REVIEW TRADE OFFERS";

    while($row3 = $db->sql_fetchrow($result3)) {

        $isinvolvedintrade=0;
        $hashammer=0;

	$offerid = $row3[tradeofferid];
	$itemid = $row3[itemid];

// For itemtype (1 = player, 0 = pick)

	$itemtype = $row3[itemtype];
	$from = $row3[from];
	$to = $row3[to];
	$approval = $row3[approval];

        if ($from == $teamlogo)
        {
        $isinvolvedintrade=1;
        }
        if ($to == $teamlogo)
        {
        $isinvolvedintrade=1;
        }
        if ($approval == $teamlogo)
        {
        $hashammer=1;
        }

        if ($isinvolvedintrade == 1)
        {
          if ($offerid == $tradeworkingonnow)
          {
          } else {
          echo "</td></tr></table><table width=595 border=1 cellpadding=0 cellspacing=0 valign=top align=center><tr><td><b><u>TRADE OFFER</u></b><br><table align=right border=1 cellspacing=0 cellpadding=0><tr><td valign=center>";
          if ($hashammer == 1)
            {
            echo "<form name=\"tradeaccept\" method=\"post\" action=\"../accepttradeoffer.php\"><input type=\"hidden\" name=\"offer\" value=\"$offerid\"><input type=\"submit\" value=\"Accept\"></form>";
            } else {
            echo "(Awaiting Approval)";
            }
            echo "</td><td valign=center><form name=\"tradereject\" method=\"post\" action=\"../rejecttradeoffer.php\"><input type=\"hidden\" name=\"offer\" value=\"$offerid\"><input type=\"submit\" value=\"Reject\"></form></td></tr></table>
                ";
          }

          if ($itemtype == 0)
          {
            $sqlgetpick = "SELECT * FROM ibl_draft_picks WHERE pickid = '$itemid'";
            $resultgetpick = $db->sql_query($sqlgetpick);
            $numsgetpick = $db->sql_numrows($resultsgetpick);
            $rowsgetpick = $db->sql_fetchrow($resultgetpick);

            $pickteam = $rowsgetpick[teampick];
            $pickyear = $rowsgetpick[year];
            $pickround = $rowsgetpick[round];

            echo "The $from send the $pickteam $pickyear Round $pickround draft pick to the $to.<br>
";
          } else {
            $sqlgetplyr = "SELECT * FROM nuke_iblplyr WHERE pid = '$itemid'";
            $resultgetplyr = $db->sql_query($sqlgetplyr);
            $numsgetplyr = $db->sql_numrows($resultsgetplyr);
            $rowsgetplyr = $db->sql_fetchrow($resultgetplyr);

            $plyrname = $rowsgetplyr[name];
            $plyrpos = $rowsgetplyr[pos];

            echo "The $from send $plyrpos $plyrname to the $to.<br>
";
          }
        $tradeworkingonnow=$offerid;
        }
      }

echo "</td><td valign=top><center><b><u>Make Trade Offer To...</u></b></center>";

    $sql7 = "SELECT * FROM nuke_ibl_team_info ORDER BY teamid ASC ";
    $result7 = $db->sql_query($sql7);

    while($row7 = $db->sql_fetchrow($result7)) {
	$team_name = $row7[team_name];
	$team_city = $row7[team_city];
	$team_id = $row7[teamid];

 if ($team_name == 'Free Agents')
 {
 } else {
//------Trade Deadline Code---------
        echo "<a href=\"modules.php?name=Team&op=offertrade&partner=$team_name\">$team_city $team_name</a><br>";
 }

}

echo "</td></tr>
<tr><td>
<a href=\"modules.php?name=Team&op=waivers&action=drop\">Drop a player to Waivers</a>
<br>
<a href=\"modules.php?name=Team&op=waivers&action=add\">Add a player from Waivers</a>
<br>
</td></tr></table>";

    CloseTable();
    include("footer.php");
}

/************************************************************************/
/* END TRADE REVIEW FUNCTION                                              */
/************************************************************************/

/************************************************************************/
/* BEGIN TRADE REVIEW CALL                                              */
/************************************************************************/

function reviewtrade($user) {
    global $stop, $module_name, $redirect, $mode, $t, $f, $gfx_chk;
    if(!is_user($user)) {
	include("header.php");
	if ($stop) {
	    OpenTable();
            displaytopmenu($tid);

	    echo "<center><font class=\"title\"><b>"._LOGININCOR."</b></font></center>\n";
	    CloseTable();
	    echo "<br>\n";
	} else {
	    OpenTable();
            displaytopmenu($tid);
	    echo "<center><font class=\"title\"><b>"._USERREGLOGIN."</b></font></center>\n";
	    CloseTable();
	    echo "<br>\n";
	}
	if (!is_user($user)) {
	    OpenTable();
            displaytopmenu($tid);

	    mt_srand ((double)microtime()*1000000);
	    $maxran = 1000000;
	    $random_num = mt_rand(0, $maxran);
	    echo "<form action=\"modules.php?name=$module_name\" method=\"post\">\n"
		."<b>"._USERLOGIN."</b><br><br>\n"
		."<table border=\"0\"><tr><td>\n"
		.""._NICKNAME.":</td><td><input type=\"text\" name=\"username\" size=\"15\" maxlength=\"25\"></td></tr>\n"
		."<tr><td>"._PASSWORD.":</td><td><input type=\"password\" name=\"user_password\" size=\"15\" maxlength=\"20\"></td></tr>\n";
	    if (extension_loaded("gd") AND ($gfx_chk == 2 OR $gfx_chk == 4 OR $gfx_chk == 5 OR $gfx_chk == 7)) {
		echo "<tr><td colspan='2'>"._SECURITYCODE.": <img src='modules.php?name=$module_name&op=gfx&random_num=$random_num' border='1' alt='"._SECURITYCODE."' title='"._SECURITYCODE."'></td></tr>\n"
		    ."<tr><td colspan='2'>"._TYPESECCODE.": <input type=\"text\" NAME=\"gfx_check\" SIZE=\"7\" MAXLENGTH=\"6\"></td></tr>\n"
		    ."<input type=\"hidden\" name=\"random_num\" value=\"$random_num\">\n";
	    }
	    echo "</table><input type=\"hidden\" name=\"redirect\" value=$redirect>\n"
		."<input type=\"hidden\" name=\"mode\" value=$mode>\n"
		."<input type=\"hidden\" name=\"f\" value=$f>\n"
		."<input type=\"hidden\" name=\"t\" value=$t>\n"
		."<input type=\"hidden\" name=\"op\" value=\"login\">\n"
		."<input type=\"submit\" value=\""._LOGIN."\"></form><br>\n\n"
		."<center><font class=\"content\">[ <a href=\"modules.php?name=$module_name&amp;op=pass_lost\">"._PASSWORDLOST."</a> | <a href=\"modules.php?name=$module_name&amp;op=new_user\">"._REGNEWUSER."</a> ]</font></center>\n";
	    CloseTable();
	}
	include("footer.php");
    } elseif (is_user($user)) {
        global $cookie;
        cookiedecode($user);
        tradereview($cookie[1]);
    }

}

/************************************************************************/
/* END TRADE REVIEW CALL                                                */
/************************************************************************/

/************************************************************************/
/* BEGIN TRADE OFFER CALL                                               */
/************************************************************************/
function offertrade($user) {
    global $stop, $module_name, $redirect, $mode, $t, $f, $gfx_chk;
    if(!is_user($user)) {
	include("header.php");
	if ($stop) {
	    OpenTable();
            displaytopmenu($tid);
	    echo "<center><font class=\"title\"><b>"._LOGININCOR."</b></font></center>\n";
	    CloseTable();
	    echo "<br>\n";
	} else {
	    OpenTable();
	    displaytopmenu($tid);
	    echo "<center><font class=\"title\"><b>"._USERREGLOGIN."</b></font></center>\n";
	    CloseTable();
	    echo "<br>\n";
	}
	if (!is_user($user)) {
	    OpenTable();
	    displaytopmenu($tid);
	    mt_srand ((double)microtime()*1000000);
	    $maxran = 1000000;
	    $random_num = mt_rand(0, $maxran);
	    echo "<form action=\"modules.php?name=$module_name\" method=\"post\">\n"
		."<b>"._USERLOGIN."</b><br><br>\n"
		."<table border=\"0\"><tr><td>\n"
		.""._NICKNAME.":</td><td><input type=\"text\" name=\"username\" size=\"15\" maxlength=\"25\"></td></tr>\n"
		."<tr><td>"._PASSWORD.":</td><td><input type=\"password\" name=\"user_password\" size=\"15\" maxlength=\"20\"></td></tr>\n";
	    if (extension_loaded("gd") AND ($gfx_chk == 2 OR $gfx_chk == 4 OR $gfx_chk == 5 OR $gfx_chk == 7)) {
		echo "<tr><td colspan='2'>"._SECURITYCODE.": <img src='modules.php?name=$module_name&op=gfx&random_num=$random_num' border='1' alt='"._SECURITYCODE."' title='"._SECURITYCODE."'></td></tr>\n"
		    ."<tr><td colspan='2'>"._TYPESECCODE.": <input type=\"text\" NAME=\"gfx_check\" SIZE=\"7\" MAXLENGTH=\"6\"></td></tr>\n"
		    ."<input type=\"hidden\" name=\"random_num\" value=\"$random_num\">\n";
	    }
	    echo "</table><input type=\"hidden\" name=\"redirect\" value=$redirect>\n"
		."<input type=\"hidden\" name=\"mode\" value=$mode>\n"
		."<input type=\"hidden\" name=\"f\" value=$f>\n"
		."<input type=\"hidden\" name=\"t\" value=$t>\n"
		."<input type=\"hidden\" name=\"op\" value=\"login\">\n"
		."<input type=\"submit\" value=\""._LOGIN."\"></form><br>\n\n"
		."<center><font class=\"content\">[ <a href=\"modules.php?name=$module_name&amp;op=pass_lost\">"._PASSWORDLOST."</a> | <a href=\"modules.php?name=$module_name&amp;op=new_user\">"._REGNEWUSER."</a> ]</font></center>\n";
	    CloseTable();
	}
	include("footer.php");
    } elseif (is_user($user)) {
        global $cookie;
        cookiedecode($user);
        tradeoffer($cookie[1]);
    }

}


/************************************************************************/
/* END TRADE OFFER CALL                                                 */
/************************************************************************/

/************************************************************************/
/* BEGIN TEAM SCHEDULE PAGE FUNCTION                                    */
/************************************************************************/
function schedule($tid) {
    global $prefix, $db, $sitename, $admin, $module_name, $user, $cookie;
    $tid = intval($tid);

    include("header.php");
    OpenTable();

//============================
// GRAB TEAM COLORS, ET AL
//============================

    $queryteam="SELECT * FROM nuke_ibl_team_info WHERE teamid = '$tid' ";
    $resultteam=mysql_query($queryteam);

    $teamid=mysql_result($resultteam,0,"teamid");
    $team_city=mysql_result($resultteam,0,"team_city");
    $team_name=mysql_result($resultteam,0,"team_name");
    $coach_pts=mysql_result($resultteam,0,"Contract_Coach");
    $color1=mysql_result($resultteam,0,"color1");
    $color2=mysql_result($resultteam,0,"color2");

//=============================
//DISPLAY TOP MENU
//=============================


    displaytopmenu($tid);
    $query="SELECT * FROM `ibl_schedule` WHERE Visitor = $tid OR Home = $tid ORDER BY Date ASC";
    $result=mysql_query($query);
    $num=mysql_numrows($result);
    $year=mysql_result($result,0,"Year");
    $year1=$year+1;
    $wins=0;
    $losses=0;
    echo "<img src=\"../images/logo/$tid.jpg\">
      <table><tr><td valign=top>
      <table width=600 border=1>
      <tr bgcolor=$color1><td colspan=26><font color=$color2 size=\"12\"><b><center>Team Schedule</center></b></font></td></tr>
      <tr bgcolor=$color2><td colspan=26><font color=$color1><b><center>November</center></b></font></td></tr>
      <tr bgcolor=$color2><td><font color=$color1><b>Date</font></td><td><font color=$color1><b>Visitor</font></td><td><font color=$color1><b>Score</font></td><td><font color=$color1><b>Home</font></td><td><font color=$color1><b>Score</font></td><td><font color=$color1><b>Box Score</font></td><td><font color=$color1><b>Record</font></td><td><font color=$color1><b>Streak</font></td></tr>
";
      list ($wins, $losses, $wstreak, $lstreak)=boxscore ($year,'11',$tid,$wins,$losses, $wstreak, $lstreak);

    echo "<tr bgcolor=$color1><td colspan=26><font color=$color2><b><center>December</center></b></font></td></tr>
      <tr bgcolor=$color1><td><font color=$color2><b>Date</font></td><td><font color=$color2><b>Visitor</font></td><td><font color=$color2><b>Score</font></td><td><font color=$color2><b>Home</font></td><td><font color=$color2><b>Score</font></td><td><font color=$color2><b>Box Score</b></font></td><td><font color=$color2><b>Record</font></td><td><font color=$color2><b>Streak</font></td></tr>";
      list ($wins, $losses, $wstreak, $lstreak)=boxscore ($year,'12',$tid,$wins,$losses, $wstreak, $lstreak);
    echo "<tr bgcolor=$color2><td colspan=26><font color=$color1><b><center>January</center></b></font></td></tr>
      <tr bgcolor=$color2><td><font color=$color1><b>Date</font></td><td><font color=$color1><b>Visitor</font></td><td><font color=$color1><b>Score</font></td><td><font color=$color1><b>Home</font></td><td><font color=$color1><b>Score</font></td><td><font color=$color1><b>Box Score</b></font></td><td><font color=$color1><b>Record</font></td><td><font color=$color1><b>Streak</font></td></tr>";
      list ($wins, $losses, $wstreak, $lstreak)=boxscore ($year1,'01',$tid,$wins,$losses, $wstreak, $lstreak);
    echo "<tr bgcolor=$color1><td colspan=26><font color=$color2><b><center>February</center></b></font></td></tr>
      <tr bgcolor=$color1><td><font color=$color2><b>Date</font></td><td><font color=$color2><b>Visitor</font></td><td><font color=$color2><b>Score</font></td><td><font color=$color2><b>Home</font></td><td><font color=$color2><b>Score</font></td><td><font color=$color2><b>Box Score</b></font></td><td><font color=$color2><b>Record</font></td><td><font color=$color2><b>Streak</font></td></tr>";
      list ($wins, $losses, $wstreak, $lstreak)=boxscore ($year1,'02',$tid,$wins,$losses, $wstreak, $lstreak);
    echo "<tr bgcolor=$color2><td colspan=26><font color=$color1><b><center>March</center></b></font></td></tr>
      <tr bgcolor=$color2><td><font color=$color1><b>Date</font></td><td><font color=$color1><b>Visitor</font></td><td><font color=$color1><b>Score</font></td><td><font color=$color1><b>Home</font></td><td><font color=$color1><b>Score</font></td><td><font color=$color1><b>Box Score</b></font></td><td><font color=$color1><b>Record</font></td><td><font color=$color1><b>Streak</font></td></tr>";
      list ($wins, $losses, $wstreak, $lstreak)=boxscore ($year1,'03',$tid,$wins,$losses, $wstreak, $lstreak);
    echo "<tr bgcolor=$color1><td colspan=26><font color=$color2><b><center>April</center></b></font></td></tr>
      <tr bgcolor=$color1><td><font color=$color2><b>Date</font></td><td><font color=$color2><b>Visitor</font></td><td><font color=$color2><b>Score</font></td><td><font color=$color2><b>Home</font></td><td><font color=$color2><b>Score</font></td><td><font color=$color2><b>Box Score</b></font></td><td><font color=$color2><b>Record</font></td><td><font color=$color2><b>Streak</font></td></tr>";
      list ($wins, $losses)=boxscore ($year1,'04',$tid,$wins,$losses, $wstreak,$lstreak);
}

function boxscore ($year, $month, $tid, $wins, $losses, $wstreak, $lstreak) {

	$query="SELECT * FROM `ibl_schedule` WHERE (Visitor = $tid AND Date BETWEEN '$year-$month-01' AND '$year-$month-31') OR (Home = $tid AND Date BETWEEN '$year-$month-01' AND '$year-$month-31') ORDER BY Date ASC";
	$result=mysql_query($query);
	$num=mysql_numrows($result);
	$i = 0;

	while ($i < $num) {
		$date=mysql_result($result,$i,"Date");
		$visitor=mysql_result($result,$i,"Visitor");
		$VScore=mysql_result($result,$i,"VScore");
		$home=mysql_result($result,$i,"Home");
		$HScore=mysql_result($result,$i,"HScore");
		$boxid=mysql_result($result,$i,"BoxID");
		$SchedID=mysql_result($result,$i,"SchedID");

		$vname=teamname($visitor);
		$hname=teamname($home);
		if ($tid == $visitor) {
			if ($VScore > $HScore) {
				$wins=$wins+1;
				$winlosscolor="green";
				$wstreak=1+$wstreak;
				$lstreak=0;

			}else{
				$losses=$losses+1;
				$winlosscolor="red";
				$wstreak=0;
				$lstreak=1+$lstreak;
			}
		}else{
			if ($VScore > $HScore) {
				$losses=$losses+1;
				$winlosscolor="red";
				$wstreak=0;
				$lstreak=1+$lstreak;
			}else{
				$wins=$wins+1;
				$winlosscolor="green";
				$wstreak=1+$wstreak;
				$lstreak=0;
			}
		}
		if ($wstreak > $lstreak) {
			$streak="W $wstreak";
		}else{
			$streak="L $lstreak";
		}

		if(($i % 2)==0) {
			$bgcolor="FFFFFF";
		}else{
			$bgcolor="EEEEEE";
		}

		if ($VScore > $HScore){
			echo "<tr bgcolor=$bgcolor><td>$date</td><td><b>$vname</b></td><td><b><font color=$winlosscolor>$VScore</font></b></td><td>$hname</b></td><td><b><font color=$winlosscolor>$HScore</font></b></td><td><a href=\"../ibl/IBL/box$boxid.htm\">View</a></td><td>$wins - $losses</td><td>$streak</td></tr>";
		}else if ($VScore < $HScore) {
			echo "<tr bgcolor=$bgcolor><td>$date</td><td>$vname</b></td><td><b><font color=$winlosscolor>$VScore</font></b></td><td><b>$hname</b></td><td><b><font color=$winlosscolor>$HScore</font></b></td><td><a href=\"../ibl/IBL/box$boxid.htm\">View</a></td><td>$wins - $losses</td><td>$streak</td></tr>";
		}else{
			echo "<tr><td>$date</td><td>$vname</b></td><td></td><td>$hname</td><td></td><td></td></tr>";
		}
	$i++;
	}
	return array($wins,$losses,$wstreak,$lstreak);
}

function teamname ($teamid) {
	$query="SELECT * FROM nuke_ibl_team_info WHERE teamid = $teamid";
	$result=mysql_query($query);
	$name=mysql_result($result, 0, "team_name");
	return $name;
}


/************************************************************************/
/* BEGIN TEAM PAGE FUNCTION                                             */
/************************************************************************/
function team($tid) {
    global $prefix, $db, $sitename, $admin, $module_name, $user, $cookie;
    $tid = intval($tid);

    $yr = $_REQUEST['yr'];
    $fayr = $_REQUEST['fayr'];

    $display = $_REQUEST['display'];
    if ($display == NULL)
    {
      $display="ratings";
    }

    include("header.php");
    OpenTable();

//============================
// GRAB TEAM COLORS, ET AL
//============================

    $queryteam="SELECT * FROM nuke_ibl_team_info WHERE teamid = '$tid' ";
    $resultteam=mysql_query($queryteam);

    $teamid=mysql_result($resultteam,0,"teamid");
    $team_city=mysql_result($resultteam,0,"team_city");
    $team_name=mysql_result($resultteam,0,"team_name");
    $coach_pts=mysql_result($resultteam,0,"Contract_Coach");
    $color1=mysql_result($resultteam,0,"color1");
    $color2=mysql_result($resultteam,0,"color2");
    $owner_name=mysql_result($resultteam,0,"owner_name");
    $owner_email=mysql_result($resultteam,0,"owner_email");
    $skype=mysql_result($resultteam,0,"skype");
    $aim=mysql_result($resultteam,0,"aim");
    $msn=mysql_result($resultteam,0,"msn");
    $Extension=mysql_result($resultteam,0,"Used_Extension_This_Season");

    if ($Extension == 0)
    {
      $Extension_Text="contract extension still available";
    } else {
      $Extension_Text="contract extension used for this season";
    }

//=============================
//DISPLAY TOP MENU
//=============================

    displaytopmenu($tid);

//=============================
//GET CONTRACT AMOUNTS CORRECT
//=============================

    $queryfaon="SELECT * FROM nuke_modules WHERE mid = '26' ORDER BY title ASC";
    $resultfaon=mysql_query($queryfaon);
    $numfaon=mysql_numrows($resultfaon);
    $faon=mysql_result($resultfaon,0,"active");

if ($tid == 0) // Team 0 is the Free Agents; we want a query that will pick up all of their players.
{
    if ($faon==0)
    {
      $query="SELECT * FROM nuke_iblplyr WHERE ordinal > '959' AND retired = 0 AND in_euro = '0' ORDER BY ordinal ASC";
    } else {
      $query="SELECT * FROM nuke_iblplyr WHERE ordinal > '959' AND retired = 0 AND cyt != cy AND in_euro = '0' ORDER BY ordinal ASC";
    }
    $result=mysql_query($query);
    $num=mysql_numrows($result);
} else if ($tid == "-1") { // SHOW ENTIRE LEAGUE
      $query="SELECT * FROM nuke_iblplyr WHERE retired = 0 ORDER BY ordinal ASC";
      $result=mysql_query($query);
      $num=mysql_numrows($result);
} else { // If not Free Agents, use the code below instead.
    if ($yr != "")
    {
      $query="SELECT * FROM nuke_iblhist WHERE teamid = '$tid' AND year = '$yr' ORDER BY name ASC";
    } else if ($faon==0)
    {
      $query="SELECT * FROM nuke_iblplyr WHERE tid = '$tid' AND retired = 0 ORDER BY ordinal ASC";
    } else {
      $query="SELECT * FROM nuke_iblplyr WHERE tid = '$tid' AND retired = 0 AND cyt != cy ORDER BY ordinal ASC";
    }
    $result=mysql_query($query);
    $num=mysql_numrows($result);
}

    echo "<table><tr><td valign=top>
      <img src=\"../images/logo/$tid.jpg\">
      ";

/* =================== INSERT STARTERS =========== */

$startingPG=NULL;
$startingSG=NULL;
$startingSF=NULL;
$startingPF=NULL;
$startingC=NULL;

$startingPGpid=NULL;
$startingSGpid=NULL;
$startingSFpid=NULL;
$startingPFpid=NULL;
$startingCpid=NULL;

$s=0;

while ($s < $num)
{

if (mysql_result($result,$s,"PGDepth")==1)
{
$startingPG=mysql_result($result,$s,"name");
$startingPGpid=mysql_result($result,$s,"pid");
} else {
}

if (mysql_result($result,$s,"SGDepth") == 1)
{
$startingSG=mysql_result($result,$s,"name");
$startingSGpid=mysql_result($result,$s,"pid");
} else {
}

if (mysql_result($result,$s,"SFDepth") == 1)
{
$startingSF=mysql_result($result,$s,"name");
$startingSFpid=mysql_result($result,$s,"pid");
} else {
}

if (mysql_result($result,$s,"PFDepth") == 1)
{
$startingPF=mysql_result($result,$s,"name");
$startingPFpid=mysql_result($result,$s,"pid");
} else {
}

if (mysql_result($result,$s,"CDepth") == 1)
{
$startingC=mysql_result($result,$s,"name");
$startingCpid=mysql_result($result,$s,"pid");
} else {
}

$s++;

}

if ($yr != "")
{
  echo "<center><h1>$yr $team_name</h1></center>";
}

if ($tid != 0 AND $yr == "")
{

$starters_table="<table align=\"center\" border=1 cellpadding=1 cellspacing=1><tr bgcolor=$color1><td colspan=5><font color=$color2><center><b>Last Chunk's Starters</b></center></font></td></tr>
<tr><td><center><b>Starting PG</b><br><img src=\"../images/player/$startingPGpid.jpg\"><br><a href=\"modules.php?name=Player&pa=showpage&pid=$startingPGpid\">$startingPG</a></td>
<td><center><b>Starting SG</b><br><img src=\"../images/player/$startingSGpid.jpg\"><br><a href=\"modules.php?name=Player&pa=showpage&pid=$startingSGpid\">$startingSG</a></td>
<td><center><b>Starting SF</b><br><img src=\"../images/player/$startingSFpid.jpg\"><br><a href=\"modules.php?name=Player&pa=showpage&pid=$startingSFpid\">$startingSF</a></td>
<td><center><b>Starting PF</b><br><img src=\"../images/player/$startingPFpid.jpg\"><br><a href=\"modules.php?name=Player&pa=showpage&pid=$startingPFpid\">$startingPF</a></td>
<td><center><b>Starting C</b><br><img src=\"../images/player/$startingCpid.jpg\"><br><a href=\"modules.php?name=Player&pa=showpage&pid=$startingCpid\">$startingC</a></td></tr></table>
";

}

// END OF INSERTION OF STARTERS

// BEGIN RATINGS TABLE

      $table_ratings="<table  align=\"center\" class=\"sortable\">
      <thead>
      <tr bgcolor=$color1><th><font color=$color2>Pos</font></th><th><font color=$color2>Player</font></th><th><font color=$color2>Age</font></th><th><font color=$color2>Sta</font></th><th><font color=$color2>2ga</font></th><th><font color=$color2>2g%</font></th><th><font color=$color2>fta</font></th><th><font color=$color2>ft%</font></th><th><font color=$color2>3ga</font></th><th><font color=$color2>3g%</font></th><th><font color=$color2>orb</font></th><th><font color=$color2>drb</font></th><th><font color=$color2>ast</font></th><th><font color=$color2>stl</font></th><th><font color=$color2>tvr</font></th><th><font color=$color2>blk</font></th><th><font color=$color2>o-o</font></th><th><font color=$color2>d-o</font></th><th><font color=$color2>p-o</font></th><th><font color=$color2>t-o</font></th><th><font color=$color2>o-d</font></th><th><font color=$color2>d-d</font></th><th><font color=$color2>p-d</font></th><th><font color=$color2>t-d</font></th><th><font color=$color2>Foul</font></th><th><font color=$color2>Inj</font></th></tr>
</thead>
<tbody>
";

    $i=0;

    while ($i < $num)
    {

   if ($yr == "")
   {

    $name=mysql_result($result,$i,"name");
    $team=mysql_result($result,$i,"teamname");
    $pid=mysql_result($result,$i,"pid");
    $pos=mysql_result($result,$i,"altpos");
    $p_ord=mysql_result($result,$i,"ordinal");
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
	$r_bird=mysql_result($result,$i,"bird");

$draftyear=mysql_result($result,$i,"draftyear");
$exp=mysql_result($result,$i,"exp");
$cy=mysql_result($result,$i,"cy");
$cyt=mysql_result($result,$i,"cyt");

	$yearoffreeagency=$draftyear+$exp+$cyt-$cy;

	} else {

     $name=mysql_result($result,$i,"name");
     $team=mysql_result($result,$i,"team");
     $pid=mysql_result($result,$i,"pid");

     $r_2ga=mysql_result($result,$i,"r_2ga");
     $r_2gp=mysql_result($result,$i,"r_2gp");
     $r_fta=mysql_result($result,$i,"r_fta");
     $r_ftp=mysql_result($result,$i,"r_ftp");
     $r_3ga=mysql_result($result,$i,"r_3ga");
     $r_3gp=mysql_result($result,$i,"r_3gp");
     $r_orb=mysql_result($result,$i,"r_orb");
     $r_drb=mysql_result($result,$i,"r_drb");
     $r_ast=mysql_result($result,$i,"r_ast");
     $r_stl=mysql_result($result,$i,"r_stl");
     $r_blk=mysql_result($result,$i,"r_blk");
     $r_tvr=mysql_result($result,$i,"r_tvr");
     $r_totoff=mysql_result($result,$i,"r_oo")+mysql_result($result,$i,"r_do")+mysql_result($result,$i,"r_po")+mysql_result($result,$i,"r_to");
     $r_totdef=mysql_result($result,$i,"r_od")+mysql_result($result,$i,"r_dd")+mysql_result($result,$i,"r_pd")+mysql_result($result,$i,"r_td");
     $r_oo=mysql_result($result,$i,"r_oo");
     $r_do=mysql_result($result,$i,"r_do");
     $r_po=mysql_result($result,$i,"r_po");
     $r_to=mysql_result($result,$i,"r_to");
     $r_od=mysql_result($result,$i,"r_od");
     $r_dd=mysql_result($result,$i,"r_dd");
     $r_pd=mysql_result($result,$i,"r_pd");
     $r_td=mysql_result($result,$i,"r_td");


	}

	if(($i % 2)==0) {
		$bgcolor="FFFFFF";
	}else{
		$bgcolor="EEEEEE";
	}

if ($tid == 0)
{
    $table_ratings=$table_ratings."      <tr bgcolor=$bgcolor><td>$pos</td><td><a href=\"modules.php?name=Player&pa=showpage&pid=$pid\">$name</a></td><td>$age</td><td>$r_sta</td><td>$r_2ga</td><td>$r_2gp</td><td>$r_fta</td><td>$r_ftp</td><td>$r_3ga</td><td>$r_3gp</td><td>$r_orb</td><td>$r_drb</td><td>$r_ast</td><td>$r_stl</td><td>$r_tvr</td><td>$r_blk</td><td>$r_oo</td><td>$r_do</td><td>$r_po</td><td>$r_to</td><td>$r_od</td><td>$r_dd</td><td>$r_pd</td><td>$r_td</td><td>$r_foul</td><td>$inj</td></tr>
";
} else {
if ($p_ord > 959)
{
    $table_ratings=$table_ratings."      <tr bgcolor=$bgcolor><td>$pos</td><td>(<a href=\"modules.php?name=Player&pa=showpage&pid=$pid\">$name)*</a></td><td>$age</td><td>$r_sta</td><td>$r_2ga</td><td>$r_2gp</td><td>$r_fta</td><td>$r_ftp</td><td>$r_3ga</td><td>$r_3gp</td><td>$r_orb</td><td>$r_drb</td><td>$r_ast</td><td>$r_stl</td><td>$r_tvr</td><td>$r_blk</td><td>$r_oo</td><td>$r_do</td><td>$r_po</td><td>$r_to</td><td>$r_od</td><td>$r_dd</td><td>$r_pd</td><td>$r_td</td><td>$r_foul</td><td>$inj</td></tr>
";
} elseif ($r_bird == 0) {
    $table_ratings=$table_ratings."      <tr bgcolor=$bgcolor><td>$pos</td><td><i><a href=\"modules.php?name=Player&pa=showpage&pid=$pid\">$name</i></a></td><td>$age</td><td>$r_sta</td><td>$r_2ga</td><td>$r_2gp</td><td>$r_fta</td><td>$r_ftp</td><td>$r_3ga</td><td>$r_3gp</td><td>$r_orb</td><td>$r_drb</td><td>$r_ast</td><td>$r_stl</td><td>$r_tvr</td><td>$r_blk</td><td>$r_oo</td><td>$r_do</td><td>$r_po</td><td>$r_to</td><td>$r_od</td><td>$r_dd</td><td>$r_pd</td><td>$r_td</td><td>$r_foul</td><td>$inj</td></tr>
";
} else if ($fayr == "" OR $yearoffreeagency == $fayr) {
   $table_ratings=$table_ratings."      <tr bgcolor=$bgcolor><td>$pos</td><td><a href=\"modules.php?name=Player&pa=showpage&pid=$pid\">$name</a></td><td>$age</td><td>$r_sta</td><td>$r_2ga</td><td>$r_2gp</td><td>$r_fta</td><td>$r_ftp</td><td>$r_3ga</td><td>$r_3gp</td><td>$r_orb</td><td>$r_drb</td><td>$r_ast</td><td>$r_stl</td><td>$r_tvr</td><td>$r_blk</td><td>$r_oo</td><td>$r_do</td><td>$r_po</td><td>$r_to</td><td>$r_od</td><td>$r_dd</td><td>$r_pd</td><td>$r_td</td><td>$r_foul</td><td>$inj</td></tr>
";
}
}

    $i++;

    }

$table_ratings=$table_ratings."
      </tbody>
    </table>
";

if ($tid != 0)
{

$table_totals=$table_totals."
      <table align=\"center\" class=\"sortable\">
        <thead>
<tr bgcolor=$color1><th><font color=$color2>Pos</font></th><td colspan=3><font color=$color2>Player</font></th><th><font color=$color2>g</font></th><th><font color=$color2>gs</font></th><th><font color=$color2>min</font></th><th><font color=$color2>fgm</font></th><th><font color=$color2>fga</font></th><th><font color=$color2>ftm</font></th><th><font color=$color2>fta</font></th><th><font color=$color2>3gm</font></th><th><font color=$color2>3ga</font></th><th><font color=$color2>orb</font></th><th><font color=$color2>reb</font></th><th><font color=$color2>ast</font></th><th><font color=$color2>stl</font></th><th><font color=$color2>to</font></th><th><font color=$color2>blk</font></th><th><font color=$color2>pf</font></th><th><font color=$color2>pts</font></th></tr>
</thead>
<tbody>
";


$i=0;

/* =======================STATS */

while ($i < $num)
{
$name=mysql_result($result,$i,"name");
$pos=mysql_result($result,$i,"altpos");
$p_ord=mysql_result($result,$i,"ordinal");
$pid=mysql_result($result,$i,"pid");

$draftyear=mysql_result($result,$i,"draftyear");
$exp=mysql_result($result,$i,"exp");
$cy=mysql_result($result,$i,"cy");
$cyt=mysql_result($result,$i,"cyt");

$yearoffreeagency=$draftyear+$exp+$cyt-$cy;

if ($yr == "")
{

$stats_gm=mysql_result($result,$i,"stats_gm");
$stats_gs=mysql_result($result,$i,"stats_gs");
$stats_min=mysql_result($result,$i,"stats_min");
$stats_fgm=mysql_result($result,$i,"stats_fgm");
$stats_fga=mysql_result($result,$i,"stats_fga");
$stats_ftm=mysql_result($result,$i,"stats_ftm");
$stats_fta=mysql_result($result,$i,"stats_fta");
$stats_tgm=mysql_result($result,$i,"stats_3gm");
$stats_tga=mysql_result($result,$i,"stats_3ga");
$stats_orb=mysql_result($result,$i,"stats_orb");
$stats_drb=mysql_result($result,$i,"stats_drb");
$stats_ast=mysql_result($result,$i,"stats_ast");
$stats_stl=mysql_result($result,$i,"stats_stl");
$stats_to=mysql_result($result,$i,"stats_to");
$stats_blk=mysql_result($result,$i,"stats_blk");
$stats_pf=mysql_result($result,$i,"stats_pf");
$stats_reb=$stats_orb+$stats_drb;
$stats_pts=2*$stats_fgm+$stats_ftm+$stats_tgm;

} else {

$stats_gm=mysql_result($result,$i,"gm");
$stats_min=mysql_result($result,$i,"min");
$stats_fgm=mysql_result($result,$i,"fgm");
$stats_fga=mysql_result($result,$i,"fga");
$stats_ftm=mysql_result($result,$i,"ftm");
$stats_fta=mysql_result($result,$i,"fta");
$stats_tgm=mysql_result($result,$i,"3gm");
$stats_tga=mysql_result($result,$i,"3ga");
$stats_orb=mysql_result($result,$i,"orb");
$stats_ast=mysql_result($result,$i,"ast");
$stats_stl=mysql_result($result,$i,"stl");
$stats_to=mysql_result($result,$i,"tvr");
$stats_blk=mysql_result($result,$i,"blk");
$stats_pf=mysql_result($result,$i,"pf");
$stats_reb=mysql_result($result,$i,"reb");
$stats_pts=2*$stats_fgm+$stats_ftm+$stats_tgm;

}

	if(($i % 2)==0) {
		$bgcolor="FFFFFF";
	}else{
		$bgcolor="EEEEEE";
	}

if ($tid == 0)
{
$table_totals=$table_totals."      <tr bgcolor=$bgcolor><td><center>$pos</center></td><td colspan=3><a href=\"modules.php?name=Player&pa=showpage&pid=$pid\">$name</a></td><td><center>$stats_gm</center></td><td><center>$stats_gs</center></td><td><center>$stats_min</center></td><td><center>$stats_fgm</center></td><td><center>$stats_fga</center></td><td><center>$stats_ftm</center></td><td><center>$stats_fta</center></td><td><center>$stats_tgm</center></td><td><center>$stats_tga</center></td><td><center>$stats_orb</center></td><td><center>$stats_reb</center></td><td><center>$stats_ast</center></td><td><center>$stats_stl</center></td><td><center>$stats_to</center></td><td><center>$stats_blk</center></td><td><center>$stats_pf</center></td><td><center>$stats_pts</center></td></tr>
";
} else {
if ($p_ord > 959)
{
$table_totals=$table_totals."      <tr bgcolor=$bgcolor><td><center>$pos</center></td><td colspan=3><a href=\"modules.php?name=Player&pa=showpage&pid=$pid\">($name)*</a></td><td><center>$stats_gm</center></td><td><center>$stats_gs</center></td><td><center>$stats_min</center></td><td><center>$stats_fgm</center></td><td><center>$stats_fga</center></td><td><center>$stats_ftm</center></td><td><center>$stats_fta</center></td><td><center>$stats_tgm</center></td><td><center>$stats_tga</center></td><td><center>$stats_orb</center></td><td><center>$stats_reb</center></td><td><center>$stats_ast</center></td><td><center>$stats_stl</center></td><td><center>$stats_to</center></td><td><center>$stats_blk</center></td><td><center>$stats_pf</center></td><td><center>$stats_pts</center></td></tr>
";
} else if ($fayr == "" OR $yearoffreeagency == $fayr) {
$table_totals=$table_totals."      <tr bgcolor=$bgcolor><td><center>$pos</center></td><td colspan=3><a href=\"modules.php?name=Player&pa=showpage&pid=$pid\">$name</a></td><td><center>$stats_gm</center></td><td><center>$stats_gs</center></td><td><center>$stats_min</center></td><td><center>$stats_fgm</center></td><td><center>$stats_fga</center></td><td><center>$stats_ftm</center></td><td><center>$stats_fta</center></td><td><center>$stats_tgm</center></td><td><center>$stats_tga</center></td><td><center>$stats_orb</center></td><td><center>$stats_reb</center></td><td><center>$stats_ast</center></td><td><center>$stats_stl</center></td><td><center>$stats_to</center></td><td><center>$stats_blk</center></td><td><center>$stats_pf</center></td><td><center>$stats_pts</center></td></tr>
";
}
}

$i++;

}

$table_totals=$table_totals."      </tbody>
<tfoot>";

// ==== INSERT TEAM OFFENSE AND DEFENSE TOTALS ====

$queryTeamOffenseTotals="SELECT * FROM ibl_team_offense_stats WHERE team = '$team_name' AND year = '0'";
$resultTeamOffenseTotals=mysql_query($queryTeamOffenseTotals);
$numTeamOffenseTotals=mysql_numrows($resultTeamOffenseTotals);

$t=0;

while ($t < $numTeamOffenseTotals)
{

$team_off_games=mysql_result($resultTeamOffenseTotals,$t,"games");
$team_off_minutes=mysql_result($resultTeamOffenseTotals,$t,"minutes");
$team_off_fgm=mysql_result($resultTeamOffenseTotals,$t,"fgm");
$team_off_fga=mysql_result($resultTeamOffenseTotals,$t,"fga");
$team_off_ftm=mysql_result($resultTeamOffenseTotals,$t,"ftm");
$team_off_fta=mysql_result($resultTeamOffenseTotals,$t,"fta");
$team_off_tgm=mysql_result($resultTeamOffenseTotals,$t,"tgm");
$team_off_tga=mysql_result($resultTeamOffenseTotals,$t,"tga");
$team_off_orb=mysql_result($resultTeamOffenseTotals,$t,"orb");
$team_off_reb=mysql_result($resultTeamOffenseTotals,$t,"reb");
$team_off_ast=mysql_result($resultTeamOffenseTotals,$t,"ast");
$team_off_stl=mysql_result($resultTeamOffenseTotals,$t,"stl");
$team_off_tvr=mysql_result($resultTeamOffenseTotals,$t,"tvr");
$team_off_blk=mysql_result($resultTeamOffenseTotals,$t,"blk");
$team_off_pf=mysql_result($resultTeamOffenseTotals,$t,"pf");
$team_off_pts=$team_off_fgm+$team_off_fgm+$team_off_ftm+$team_off_tgm;

if ($yr == "")
{

$table_totals=$table_totals."
<tr><td colspan=4><b>$team_name Offense</td><td><center><b>$team_off_games</center></td><td><center><b>$team_off_games</center></td><td><center><b>$team_off_minutes</center></td><td><center><b>$team_off_fgm</center></td><td><center><b>$team_off_fga</b></center></td><td><center><b>$team_off_ftm</center></td><td><center><b>$team_off_fta</b></center></td><td><center><b>$team_off_tgm</center></td><td><center><b>$team_off_tga</b></center></td><td><center><b>$team_off_orb</center></td><td><center><b>$team_off_reb</center></td><td><center><b>$team_off_ast</center></td><td><center><b>$team_off_stl</center></td><td><center><b>$team_off_tvr</center></td><td><center><b>$team_off_blk</center></td><td><center><b>$team_off_pf</center></td><td><center><b>$team_off_pts</center></td></tr>";

}

$t++;

}


$queryTeamDefenseTotals="SELECT * FROM ibl_team_defense_stats WHERE team = '$team_name' AND year = '0'";
$resultTeamDefenseTotals=mysql_query($queryTeamDefenseTotals);
$numTeamDefenseTotals=mysql_numrows($resultTeamDefenseTotals);

$t=0;

while ($t < $numTeamDefenseTotals)
{

$team_def_games=mysql_result($resultTeamDefenseTotals,$t,"games");
$team_def_minutes=mysql_result($resultTeamDefenseTotals,$t,"minutes");
$team_def_fgm=mysql_result($resultTeamDefenseTotals,$t,"fgm");
$team_def_fga=mysql_result($resultTeamDefenseTotals,$t,"fga");
$team_def_ftm=mysql_result($resultTeamDefenseTotals,$t,"ftm");
$team_def_fta=mysql_result($resultTeamDefenseTotals,$t,"fta");
$team_def_tgm=mysql_result($resultTeamDefenseTotals,$t,"tgm");
$team_def_tga=mysql_result($resultTeamDefenseTotals,$t,"tga");
$team_def_orb=mysql_result($resultTeamDefenseTotals,$t,"orb");
$team_def_reb=mysql_result($resultTeamDefenseTotals,$t,"reb");
$team_def_ast=mysql_result($resultTeamDefenseTotals,$t,"ast");
$team_def_stl=mysql_result($resultTeamDefenseTotals,$t,"stl");
$team_def_tvr=mysql_result($resultTeamDefenseTotals,$t,"tvr");
$team_def_blk=mysql_result($resultTeamDefenseTotals,$t,"blk");
$team_def_pf=mysql_result($resultTeamDefenseTotals,$t,"pf");
$team_def_pts=$team_def_fgm+$team_def_fgm+$team_def_ftm+$team_def_tgm;

if ($yr == "")
{

$table_totals=$table_totals."      <tr><td colspan=4><b>$team_name Defense</td><td><center><b>$team_def_games</center></td><td><center><b>$team_def_games</center></td><td><center><b>$team_def_minutes</center></td><td><center><b>$team_def_fgm</center></td><td><center><b>$team_def_fga</b></center></td><td><center><b>$team_def_ftm</center></td><td><center><b>$team_def_fta</b></center></td><td><center><b>$team_def_tgm</b></center></td><td><center><b>$team_def_tga</b></center></td><td><center><b>$team_def_orb</center></td><td><center><b>$team_def_reb</center></td><td><center><b>$team_def_ast</center></td><td><center><b>$team_def_stl</center></td><td><center><b>$team_def_tvr</center></td><td><center><b>$team_def_blk</center></td><td><center><b>$team_def_pf</center></td><td><center><b>$team_def_pts</center></td></tr>
";

}

$t++;

}

$table_totals=$table_totals."</tfoot>
</table>
";

$table_averages=$table_averages."<table  align=\"center\" class=\"sortable\">
<thead>
<tr bgcolor=$color1><th><font color=$color2>Pos</font></th><td colspan=3><font color=$color2>Player</font></th><th><font color=$color2>g</font></th><th><font color=$color2>gs</font></th><th><font color=$color2>min</font></th><th><font color=$color2>fgp</font></th><th><font color=$color2>ftp</font></th><th><font color=$color2>3gp</font></th><th><font color=$color2>orb</font></th><th><font color=$color2>reb</font></th><th><font color=$color2>ast</font></th><th><font color=$color2>stl</font></th><th><font color=$color2>to</font></th><th><font color=$color2>blk</font></th><th><font color=$color2>pf</font></th><th><font color=$color2>pts</font></th></tr>
</thead>
<tbody>";

/* =======================AVERAGES */

if ($yr == "")
{
       if ($tid != "-1")
       {
       $per_query="SELECT * FROM ibl_per_calculator WHERE team_id = '$tid' ";
       } else {
       $per_query="SELECT * FROM ibl_per_calculator ";
       }
      $per_result=mysql_query($per_query);
      $per_rows=mysql_numrows($per_result);

      $per=0;
      while ($per < $per_rows)
      {
         $PER_array[$per]['name']=mysql_result($per_result,$per,"player_name");
         $PER_array[$per]['pid']=mysql_result($per_result,$per,"player_id");
         $PER_array[$per]['PER']=mysql_result($per_result,$per,"PER");
         $PER_array[$per]['MM']=mysql_result($per_result,$per,"Magic_Metric");
         $PER_array[$per]['GS']=mysql_result($per_result,$per,"Game_Score");

         $per++;
      }

}

$i=0;

while ($i < $num)
{
$name=mysql_result($result,$i,"name");
$pos=mysql_result($result,$i,"altpos");
$p_ord=mysql_result($result,$i,"ordinal");
$pid=mysql_result($result,$i,"pid");

$draftyear=mysql_result($result,$i,"draftyear");
$exp=mysql_result($result,$i,"exp");
$cy=mysql_result($result,$i,"cy");
$cyt=mysql_result($result,$i,"cyt");

$yearoffreeagency=$draftyear+$exp+$cyt-$cy;


$stats_PER="";
$stats_MM="";
$stats_GS="";

if ($yr == "")
{

$pc=0;
while ($pc < $per)
{
  if ($PER_array[$pc]['pid'] == $pid)
  {
    $stats_PER=number_format($PER_array[$pc]['PER'],3);
    $stats_Magic_Metric=number_format($PER_array[$pc]['MM'],3);
    $stats_Game_Score=number_format($PER_array[$pc]['GS'],3);
  }
  $pc++;
}

$stats_gm=mysql_result($result,$i,"stats_gm");
$stats_gs=mysql_result($result,$i,"stats_gs");
$stats_min=mysql_result($result,$i,"stats_min");
$stats_fgm=mysql_result($result,$i,"stats_fgm");
$stats_fga=mysql_result($result,$i,"stats_fga");
$stats_ftm=mysql_result($result,$i,"stats_ftm");
$stats_fta=mysql_result($result,$i,"stats_fta");
$stats_tgm=mysql_result($result,$i,"stats_3gm");
$stats_tga=mysql_result($result,$i,"stats_3ga");
$stats_orb=mysql_result($result,$i,"stats_orb");
$stats_drb=mysql_result($result,$i,"stats_drb");
$stats_ast=mysql_result($result,$i,"stats_ast");
$stats_stl=mysql_result($result,$i,"stats_stl");
$stats_to=mysql_result($result,$i,"stats_to");
$stats_blk=mysql_result($result,$i,"stats_blk");
$stats_pf=mysql_result($result,$i,"stats_pf");
$stats_reb=$stats_orb+$stats_drb;
$stats_pts=2*$stats_fgm+$stats_ftm+$stats_tgm;

} else {

$stats_gm=mysql_result($result,$i,"gm");
$stats_min=mysql_result($result,$i,"min");
$stats_fgm=mysql_result($result,$i,"fgm");
$stats_fga=mysql_result($result,$i,"fga");
$stats_ftm=mysql_result($result,$i,"ftm");
$stats_fta=mysql_result($result,$i,"fta");
$stats_tgm=mysql_result($result,$i,"3gm");
$stats_tga=mysql_result($result,$i,"3ga");
$stats_orb=mysql_result($result,$i,"orb");
$stats_ast=mysql_result($result,$i,"ast");
$stats_stl=mysql_result($result,$i,"stl");
$stats_to=mysql_result($result,$i,"tvr");
$stats_blk=mysql_result($result,$i,"blk");
$stats_pf=mysql_result($result,$i,"pf");
$stats_reb=mysql_result($result,$i,"reb");
$stats_pts=2*$stats_fgm+$stats_ftm+$stats_tgm;

}

@$stats_fgp=number_format(($stats_fgm/$stats_fga),3);
@$stats_ftp=number_format(($stats_ftm/$stats_fta),3);
@$stats_tgp=number_format(($stats_tgm/$stats_tga),3);
@$stats_mpg=number_format(($stats_min/$stats_gm),1);
@$stats_opg=number_format(($stats_orb/$stats_gm),1);
@$stats_rpg=number_format(($stats_reb/$stats_gm),1);
@$stats_apg=number_format(($stats_ast/$stats_gm),1);
@$stats_spg=number_format(($stats_stl/$stats_gm),1);
@$stats_tpg=number_format(($stats_to/$stats_gm),1);
@$stats_bpg=number_format(($stats_blk/$stats_gm),1);
@$stats_fpg=number_format(($stats_pf/$stats_gm),1);
@$stats_ppg=number_format(($stats_pts/$stats_gm),1);

	if(($i % 2)==0) {
		$bgcolor="FFFFFF";
	}else{
		$bgcolor="EEEEEE";
	}

if ($tid == 0)
{
$table_averages=$table_averages."
<tr bgcolor=$bgcolor><td>$pos</td><td colspan=3><a href=\"modules.php?name=Player&pa=showpage&pid=$pid\">$name</a></td>";
$table_averages=$table_averages."<td><center>$stats_gm</center></td><td>$stats_gs</td><td><center>";
$table_averages=$table_averages.$stats_mpg;
$table_averages=$table_averages."</center></td><td><center>";
$table_averages=$table_averages.$stats_fgp;
$table_averages=$table_averages."</center></td><td><center>";
$table_averages=$table_averages.$stats_ftp;
$table_averages=$table_averages."</center></td><td><center>";
$table_averages=$table_averages.$stats_tgp;
$table_averages=$table_averages."</center></td><td><center>";
$table_averages=$table_averages.$stats_opg;
$table_averages=$table_averages."</center></td><td><center>";
$table_averages=$table_averages.$stats_rpg;
$table_averages=$table_averages."</center></td><td><center>";
$table_averages=$table_averages.$stats_apg;
$table_averages=$table_averages."</center></td><td><center>";
$table_averages=$table_averages.$stats_spg;
$table_averages=$table_averages."</center></td><td><center>";
$table_averages=$table_averages.$stats_tpg;
$table_averages=$table_averages."</center></td><td><center>";
$table_averages=$table_averages.$stats_bpg;
$table_averages=$table_averages."</center></td><td><center>";
$table_averages=$table_averages.$stats_fpg;
$table_averages=$table_averages."</center></td><td><center>";
$table_averages=$table_averages.$stats_ppg;

} else {
if ($p_ord > 959)
{

$table_averages=$table_averages."
<tr bgcolor=$bgcolor><td>$pos</td><td colspan=3><a href=\"modules.php?name=Player&pa=showpage&pid=$pid\">($name)*</a></td>";
$table_averages=$table_averages."<td><center>$stats_gm</center></td><td>$stats_gs</td><td><center>";
$table_averages=$table_averages.$stats_mpg;
$table_averages=$table_averages."</center></td><td><center>";
$table_averages=$table_averages.$stats_fgp;
$table_averages=$table_averages."</center></td><td><center>";
$table_averages=$table_averages.$stats_ftp;
$table_averages=$table_averages."</center></td><td><center>";
$table_averages=$table_averages.$stats_tgp;
$table_averages=$table_averages."</center></td><td><center>";
$table_averages=$table_averages.$stats_opg;
$table_averages=$table_averages."</center></td><td><center>";
$table_averages=$table_averages.$stats_rpg;
$table_averages=$table_averages."</center></td><td><center>";
$table_averages=$table_averages.$stats_apg;
$table_averages=$table_averages."</center></td><td><center>";
$table_averages=$table_averages.$stats_spg;
$table_averages=$table_averages."</center></td><td><center>";
$table_averages=$table_averages.$stats_tpg;
$table_averages=$table_averages."</center></td><td><center>";
$table_averages=$table_averages.$stats_bpg;
$table_averages=$table_averages."</center></td><td><center>";
$table_averages=$table_averages.$stats_fpg;
$table_averages=$table_averages."</center></td><td><center>";
$table_averages=$table_averages.$stats_ppg;

} else if ($fayr == "" OR $yearoffreeagency == $fayr) {
$table_averages=$table_averages."
<tr bgcolor=$bgcolor><td>$pos</td><td colspan=3><a href=\"modules.php?name=Player&pa=showpage&pid=$pid\">$name</a></td>";
$table_averages=$table_averages."<td><center>$stats_gm</center></td><td>$stats_gs</td><td><center>";
$table_averages=$table_averages.$stats_mpg;
$table_averages=$table_averages."</center></td><td><center>";
$table_averages=$table_averages.$stats_fgp;
$table_averages=$table_averages."</center></td><td><center>";
$table_averages=$table_averages.$stats_ftp;
$table_averages=$table_averages."</center></td><td><center>";
$table_averages=$table_averages.$stats_tgp;
$table_averages=$table_averages."</center></td><td><center>";
$table_averages=$table_averages.$stats_opg;
$table_averages=$table_averages."</center></td><td><center>";
$table_averages=$table_averages.$stats_rpg;
$table_averages=$table_averages."</center></td><td><center>";
$table_averages=$table_averages.$stats_apg;
$table_averages=$table_averages."</center></td><td><center>";
$table_averages=$table_averages.$stats_spg;
$table_averages=$table_averages."</center></td><td><center>";
$table_averages=$table_averages.$stats_tpg;
$table_averages=$table_averages."</center></td><td><center>";
$table_averages=$table_averages.$stats_bpg;
$table_averages=$table_averages."</center></td><td><center>";
$table_averages=$table_averages.$stats_fpg;
$table_averages=$table_averages."</center></td><td><center>";
$table_averages=$table_averages.$stats_ppg;

}
}

$i++;

}

// ========= TEAM AVERAGES DISPLAY

$table_averages=$table_averages."</tbody>
<tfoot>";

$queryTeamOffenseTotals="SELECT * FROM ibl_team_offense_stats WHERE team = '$team_name' AND year = '0'";
$resultTeamOffenseTotals=mysql_query($queryTeamOffenseTotals);
$numTeamOffenseTotals=mysql_numrows($resultTeamOffenseTotals);

$t=0;

while ($t < $numTeamOffenseTotals)
{

$team_off_games=mysql_result($resultTeamOffenseTotals,$t,"games");
$team_off_minutes=mysql_result($resultTeamOffenseTotals,$t,"minutes");
$team_off_fgm=mysql_result($resultTeamOffenseTotals,$t,"fgm");
$team_off_fga=mysql_result($resultTeamOffenseTotals,$t,"fga");
@$team_off_fgp=number_format(($team_off_fgm/$team_off_fga),3);
$team_off_ftm=mysql_result($resultTeamOffenseTotals,$t,"ftm");
$team_off_fta=mysql_result($resultTeamOffenseTotals,$t,"fta");
@$team_off_ftp=number_format(($team_off_ftm/$team_off_fta),3);
$team_off_tgm=mysql_result($resultTeamOffenseTotals,$t,"tgm");
$team_off_tga=mysql_result($resultTeamOffenseTotals,$t,"tga");
@$team_off_tgp=number_format(($team_off_tgm/$team_off_tga),3);
$team_off_orb=mysql_result($resultTeamOffenseTotals,$t,"orb");
$team_off_reb=mysql_result($resultTeamOffenseTotals,$t,"reb");
$team_off_ast=mysql_result($resultTeamOffenseTotals,$t,"ast");
$team_off_stl=mysql_result($resultTeamOffenseTotals,$t,"stl");
$team_off_tvr=mysql_result($resultTeamOffenseTotals,$t,"tvr");
$team_off_blk=mysql_result($resultTeamOffenseTotals,$t,"blk");
$team_off_pf=mysql_result($resultTeamOffenseTotals,$t,"pf");
$team_off_pts=$team_off_fgm+$team_off_fgm+$team_off_ftm+$team_off_tgm;

@$team_off_avgmin=number_format(($team_off_minutes/$team_off_games),1);
@$team_off_avgorb=number_format(($team_off_orb/$team_off_games),1);
@$team_off_avgreb=number_format(($team_off_reb/$team_off_games),1);
@$team_off_avgast=number_format(($team_off_ast/$team_off_games),1);
@$team_off_avgstl=number_format(($team_off_stl/$team_off_games),1);
@$team_off_avgtvr=number_format(($team_off_tvr/$team_off_games),1);
@$team_off_avgblk=number_format(($team_off_blk/$team_off_games),1);
@$team_off_avgpf=number_format(($team_off_pf/$team_off_games),1);
@$team_off_avgpts=number_format(($team_off_pts/$team_off_games),1);

if ($yr == "")
{

$table_averages=$table_averages."<tr><td colspan=4><b>$team_name Offense</td>
<td><b><center>$team_off_games</center></td><td><b>$team_off_games</td><td><center><b>";
$table_averages=$table_averages.$team_off_avgmin;
$table_averages=$table_averages."</center></td><td><center><b>";
$table_averages=$table_averages.$team_off_fgp;
$table_averages=$table_averages."</center></td><td><center><b>";
$table_averages=$table_averages.$team_off_ftp;
$table_averages=$table_averages."</center></td><td><center><b>";
$table_averages=$table_averages.$team_off_tgp;
$table_averages=$table_averages."</center></td><td><center><b>";
$table_averages=$table_averages.$team_off_avgorb;
$table_averages=$table_averages."</center></td><td><center><b>";
$table_averages=$table_averages.$team_off_avgreb;
$table_averages=$table_averages."</center></td><td><center><b>";
$table_averages=$table_averages.$team_off_avgast;
$table_averages=$table_averages."</center></td><td><center><b>";
$table_averages=$table_averages.$team_off_avgstl;
$table_averages=$table_averages."</center></td><td><center><b>";
$table_averages=$table_averages.$team_off_avgtvr;
$table_averages=$table_averages."</center></td><td><center><b>";
$table_averages=$table_averages.$team_off_avgblk;
$table_averages=$table_averages."</center></td><td><center><b>";
$table_averages=$table_averages.$team_off_avgpf;
$table_averages=$table_averages."</center></td><td><center><b>";
$table_averages=$table_averages.$team_off_avgpts;
$table_averages=$table_averages."</center></td></tr>";

}

$t++;

}


$queryTeamDefenseTotals="SELECT * FROM ibl_team_defense_stats WHERE team = '$team_name' AND year = '0'";
$resultTeamDefenseTotals=mysql_query($queryTeamDefenseTotals);
$numTeamDefenseTotals=mysql_numrows($resultTeamDefenseTotals);

$t=0;

while ($t < $numTeamDefenseTotals)
{

$team_def_games=mysql_result($resultTeamDefenseTotals,$t,"games");
$team_def_minutes=mysql_result($resultTeamDefenseTotals,$t,"minutes");
$team_def_fgm=mysql_result($resultTeamDefenseTotals,$t,"fgm");
$team_def_fga=mysql_result($resultTeamDefenseTotals,$t,"fga");
@$team_def_fgp=number_format(($team_def_fgm/$team_def_fga),3);
$team_def_ftm=mysql_result($resultTeamDefenseTotals,$t,"ftm");
$team_def_fta=mysql_result($resultTeamDefenseTotals,$t,"fta");
@$team_def_ftp=number_format(($team_def_ftm/$team_def_fta),3);
$team_def_tgm=mysql_result($resultTeamDefenseTotals,$t,"tgm");
$team_def_tga=mysql_result($resultTeamDefenseTotals,$t,"tga");
@$team_def_tgp=number_format(($team_def_tgm/$team_def_tga),3);
$team_def_orb=mysql_result($resultTeamDefenseTotals,$t,"orb");
$team_def_reb=mysql_result($resultTeamDefenseTotals,$t,"reb");
$team_def_ast=mysql_result($resultTeamDefenseTotals,$t,"ast");
$team_def_stl=mysql_result($resultTeamDefenseTotals,$t,"stl");
$team_def_tvr=mysql_result($resultTeamDefenseTotals,$t,"tvr");
$team_def_blk=mysql_result($resultTeamDefenseTotals,$t,"blk");
$team_def_pf=mysql_result($resultTeamDefenseTotals,$t,"pf");
$team_def_pts=$team_def_fgm+$team_def_fgm+$team_def_ftm+$team_def_tgm;

@$team_def_avgmin=number_format(($team_def_minutes/$team_def_games),1);
@$team_def_avgorb=number_format(($team_def_orb/$team_def_games),1);
@$team_def_avgreb=number_format(($team_def_reb/$team_def_games),1);
@$team_def_avgast=number_format(($team_def_ast/$team_def_games),1);
@$team_def_avgstl=number_format(($team_def_stl/$team_def_games),1);
@$team_def_avgtvr=number_format(($team_def_tvr/$team_def_games),1);
@$team_def_avgblk=number_format(($team_def_blk/$team_def_games),1);
@$team_def_avgpf=number_format(($team_def_pf/$team_def_games),1);
@$team_def_avgpts=number_format(($team_def_pts/$team_def_games),1);

if ($yr == "")
{

$table_averages=$table_averages."<tr><td colspan=4><b>$team_name Defense</td>
<td><center><b>$team_def_games</center></td><td><b>$team_def_games</td><td><center><b>";
$table_averages=$table_averages.$team_def_avgmin;
$table_averages=$table_averages."</center></td><td><center><b>";
$table_averages=$table_averages.$team_def_fgp;
$table_averages=$table_averages."</center></td><td><center><b>";
$table_averages=$table_averages.$team_def_ftp;
$table_averages=$table_averages."</center></td><td><center><b>";
$table_averages=$table_averages.$team_def_tgp;
$table_averages=$table_averages."</center></td><td><center><b>";
$table_averages=$table_averages.$team_def_avgorb;
$table_averages=$table_averages."</center></td><td><center><b>";
$table_averages=$table_averages.$team_def_avgreb;
$table_averages=$table_averages."</center></td><td><center><b>";
$table_averages=$table_averages.$team_def_avgast;
$table_averages=$table_averages."</center></td><td><center><b>";
$table_averages=$table_averages.$team_def_avgstl;
$table_averages=$table_averages."</center></td><td><center><b>";
$table_averages=$table_averages.$team_def_avgtvr;
$table_averages=$table_averages."</center></td><td><center><b>";
$table_averages=$table_averages.$team_def_avgblk;
$table_averages=$table_averages."</center></td><td><center><b>";
$table_averages=$table_averages.$team_def_avgpf;
$table_averages=$table_averages."</center></td><td><center><b>";
$table_averages=$table_averages.$team_def_avgpts;
$table_averages=$table_averages."</center></td></tr>";

}

$t++;

}

$thischunk=$row[maxchunk];

$table_averages=$table_averages."</tfoot></table>
";

if ($yr == "")
{

$table_chunk=$table_chunk."<table  align=\"center\" class=\"sortable\">
     <thead>
";
/* ======================CHUNK STATS */

$currentSeasonEndingYear = getCurrentSeasonEndingYear();

$max_chunk_query="SELECT MAX(chunk) as maxchunk FROM nuke_iblplyr_chunk WHERE active = 1 AND Season = '$currentSeasonEndingYear' ";
$max_chunk_result=mysql_query($max_chunk_query);
$row = mysql_fetch_assoc($max_chunk_result);

$table_chunk=$table_chunk."
<tr bgcolor=$color1><th><font color=$color2>Pos</font></th><td colspan=3><font color=$color2>Player</font></th><th><font color=$color2>g</font></th><th><font color=$color2>gs</font></th><th><font color=$color2>min</font></th><th><font color=$color2>fgp</font></th><th><font color=$color2>ftp</font></th><th><font color=$color2>3gp</font></th><th><font color=$color2>orb</font></th><th><font color=$color2>reb</font></th><th><font color=$color2>ast</font></th><th><font color=$color2>stl</font></th><th><font color=$color2>to</font></th><th><font color=$color2>blk</font></th><th><font color=$color2>pf</font></th><th><font color=$color2>pts</font></th></tr>
</thead>
<tbody>";



$query_chunk="SELECT * FROM nuke_iblplyr_chunk WHERE chunk = $row[maxchunk] AND tid = $tid AND Season = '$currentSeasonEndingYear' ORDER BY ordinal ASC";
$result_chunk=mysql_query($query_chunk);
$num_chunk=mysql_numrows($result_chunk);

$i=0;

while ($i < $num_chunk)
	{
	$name=mysql_result($result_chunk,$i,"name");
	$pos=mysql_result($result_chunk,$i,"altpos");
	$pid=mysql_result($result_chunk,$i,"pid");

	$stats_gm=mysql_result($result_chunk,$i,"stats_gm");
	$stats_gs=mysql_result($result_chunk,$i,"stats_gs");
	$stats_min=mysql_result($result_chunk,$i,"stats_min");
	$stats_fgm=mysql_result($result_chunk,$i,"stats_fgm");
	$stats_fga=mysql_result($result_chunk,$i,"stats_fga");
	@$stats_fgp=number_format(($stats_fgm/$stats_fga),3);
	$stats_ftm=mysql_result($result_chunk,$i,"stats_ftm");
	$stats_fta=mysql_result($result_chunk,$i,"stats_fta");
	@$stats_ftp=number_format(($stats_ftm/$stats_fta),3);
	$stats_tgm=mysql_result($result_chunk,$i,"stats_3gm");
	$stats_tga=mysql_result($result_chunk,$i,"stats_3ga");
	@$stats_tgp=number_format(($stats_tgm/$stats_tga),3);
	$stats_orb=mysql_result($result_chunk,$i,"stats_orb");
	$stats_drb=mysql_result($result_chunk,$i,"stats_drb");
	$stats_ast=mysql_result($result_chunk,$i,"stats_ast");
	$stats_stl=mysql_result($result_chunk,$i,"stats_stl");
	$stats_to=mysql_result($result_chunk,$i,"stats_to");
	$stats_blk=mysql_result($result_chunk,$i,"stats_blk");
	$stats_pf=mysql_result($result_chunk,$i,"stats_pf");
	$stats_reb=$stats_orb+$stats_drb;
	$stats_pts=2*$stats_fgm+$stats_ftm+$stats_tgm;

	@$stats_mpg=number_format(($stats_min/$stats_gm),1);
	@$stats_opg=number_format(($stats_orb/$stats_gm),1);
	@$stats_rpg=number_format(($stats_reb/$stats_gm),1);
	@$stats_apg=number_format(($stats_ast/$stats_gm),1);
	@$stats_spg=number_format(($stats_stl/$stats_gm),1);
	@$stats_tpg=number_format(($stats_to/$stats_gm),1);
	@$stats_bpg=number_format(($stats_blk/$stats_gm),1);
	@$stats_fpg=number_format(($stats_pf/$stats_gm),1);
	@$stats_ppg=number_format(($stats_pts/$stats_gm),1);

	if(($i % 2)==0) {
		$bgcolor="FFFFFF";
	}else{
		$bgcolor="EEEEEE";
	}


$table_chunk=$table_chunk."<tr bgcolor=$bgcolor><td>$pos</td><td colspan=3><a href=\"modules.php?name=Player&pa=showpage&pid=$pid\">$name</a></td>";
$table_chunk=$table_chunk."<td><center>$stats_gm</center></td><td>$stats_gs</td><td><center>";
$table_chunk=$table_chunk.$stats_mpg;
$table_chunk=$table_chunk."</center></td><td><center>";
$table_chunk=$table_chunk.$stats_fgp;
$table_chunk=$table_chunk."</center></td><td><center>";
$table_chunk=$table_chunk.$stats_ftp;
$table_chunk=$table_chunk."</center></td><td><center>";
$table_chunk=$table_chunk.$stats_tgp;
$table_chunk=$table_chunk."</center></td><td><center>";
$table_chunk=$table_chunk.$stats_opg;
$table_chunk=$table_chunk."</center></td><td><center>";
$table_chunk=$table_chunk.$stats_rpg;
$table_chunk=$table_chunk."</center></td><td><center>";
$table_chunk=$table_chunk.$stats_apg;
$table_chunk=$table_chunk."</center></td><td><center>";
$table_chunk=$table_chunk.$stats_spg;
$table_chunk=$table_chunk."</center></td><td><center>";
$table_chunk=$table_chunk.$stats_tpg;
$table_chunk=$table_chunk."</center></td><td><center>";
$table_chunk=$table_chunk.$stats_bpg;
$table_chunk=$table_chunk."</center></td><td><center>";
$table_chunk=$table_chunk.$stats_fpg;
$table_chunk=$table_chunk."</center></td><td><center>";
$table_chunk=$table_chunk.$stats_ppg;
$table_chunk=$table_chunk."</center></td></tr>";

	$i++;

	}


$table_chunk=$table_chunk."
  </tbody>
  </table>";

}// END OF IF $yr == "" BRACE TO REMOVE PER CHUNK STUFF

}  // END OF TID != 0 brace - inserted so that Free Agents won't clog up the page with season averages and totals when those are almost always zeros.

if ($yr == "")
{

  $table_contracts=$table_contracts."<table  align=\"center\" class=\"sortable\">
   <thead>
<tr bgcolor=$color1><th><font color=$color2>Pos</font></th><td colspan=3><font color=$color2>Player</font></th><th><font color=$color2>Bird</font></th><th><font color=$color2>Year1</font></th><th><font color=$color2>Year2</font></th><th><font color=$color2>Year3</font></th><th><font color=$color2>Year4</font></th><th><font color=$color2>Year5</font></th><th><font color=$color2>Year6</font></th><td bgcolor=#000000 width=3></th><th><font color=$color2>Talent</font></th><th><font color=$color2>Skill</font></th><th><font color=$color2>Intang</font></th><th><font color=$color2>Clutch</font></th><th><font color=$color2>Consistency</font></th></tr>
</thead>
<tbody>
";

/* =======================CONTRACTS ET AL */

$i=0;
while ($i < $num)
{

$name=mysql_result($result,$i,"name");
$pos=mysql_result($result,$i,"altpos");
$p_ord=mysql_result($result,$i,"ordinal");
$pid=mysql_result($result,$i,"pid");

$cy=mysql_result($result,$i,"cy");
$cyt=mysql_result($result,$i,"cyt");

if ($faon == 0) {
$year1=$cy;
$year2=$cy+1;
$year3=$cy+2;
$year4=$cy+3;
$year5=$cy+4;
$year6=$cy+5;
} else {
$year1=$cy+1;
$year2=$cy+2;
$year3=$cy+3;
$year4=$cy+4;
$year5=$cy+5;
$year6=$cy+6;
}

if ($cy==0)
{

if ($year1 < 7)
{
$con1=mysql_result($result,$i,"cy1");
} else {
$con1=0;
}

if ($year2 < 7)
{
$con2=mysql_result($result,$i,"cy2");
} else {
$con2=0;
}

if ($year3 < 7)
{
$con3=mysql_result($result,$i,"cy3");
} else {
$con3=0;
}

if ($year4 < 7)
{
$con4=mysql_result($result,$i,"cy4");
} else {
$con4=0;
}

if ($year5 < 7)
{
$con5=mysql_result($result,$i,"cy5");
} else {
$con5=0;
}

if ($year6 < 7)
{
$con6=mysql_result($result,$i,"cy6");
} else {
$con6=0;
}

} else {
if ($year1 < 7)
{
$con1=mysql_result($result,$i,"cy$year1");
} else {
$con1=0;
}

if ($year2 < 7)
{
$con2=mysql_result($result,$i,"cy$year2");
} else {
$con2=0;
}

if ($year3 < 7)
{
$con3=mysql_result($result,$i,"cy$year3");
} else {
$con3=0;
}

if ($year4 < 7)
{
$con4=mysql_result($result,$i,"cy$year4");
} else {
$con4=0;
}

if ($year5 < 7)
{
$con5=mysql_result($result,$i,"cy$year5");
} else {
$con5=0;
}

if ($year6 < 7)
{
$con6=mysql_result($result,$i,"cy$year6");
} else {
$con6=0;
}
}

$bird=mysql_result($result,$i,"bird");
$talent=mysql_result($result,$i,"talent");
$skill=mysql_result($result,$i,"skill");
$intangibles=mysql_result($result,$i,"intangibles");
$Clutch=mysql_result($result,$i,"Clutch");
$Consistency=mysql_result($result,$i,"Consistency");

	if(($i % 2)==0) {
		$bgcolor="FFFFFF";
	}else{
		$bgcolor="EEEEEE";
	}


if ($tid == 0)
{
$table_contracts=$table_contracts."
<tr bgcolor=$bgcolor><td>$pos</td><td colspan=3><a href=\"modules.php?name=Player&pa=showpage&pid=$pid\">$name</a></td><td>$bird</td><td>$con1</td><td>$con2</td><td>$con3</td><td>$con4</td><td>$con5</td><td>$con6</td><td bgcolor=#000000></td><td>$talent</td><td>$skill</td><td>$intangibles</td><td>$Clutch</td><td>$Consistency</td></tr>
";
} else {
if ($p_ord > 959)
{
$table_contracts=$table_contracts."
<tr bgcolor=$bgcolor><td>$pos</td><td colspan=3><a href=\"modules.php?name=Player&pa=showpage&pid=$pid\">($name)*</a></td><td>$bird</td><td>$con1</td><td>$con2</td><td>$con3</td><td>$con4</td><td>$con5</td><td>$con6</td><td bgcolor=#000000></td><td>$talent</td><td>$skill</td><td>$intangibles</td><td>$Clutch</td><td>$Consistency</td></tr>
";
} else {
$table_contracts=$table_contracts."
<tr bgcolor=$bgcolor><td>$pos</td><td colspan=3><a href=\"modules.php?name=Player&pa=showpage&pid=$pid\">$name</a></td><td>$bird</td><td>$con1</td><td>$con2</td><td>$con3</td><td>$con4</td><td>$con5</td><td>$con6</td><td bgcolor=#000000></td><td>$talent</td><td>$skill</td><td>$intangibles</td><td>$Clutch</td><td>$Consistency</td></tr>
";
}
}

$cap1=$cap1+$con1;
$cap2=$cap2+$con2;
$cap3=$cap3+$con3;
$cap4=$cap4+$con4;
$cap5=$cap5+$con5;
$cap6=$cap6+$con6;

$i++;

}

$cap1=number_format($cap1/100,2);
$cap2=number_format($cap2/100,2);
$cap3=number_format($cap3/100,2);
$cap4=number_format($cap4/100,2);
$cap5=number_format($cap5/100,2);
$cap6=number_format($cap6/100,2);

$table_contracts=$table_contracts."</tbody>
<tfoot>

      <tr><td></td><td colspan=3><b>Cap Totals</td><td></td><td><b>";
$table_contracts=$table_contracts.$cap1;
$table_contracts=$table_contracts."</td><td><b>";
$table_contracts=$table_contracts.$cap2;
$table_contracts=$table_contracts."</td><td><b>";
$table_contracts=$table_contracts.$cap3;
$table_contracts=$table_contracts."</td><td><b>";
$table_contracts=$table_contracts.$cap4;
$table_contracts=$table_contracts."</td><td><b>";
$table_contracts=$table_contracts.$cap5;
$table_contracts=$table_contracts."</td><td><b>";
$table_contracts=$table_contracts.$cap6;
$table_contracts=$table_contracts."</td><td bgcolor=#000000></td><td></td><td></td><td></td><td></td><td></td></tr>
<tr><td colspan=17><i>Note:</i> Players whose names appear in parenthesis and with a trailing asterisk are waived players that still count against the salary cap.</td></tr>
</tfoot>
</table>";

} else {
} // END OF IF YEAR EQUAL NULL STATEMENT FROM BEFORE CHUNK AVERAGES

$table_draftpicks=$table_draftpicks."<table align=\"center\">
";

// PUT DRAFT PICKS BELOW SALARY PRINTOUT

$querypicks="SELECT * FROM ibl_draft_picks WHERE ownerofpick = '$team_name' ORDER BY year, round ASC";
$resultpicks=mysql_query($querypicks);
$numpicks=mysql_num_rows($resultpicks);

$hh=0;

$query_all_team_colors="SELECT * FROM nuke_ibl_team_info ORDER BY teamid ASC";
$colors=mysql_query($query_all_team_colors);
$num_all_team_colors=mysql_num_rows($colors);

$i=0;
while ($i < $num_all_team_colors)
{
    $color_array[$i]['team_id']=mysql_result($colors,$i,"teamid");
    $color_array[$i]['team_city']=mysql_result($colors,$i,"team_city");
    $color_array[$i]['team_name']=mysql_result($colors,$i,"team_name");
    $color_array[$i]['color1']=mysql_result($colors,$i,"color1");
    $color_array[$i]['color2']=mysql_result($colors,$i,"color2");
    $i++;
}

while ($hh < $numpicks)
{

$ownerofpick=mysql_result($resultpicks,$hh,"ownerofpick");
$teampick=mysql_result($resultpicks,$hh,"teampick");
$year=mysql_result($resultpicks,$hh,"year");
$round=mysql_result($resultpicks,$hh,"round");

  $j=0;
  while ($j < $i)
  {
    $pick_team_name=$color_array[$j]['team_name'];
    if ($pick_team_name == $teampick)
    {
      $pick_team_id=$color_array[$j]['team_id'];
      $pick_team_city=$color_array[$j]['team_city'];
      $pick_team_color1=$color_array[$j]['color1'];
      $pick_team_color2=$color_array[$j]['color2'];
    }
    $j++;
  }

$table_draftpicks=$table_draftpicks."<tr><td valign=\"center\"><a href=\"modules.php?name=Team&op=team&tid=$pick_team_id\"><img src=\"images/logo/$teampick.png\"></a></td><td valign=\"center\"><a href=\"modules.php?name=Team&op=team&tid=$pick_team_id\">$year $pick_team_city $teampick (Round $round)</a></td></tr>";

$hh++;
}

$table_draftpicks=$table_draftpicks."</table>";


$inforight = team_info_right ($team_name, $color1, $color2, $owner_name, $tid);

$team_info_right=$inforight[0];
$rafters=$inforight[1];

if ($yr != "")
{
  $insertyear="&yr=$yr";
} else {
  $insertyear="";
}


if ($display == "ratings")
{
  $showing="Player Ratings";
  $tabs=$tabs."<td bgcolor=#BBBBBB><a href=\"modules.php?name=Team&op=team&tid=$tid&display=ratings$insertyear\">Ratings</a></td>";
  $table_output=$table_ratings;
} else {
  $tabs=$tabs."<td><a href=\"modules.php?name=Team&op=team&tid=$tid&display=ratings$insertyear\">Ratings</a> | </td>";
}
if ($display == "total_s") {
  $showing="Season Totals";
  $tabs=$tabs."<td bgcolor=#BBBBBB><a href=\"modules.php?name=Team&op=team&tid=$tid&display=total_s$insertyear\">Season Totals</a></td>";
  $table_output=$table_totals;
} else {
  $tabs=$tabs."<td><a href=\"modules.php?name=Team&op=team&tid=$tid&display=total_s$insertyear\">Season Totals</a> | </td>";
}
if ($display == "avg_s") {
  $showing="Season Averages";
  $tabs=$tabs."<td bgcolor=#BBBBBB><a href=\"modules.php?name=Team&op=team&tid=$tid&display=avg_s$insertyear\">Season Averages</a></td>";
  $table_output=$table_averages;
} else {
  $tabs=$tabs."<td ><a href=\"modules.php?name=Team&op=team&tid=$tid&display=avg_s$insertyear\">Season Averages</a> | </td>";
}
if ($display == "chunk") {
  $showing="Sim Averages";
  $tabs=$tabs." <td bgcolor=#BBBBBB><a href=\"modules.php?name=Team&op=team&tid=$tid&display=chunk$insertyear\">Sim Averages</a></td>";
  $table_output=$table_chunk;
} else {
  $tabs=$tabs."<td><a href=\"modules.php?name=Team&op=team&tid=$tid&display=chunk$insertyear\">Sim Averages</a> | </td>";
}

if ($display == "contracts") {
  $showing="Contracts";
  $tabs=$tabs."<td bgcolor=#BBBBBB><a href=\"modules.php?name=Team&op=team&tid=$tid&display=contracts$insertyear\">Contracts</a></td>";
  $table_output=$table_contracts;
} else {
  $tabs=$tabs."<td><a href=\"modules.php?name=Team&op=team&tid=$tid&display=contracts$insertyear\">Contracts</a></td>";
}

echo "
       <table align=center><tr bgcolor=$color1><td><font color=$color2><b><center>$showing (Sortable by clicking on Column Heading)</center></b></font></td></tr>
       <tr><td align=center><table><tr>$tabs</tr></table></td></tr>
       <tr><td align=center>$table_output</td></tr>
       <tr><td align=center>$starters_table</td></tr>
       <tr bgcolor=$color1><td><font color=$color2><b><center>Draft Picks</center></b></font></td></tr>
       <tr><td>$table_draftpicks</td></tr>
       <tr><td>$rafters</td></tr></table>";



// TRANSITIONS TO NEXT SIDE OF PAGE

echo "</td><td valign=top> $team_info_right
</td></tr></table>
";

// CLOSES UP THE TABLE

    CloseTable();
    include("footer.php");

}


/************************************************************************/
/* BEGIN FUNCTION TO DISPLAY TEAM INFO BAR ON THE */
/* RIGHT HAND SIDE OF THE TEAM PAGE                         */
/************************************************************************/

function team_info_right ($team_name, $color1, $color2, $owner_name, $tid)
{

// ==== GET OWNER INFO

$queryo="SELECT * FROM nuke_users WHERE user_ibl_team = '$team_name' ORDER BY user_id DESC";
$resulto=mysql_query($queryo);
$numo=mysql_num_rows($resulto);

$user_id=mysql_result($resulto,0,"user_id");
$username=mysql_result($resulto,0,"username");
$user_lastvisit=mysql_result($resulto,0,"user_lastvisit");
$date_started=mysql_result($resulto,0,"date_started");
$visitdate=date(r,$user_lastvisit);

$output="
<table bgcolor=#eeeeee width=200>";

$output=$output."<tr bgcolor=\"#$color1\"><td align=\"center\">
<font color=\"#$color2\"><b>Owner Information</b></font>
</td></tr>
<tr><td>
<b>Owner Nickname:</b> <a href=\"modules.php?name=Your_Account&op=userinfo&username=$username\">$username</a><br>
<a href=\"modules.php?name=Private_Messages&mode=post&u=$user_id\">Send Private Message</a><br>
<b>Year Joined</b>: $date_started<br>
<b>Last Site Visit:</b> $visitdate<br>
</td></tr>";

//==================
// OWNER DECORATIONS
//==================

$owner_award_code = $owner_name." (".$team_name.")";

$querydec="SELECT * FROM nuke_ibl_awards WHERE name LIKE '$owner_award_code' ORDER BY year ASC";
$resultdec=mysql_query($querydec);
$numdec=mysql_num_rows($resultdec);

if ($numdec > 0)

$dec=0;
$output=$output."<tr bgcolor=\"#$color1\"><td align=\"center\">
<font color=\"#$color2\"><b>Owner Awards</b></font>
</td></tr>
<tr><td>";
while ($dec < $numdec)
{
$dec_year=mysql_result($resultdec,$dec,"year");
$dec_Award=mysql_result($resultdec,$dec,"Award");

$output=$output."<table border=0 cellpadding=0 cellspacing=0><tr><td><img height=25 src=\"../images/banners/gmoy_trophy.gif\"></td><td>$dec_year $dec_Award</td></tr></table>";

$dec++;
}
$output=$output."</td></tr>";

// REGULAR SEASON RESULTS

    $querywl="SELECT * FROM nuke_iblteam_win_loss WHERE currentname = '$team_name' ORDER BY year DESC";
    $resultwl=mysql_query($querywl);
    $numwl=mysql_num_rows($resultwl);

$h=0;
$wintot=0;
$lostot=0;

$output=$output."<tr bgcolor=\"#$color1\"><td align=center><font color=\"#$color2\"><b>Regular Season History</b></font></td></tr>
<tr><td>
<div id=\"History-R\" style=\"overflow:auto; height:150px\">";

while ($h < $numwl)
{
$yearwl=mysql_result($resultwl,$h,"year");
$namewl=mysql_result($resultwl,$h,"namethatyear");
$wins=mysql_result($resultwl,$h,"wins");
$losses=mysql_result($resultwl,$h,"losses");
$wintot=$wintot+$wins;
$lostot=$lostot+$losses;
@$winpct=number_format($wins/($wins+$losses),3);
$output=$output."<a href=\"modules.php?name=Team&op=team&tid=$tid&yr=$yearwl\">$yearwl $namewl</a>: $wins-$losses ($winpct)<br>
";


$h++;
}
@$wlpct=number_format($wintot/($wintot+$lostot),3);

$output=$output."</div></td></tr>
<tr bgcolor=\"#$color1\"><td align=center><font color=\"#$color2\"><b>All-Time Franchise Record</b></font></td></tr>
<tr><td>$wintot - $lostot ($wlpct Percentage)</td></tr>
";

// POST-SEASON RESULTS

$queryplayoffs="SELECT * FROM ibl_playoff_results ORDER BY year DESC";
$resultplayoffs=mysql_query($queryplayoffs);
$numplayoffs=mysql_num_rows($resultplayoffs);

$pp=0;
$totalplayoffwins=0;
$totalplayofflosses=0;
$first_round_victories=0;
$second_round_victories=0;
$third_round_victories=0;
$fourth_round_victories=0;
$first_round_losses=0;
$second_round_losses=0;
$third_round_losses=0;
$fourth_round_losses=0;

$round_one_output="";
$round_two_output="";
$round_three_output="";
$round_four_output="";

$first_wins=0;
$second_wins=0;
$third_wins=0;
$fourth_wins=0;


  while ($pp < $numplayoffs)
  {

  $playoffround=mysql_result($resultplayoffs,$pp,"round");
  $playoffyear=mysql_result($resultplayoffs,$pp,"year");
  $playoffwinner=mysql_result($resultplayoffs,$pp,"winner");
  $playoffloser=mysql_result($resultplayoffs,$pp,"loser");
  $playoffloser_games=mysql_result($resultplayoffs,$pp,"loser_games");

  if ($playoffround == 1)
  {
    if ($playoffwinner == $team_name)
    {
      $totalplayoffwins=$totalplayoffwins+4;
      $totalplayofflosses=$totalplayofflosses+$playoffloser_games;
      $first_wins=$first_wins+4;
      $first_losses=$first_losses+$playoffloser_games;
      $first_round_victories++;
      $round_one_output=$round_one_output."$playoffyear - $team_name 4, $playoffloser $playoffloser_games<br>";
    } else if ($playoffloser == $team_name) {
      $totalplayofflosses=$totalplayofflosses+4;
      $totalplayoffwins=$totalplayoffwins+$playoffloser_games;
      $first_losses=$first_losses+4;
      $first_wins=$first_wins+$playoffloser_games;
      $first_round_losses++;
      $round_one_output=$round_one_output."$playoffyear - $playoffwinner 4, $team_name $playoffloser_games<br>";
    }
  } else if ($playoffround == 2) {
    if ($playoffwinner == $team_name)
    {
      $totalplayoffwins=$totalplayoffwins+4;
      $totalplayofflosses=$totalplayofflosses+$playoffloser_games;
      $second_wins=$second_wins+4;
      $second_losses=$second_losses+$playoffloser_games;
      $second_round_victories++;
      $round_two_output=$round_two_output."$playoffyear - $team_name 4, $playoffloser $playoffloser_games<br>";
    } else if ($playoffloser == $team_name) {
      $totalplayofflosses=$totalplayofflosses+4;
      $totalplayoffwins=$totalplayoffwins+$playoffloser_games;
      $second_losses=$second_losses+4;
      $second_wins=$second_wins+$playoffloser_games;
      $second_round_losses++;
      $round_two_output=$round_two_output."$playoffyear - $playoffwinner 4, $team_name $playoffloser_games<br>";
    }
  } else if ($playoffround == 3) {
    if ($playoffwinner == $team_name)
    {
      $totalplayoffwins=$totalplayoffwins+4;
      $totalplayofflosses=$totalplayofflosses+$playoffloser_games;
      $third_wins=$third_wins+4;
      $third_losses=$third_losses+$playoffloser_games;
      $third_round_victories++;
      $round_three_output=$round_three_output."$playoffyear - $team_name 4, $playoffloser $playoffloser_games<br>";
    } else if ($playoffloser == $team_name) {
      $totalplayofflosses=$totalplayofflosses+4;
      $totalplayoffwins=$totalplayoffwins+$playoffloser_games;
      $third_losses=$third_losses+4;
      $third_wins=$third_wins+$playoffloser_games;
      $third_round_losses++;
      $round_three_output=$round_three_output."$playoffyear - $playoffwinner 4, $team_name $playoffloser_games<br>";
    }
  } else if ($playoffround == 4) {
    if ($playoffwinner == $team_name)
    {
      $totalplayoffwins=$totalplayoffwins+4;
      $totalplayofflosses=$totalplayofflosses+$playoffloser_games;
      $fourth_wins=$fourth_wins+4;
      $fourth_losses=$fourth_losses+$playoffloser_games;
      $fourth_round_victories++;
      $round_four_output=$round_four_output."$playoffyear - $team_name 4, $playoffloser $playoffloser_games<br>";
    } else if ($playoffloser == $team_name) {
      $totalplayofflosses=$totalplayofflosses+4;
      $totalplayoffwins=$totalplayoffwins+$playoffloser_games;
      $fourth_losses=$fourth_losses+4;
      $fourth_wins=$fourth_wins+$playoffloser_games;
      $fourth_round_losses++;
      $round_four_output=$round_four_output."$playoffyear - $playoffwinner 4, $team_name $playoffloser_games<br>";
    }
  }
  $pp++;
  }

@$pwlpct=number_format($totalplayoffwins/($totalplayoffwins+$totalplayofflosses),3);
@$r1wlpct=number_format($first_round_victories/($first_round_victories+$first_round_losses),3);
@$r2wlpct=number_format($second_round_victories/($second_round_victories+$second_round_losses),3);
@$r3wlpct=number_format($third_round_victories/($third_round_victories+$third_round_losses),3);
@$r4wlpct=number_format($fourth_round_victories/($fourth_round_victories+$fourth_round_losses),3);
$round_victories=$first_round_victories+$second_round_victories+$third_round_victories+$fourth_round_victories;
$round_losses=$first_round_losses+$second_round_losses+$third_round_losses+$fourth_round_losses;
@$swlpct=number_format($round_victories/($round_victories+$round_losses),3);
@$firstpct=number_format($first_wins/($first_wins+$first_losses),3);
@$secondpct=number_format($second_wins/($second_wins+$second_losses),3);
@$thirdpct=number_format($third_wins/($third_wins+$third_losses),3);
@$fourthpct=number_format($fourth_wins/($fourth_wins+$first_losses),3);

if ($round_one_output != "")
{
  $output=$output."<tr bgcolor=\"#$color1\"><td align=center><font color=\"#$color2\"><b>First-Round Playoff Results</b></font></td></tr>
<tr><td>
<div id=\"History-P1\" style=\"overflow:auto; height:150px\">".$round_one_output."</div></td></tr>
<tr><td><b>Totals:</b> $first_wins - $first_losses ($firstpct)<br>
<b>Series:</b> $first_round_victories - $first_round_losses ($r1wlpct)</td></tr>";
}
if ($round_two_output != "")
{
  $output=$output."<tr bgcolor=\"#$color1\"><td align=center><font color=\"#$color2\"><b>Conference Semis Playoff Results</b></font></td></tr>
<tr><td>
<div id=\"History-P2\" style=\"overflow:auto; height:150px\">".$round_two_output."</div></td></tr>
<tr><td><b>Totals:</b> $second_wins - $second_losses ($secondpct)<br>
<b>Series:</b> $second_round_victories - $second_round_losses ($r2wlpct)</td></tr>";
}
if ($round_three_output != "")
{
  $output=$output."<tr bgcolor=\"#$color1\"><td align=center><font color=\"#$color2\"><b>Conference Finals Playoff Results</b></font></td></tr>
<tr><td>
<div id=\"History-P3\" style=\"overflow:auto; height:150px\">".$round_three_output."</div></td></tr>
<tr><td><b>Totals:</b> $third_wins - $third_losses ($thirdpct)<br>
<b>Series:</b> $third_round_victories - $third_round_losses ($r3wlpct)</td></tr>";
}
if ($round_four_output != "")
{
  $output=$output."<tr bgcolor=\"#$color1\"><td align=center><font color=\"#$color2\"><b>IBL Finals Playoff Results</b></font></td></tr>
<tr><td>
<div id=\"History-P4\" style=\"overflow:auto; height:150px\">".$round_four_output."</div></td></tr>
<tr><td><b>Totals:</b> $fourth_wins - $fourth_losses ($fourthpct)<br>
<b>Series:</b> $fourth_round_victories - $fourth_round_losses ($r4wlpct)</td></tr>";
}

$output=$output."<tr bgcolor=\"#$color1\"><td align=center><font color=\"#$color2\"><b>Playoff Totals</b></font></td></tr>
<tr><td><b>Games:</b> $totalplayoffwins - $totalplayofflosses ($pwlpct)</td></tr>
<tr><td><b>Series:</b> $round_victories - $round_losses ($swlpct)</td></tr>
</table>
";

$ultimate_output[0]=$output;

return $ultimate_output;
}

/************************************************************************/
/* END FUNCTION TO DISPLAY TEAM INFO BAR ON THE */
/* RIGHT HAND SIDE OF THE TEAM PAGE                         */
/************************************************************************/


/************************************************************************/
/* BEGIN DISPLAY INJURED PLAYERS                                        */
/************************************************************************/
function viewinjuries()
{
    include("header.php");
    OpenTable();

    displaytopmenu($tid);

    $query="SELECT * FROM nuke_iblplyr WHERE injured > 0 AND retired = 0 ORDER BY ordinal ASC";

    $result=mysql_query($query);
    $num=mysql_numrows($result);

    echo "<center><h2>INJURED PLAYERS</h2></center>
      <table><tr><td valign=top>
      <table>
      <tr><td>Pos</td><td>Player</td><td>Team</td><td>Days Injured</td>";

    $i=0;

    while ($i < $num)
    {
    $name=mysql_result($result,$i,"name");
    $team=mysql_result($result,$i,"teamname");
    $pid=mysql_result($result,$i,"pid");
    $tid=mysql_result($result,$i,"tid");
    $pos=mysql_result($result,$i,"pos");
    $inj=mysql_result($result,$i,"injured");

    echo "      <tr><td>$pos</td><td><a href=\"modules.php?name=Player&pa=showpage&pid=$pid\">$name</a></td><td><a href=\"modules.php?name=Team&op=team&tid=$tid\">$team</a></td><td>$inj</td></tr>
";

    $i++;

    }

echo "</table></table>";

    CloseTable();
    include("footer.php");

}

/************************************************************************/
/* END DISPLAY INJURED PLAYERS                                          */
/************************************************************************/

function menu()
{
    global $prefix, $db, $sitename, $admin, $module_name, $user, $cookie;
    $tid = intval($tid);

    include("header.php");
    OpenTable();

displaytopmenu($tid);

    CloseTable();
    include("footer.php");

}


function seteditor($user) {
    global $stop, $module_name, $redirect, $mode, $t, $f, $gfx_chk;
    if(!is_user($user)) {
	include("header.php");
	if ($stop) {
	    OpenTable();
            displaytopmenu($tid);

	    echo "<center><font class=\"title\"><b>"._LOGININCOR."</b></font></center>\n";
	    CloseTable();
	    echo "<br>\n";
	} else {
	    OpenTable();
            displaytopmenu($tid);
	    echo "<center><font class=\"title\"><b>"._USERREGLOGIN."</b></font></center>\n";
	    CloseTable();
	    echo "<br>\n";
	}
	if (!is_user($user)) {
	    OpenTable();
            displaytopmenu($tid);

	    mt_srand ((double)microtime()*1000000);
	    $maxran = 1000000;
	    $random_num = mt_rand(0, $maxran);
	    echo "<form action=\"modules.php?name=$module_name\" method=\"post\">\n"
		."<b>"._USERLOGIN."</b><br><br>\n"
		."<table border=\"0\"><tr><td>\n"
		.""._NICKNAME.":</td><td><input type=\"text\" name=\"username\" size=\"15\" maxlength=\"25\"></td></tr>\n"
		."<tr><td>"._PASSWORD.":</td><td><input type=\"password\" name=\"user_password\" size=\"15\" maxlength=\"20\"></td></tr>\n";
	    if (extension_loaded("gd") AND ($gfx_chk == 2 OR $gfx_chk == 4 OR $gfx_chk == 5 OR $gfx_chk == 7)) {
		echo "<tr><td colspan='2'>"._SECURITYCODE.": <img src='modules.php?name=$module_name&op=gfx&random_num=$random_num' border='1' alt='"._SECURITYCODE."' title='"._SECURITYCODE."'></td></tr>\n"
		    ."<tr><td colspan='2'>"._TYPESECCODE.": <input type=\"text\" NAME=\"gfx_check\" SIZE=\"7\" MAXLENGTH=\"6\"></td></tr>\n"
		    ."<input type=\"hidden\" name=\"random_num\" value=\"$random_num\">\n";
	    }
	    echo "</table><input type=\"hidden\" name=\"redirect\" value=$redirect>\n"
		."<input type=\"hidden\" name=\"mode\" value=$mode>\n"
		."<input type=\"hidden\" name=\"f\" value=$f>\n"
		."<input type=\"hidden\" name=\"t\" value=$t>\n"
		."<input type=\"hidden\" name=\"op\" value=\"login\">\n"
		."<input type=\"submit\" value=\""._LOGIN."\"></form><br>\n\n"
		."<center><font class=\"content\">[ <a href=\"modules.php?name=$module_name&amp;op=pass_lost\">"._PASSWORDLOST."</a> | <a href=\"modules.php?name=$module_name&amp;op=new_user\">"._REGNEWUSER."</a> ]</font></center>\n";
	    CloseTable();
	}
	include("footer.php");
    } elseif (is_user($user)) {
        global $cookie;
        cookiedecode($user);
        editset($cookie[1]);
    }

}

/************************************************************************/
/* BEGIN OFFENSIVE SET EDITOR                                           */
/************************************************************************/

function editset($username, $bypass=0, $hid=0, $url=0) {
    global $user, $cookie, $sitename, $prefix, $user_prefix, $db, $admin, $broadcast_msg, $my_headlines, $module_name, $subscription_url, $attrib, $step, $player;
    $sql = "SELECT * FROM ".$prefix."_bbconfig";
    $result = $db->sql_query($sql);
    while ( $row = $db->sql_fetchrow($result) )
    {
    $board_config[$row['config_name']] = $row['config_value'];
    }

// ==== PICKUP LOGGED-IN USER INFO

    $sql2 = "SELECT * FROM ".$user_prefix."_users WHERE username='$username'";
    $result2 = $db->sql_query($sql2);
    $num = $db->sql_numrows($result2);
    $userinfo = $db->sql_fetchrow($result2);
    if(!$bypass) cookiedecode($user);

// ===== END OF INFO PICKUP

    include("header.php");

    OpenTable();

    displaytopmenu($tid);

// === GRAB TEAM INFORMATION FOR LOGGED-IN USER===

    $teamlogo = $userinfo[user_ibl_team];

    echo "<hr>
    <center><img src=\"online/teamgrfx/$teamlogo.jpg\"><br>
";

    $sql3 = "SELECT * FROM nuke_ibl_offense_sets WHERE TeamName = '$teamlogo' ORDER BY SetNumber ASC ";
    $result3 = $db->sql_query($sql3);
    $num3 = $db->sql_numrows($result3);

$SetToWorkOn = $_POST['SelectedSet'];


echo "<table align=center valign=top><tr><th><center>Gameplan Review - $teamlogo</center></th></tr>
<tr><td><center>
<form name=\"Set_Editor\" method=\"post\" action=\"modules.php?name=Team&op=seteditor\"><select name=\"SelectedSet\">";

$i=0;

while ($i < 3)
{

$name_of_set=mysql_result($result3,$i,"offense_name");
$setnumbervalue=mysql_result($result3,$i,"SetNumber");

  if ($SetToWorkOn == $setnumbervalue) {
    echo "	<option value=\"$setnumbervalue\" SELECTED>$name_of_set</option>
";
      } else {
    echo "	<option value=\"$setnumbervalue\">$name_of_set</option>
";
  }
$i++;
}

echo "</select><input type=\"submit\" value=\"Select This Set\"></form></td></tr>";

if ($SetToWorkOn == NULL)
{
} else {
    $sql4 = "SELECT * FROM nuke_ibl_offense_sets WHERE TeamName = '$teamlogo' AND SetNumber = '$SetToWorkOn'";
    $result4 = $db->sql_query($sql4);
    $num4 = $db->sql_numrows($result4);

$name1=mysql_result($result4,0,"PG_Depth_Name");
$name2=mysql_result($result4,0,"SG_Depth_Name");
$name3=mysql_result($result4,0,"SF_Depth_Name");
$name4=mysql_result($result4,0,"PF_Depth_Name");
$name5=mysql_result($result4,0,"C_Depth_Name");

$low1=mysql_result($result4,0,"PG_Low_Range");;
$low2=mysql_result($result4,0,"SG_Low_Range");
$low3=mysql_result($result4,0,"SF_Low_Range");
$low4=mysql_result($result4,0,"PF_Low_Range");
$low5=mysql_result($result4,0,"C_Low_Range");

$high1=mysql_result($result4,0,"PG_High_Range");
$high2=mysql_result($result4,0,"SG_High_Range");
$high3=mysql_result($result4,0,"SF_High_Range");
$high4=mysql_result($result4,0,"PF_High_Range");
$high5=mysql_result($result4,0,"C_High_Range");

$totalslots = $high1+$high2+$high3+$high4+$high5-$low1-$low2-$low3-$low4-$low5+5;

echo "<tr><td>
<table><tr><th>Pos</th><th>$name1</th><th>$name2</th><th>$name3</th><th>$name4</th><th>$name5</th><th>Roster</th></tr>";

$j=1;

while ($j < 10)
{

if ($j == 1)
{
echo "<tr><td>PG</td>";
} else if ($j == 2) {
echo "<tr><td>G</td>";
} else if ($j == 3) {
echo "<tr><td>SG</td>";
} else if ($j == 4) {
echo "<tr><td>GF</td>";
} else if ($j == 5) {
echo "<tr><td>SF</td>";
} else if ($j == 6) {
echo "<tr><td>F</td>";
} else if ($j == 7) {
echo "<tr><td>PF</td>";
} else if ($j == 8) {
echo "<tr><td>FC</td>";
} else {
echo "<tr><td>C</td>";
}

$check1=OffenseSetPositionCheck($j,$low1,$high1);
if ($check1 == 1 )
{
echo "<td>OK</td>";
} else if ($check1 == 2 ) {
  if ($low1+1 == $high1)
  {
  echo "<td>OK</td>";
  } else {
  echo "<td>OK - <a href=\"modules.php?name=Team&op=changeset&action=remove&set=$SetToWorkOn&type=low&position=1\">Remove</a></td>";
  }
} else if ($check1 == 3 ) {
  if ($low1+1 == $high1)
  {
  echo "<td>OK</td>";
  } else {
  echo "<td>OK - <a href=\"modules.php?name=Team&op=changeset&action=remove&set=$SetToWorkOn&type=high&position=1\">Remove</a></td>";
  }
} else if ($check1 == 4 ) {
  if ($totalslots < 22)
  {
  echo "<td>-- <a href=\"modules.php?name=Team&op=changeset&action=add&set=$SetToWorkOn&type=low&position=1\">Add</a></td>";
  } else {
  echo "<td>--</td>";
  }
} else if ($check1 == 5 ) {
  if ($totalslots < 22)
  {
  echo "<td>-- <a href=\"modules.php?name=Team&op=changeset&action=add&set=$SetToWorkOn&type=high&position=1\">Add</a></td>";
  } else {
  echo "<td>--</td>";
  }
} else if ($check1 == 6 ) {
  if ($totalslots < 22)
  {
  echo "<td>-- <a href=\"modules.php?name=Team&op=changeset&action=add&set=$SetToWorkOn&type=low&position=1\">Add</a></td>";
  } else {
  echo "<td>--</td>";
  }
} else {
echo "<td>--</td>";
}

$check2=OffenseSetPositionCheck($j,$low2,$high2);
if ($check2 == 1 )
{
echo "<td>OK</td>";
} else if ($check2 == 2 ) {
  if ($low2+1 == $high2)
  {
  echo "<td>OK</td>";
  } else {
  echo "<td>OK - <a href=\"modules.php?name=Team&op=changeset&action=remove&set=$SetToWorkOn&type=low&position=2\">Remove</a></td>";
  }
} else if ($check2 == 3 ) {
  if ($low2+1 == $high2)
  {
  echo "<td>OK</td>";
  } else {
  echo "<td>OK - <a href=\"modules.php?name=Team&op=changeset&action=remove&set=$SetToWorkOn&type=high&position=2\">Remove</a></td>";
  }
} else if ($check2 == 4 ) {
  if ($totalslots < 22)
  {
  echo "<td>-- <a href=\"modules.php?name=Team&op=changeset&action=add&set=$SetToWorkOn&type=low&position=2\">Add</a></td>";
  } else {
  echo "<td>--</td>";
  }
} else if ($check2 == 5 ) {
  if ($totalslots < 22)
  {
  echo "<td>-- <a href=\"modules.php?name=Team&op=changeset&action=add&set=$SetToWorkOn&type=high&position=2\">Add</a></td>";
  } else {
  echo "<td>--</td>";
  }
} else if ($check2 == 6 ) {
  if ($totalslots < 22)
  {
  echo "<td>-- <a href=\"modules.php?name=Team&op=changeset&action=add&set=$SetToWorkOn&type=low&position=1\">Add</a></td>";
  } else {
  echo "<td>--</td>";
  }
} else {
echo "<td>--</td>";
}

$check3=OffenseSetPositionCheck($j,$low3,$high3);
if ($check3 == 1 )
{
echo "<td>OK</td>";
} else if ($check3 == 2 ) {
  if ($low3+1 == $high3)
  {
  echo "<td>OK</td>";
  } else {
  echo "<td>OK - <a href=\"modules.php?name=Team&op=changeset&action=remove&set=$SetToWorkOn&type=low&position=3\">Remove</a></td>";
  }
} else if ($check3 == 3 ) {
  if ($low3+1 == $high3)
  {
  echo "<td>OK</td>";
  } else {
  echo "<td>OK - <a href=\"modules.php?name=Team&op=changeset&action=remove&set=$SetToWorkOn&type=high&position=3\">Remove</a></td>";
  }
} else if ($check3 == 4 ) {
  if ($totalslots < 22)
  {
  echo "<td>-- <a href=\"modules.php?name=Team&op=changeset&action=add&set=$SetToWorkOn&type=low&position=3\">Add</a></td>";
  } else {
  echo "<td>--</td>";
  }
} else if ($check3 == 5 ) {
  if ($totalslots < 22)
  {
  echo "<td>-- <a href=\"modules.php?name=Team&op=changeset&action=add&set=$SetToWorkOn&type=high&position=3\">Add</a></td>";
  } else {
  echo "<td>--</td>";
  }
} else if ($check3 == 6 ) {
  if ($totalslots < 22)
  {
  echo "<td>-- <a href=\"modules.php?name=Team&op=changeset&action=add&set=$SetToWorkOn&type=low&position=1\">Add</a></td>";
  } else {
  echo "<td>--</td>";
  }
} else {
echo "<td>--</td>";
}

$check4=OffenseSetPositionCheck($j,$low4,$high4);
if ($check4 == 1 )
{
echo "<td>OK</td>";
} else if ($check4 == 2 ) {
  if ($low4+1 == $high4)
  {
  echo "<td>OK</td>";
  } else {
  echo "<td>OK - <a href=\"modules.php?name=Team&op=changeset&action=remove&set=$SetToWorkOn&type=low&position=4\">Remove</a></td>";
  }
} else if ($check4 == 3 ) {
  if ($low4+1 == $high4)
  {
  echo "<td>OK</td>";
  } else {
  echo "<td>OK - <a href=\"modules.php?name=Team&op=changeset&action=remove&set=$SetToWorkOn&type=high&position=4\">Remove</a></td>";
  }
} else if ($check4 == 4 ) {
  if ($totalslots < 22)
  {
  echo "<td>-- <a href=\"modules.php?name=Team&op=changeset&action=add&set=$SetToWorkOn&type=low&position=4\">Add</a></td>";
  } else {
  echo "<td>--</td>";
  }
} else if ($check4 == 5 ) {
  if ($totalslots < 22)
  {
  echo "<td>-- <a href=\"modules.php?name=Team&op=changeset&action=add&set=$SetToWorkOn&type=high&position=4\">Add</a></td>";
  } else {
  echo "<td>--</td>";
  }
} else if ($check4 == 6 ) {
  if ($totalslots < 22)
  {
  echo "<td>-- <a href=\"modules.php?name=Team&op=changeset&action=add&set=$SetToWorkOn&type=low&position=1\">Add</a></td>";
  } else {
  echo "<td>--</td>";
  }
} else {
echo "<td>--</td>";
}

$check5=OffenseSetPositionCheck($j,$low5,$high5);
if ($check5 == 1 )
{
echo "<td>OK</td>";
} else if ($check5 == 2 ) {
  if ($low5+1 == $high5)
  {
  echo "<td>OK</td>";
  } else {
  echo "<td>OK - <a href=\"modules.php?name=Team&op=changeset&action=remove&set=$SetToWorkOn&type=low&position=5\">Remove</a></td>";
  }
} else if ($check5 == 3 ) {
  if ($low5+1 == $high5)
  {
  echo "<td>OK</td>";
  } else {
  echo "<td>OK - <a href=\"modules.php?name=Team&op=changeset&action=remove&set=$SetToWorkOn&type=high&position=5\">Remove</a></td>";
  }
} else if ($check5 == 4 ) {
  if ($totalslots < 22)
  {
  echo "<td>-- <a href=\"modules.php?name=Team&op=changeset&action=add&set=$SetToWorkOn&type=low&position=5\">Add</a></td>";
  } else {
  echo "<td>--</td>";
  }
} else if ($check5 == 5 ) {
  if ($totalslots < 22)
  {
  echo "<td>-- <a href=\"modules.php?name=Team&op=changeset&action=add&set=$SetToWorkOn&type=high&position=5\">Add</a></td>";
  } else {
  echo "<td>--</td>";
  }
} else if ($check5 == 6 ) {
  if ($totalslots < 22)
  {
  echo "<td>-- <a href=\"modules.php?name=Team&op=changeset&action=add&set=$SetToWorkOn&type=low&position=1\">Add</a></td>";
  } else {
  echo "<td>--</td>";
  }
} else {
echo "<td>--</td>";
}

echo "<td>";

if ($j == 1)
{
    $sql5 = "SELECT * FROM nuke_iblplyr WHERE teamname = '$teamlogo' AND altpos = 'PG' AND retired = '0' ORDER BY ordinal ASC ";
    $result5 = $db->sql_query($sql5);
    $num5 = $db->sql_numrows($result5);
} else if ($j == 2) {
    $sql5 = "SELECT * FROM nuke_iblplyr WHERE teamname = '$teamlogo' AND altpos = 'G' AND retired = '0' ORDER BY ordinal ASC ";
    $result5 = $db->sql_query($sql5);
    $num5 = $db->sql_numrows($result5);
} else if ($j == 3) {
    $sql5 = "SELECT * FROM nuke_iblplyr WHERE teamname = '$teamlogo' AND altpos = 'SG' AND retired = '0' ORDER BY ordinal ASC ";
    $result5 = $db->sql_query($sql5);
    $num5 = $db->sql_numrows($result5);
} else if ($j == 4) {
    $sql5 = "SELECT * FROM nuke_iblplyr WHERE teamname = '$teamlogo' AND altpos = 'GF' AND retired = '0' ORDER BY ordinal ASC ";
    $result5 = $db->sql_query($sql5);
    $num5 = $db->sql_numrows($result5);
} else if ($j == 5) {
    $sql5 = "SELECT * FROM nuke_iblplyr WHERE teamname = '$teamlogo' AND altpos = 'SF' AND retired = '0' ORDER BY ordinal ASC ";
    $result5 = $db->sql_query($sql5);
    $num5 = $db->sql_numrows($result5);
} else if ($j == 6) {
    $sql5 = "SELECT * FROM nuke_iblplyr WHERE teamname = '$teamlogo' AND altpos = 'F' AND retired = '0' ORDER BY ordinal ASC ";
    $result5 = $db->sql_query($sql5);
    $num5 = $db->sql_numrows($result5);
} else if ($j == 7) {
    $sql5 = "SELECT * FROM nuke_iblplyr WHERE teamname = '$teamlogo' AND altpos = 'PF' AND retired = '0' ORDER BY ordinal ASC ";
    $result5 = $db->sql_query($sql5);
    $num5 = $db->sql_numrows($result5);
} else if ($j == 8) {
    $sql5 = "SELECT * FROM nuke_iblplyr WHERE teamname = '$teamlogo' AND altpos = 'FC' AND retired = '0' ORDER BY ordinal ASC ";
    $result5 = $db->sql_query($sql5);
    $num5 = $db->sql_numrows($result5);
} else {
    $sql5 = "SELECT * FROM nuke_iblplyr WHERE teamname = '$teamlogo' AND altpos = 'C' AND retired = '0' ORDER BY ordinal ASC ";
    $result5 = $db->sql_query($sql5);
    $num5 = $db->sql_numrows($result5);
}

$k=0;

while ($k < $num5)
{

$playername=mysql_result($result5,$k,"name");
$pid=mysql_result($result5,$k,"pid");

echo "<a href=\"modules.php?name=Player&pa=showpage&pid=$pid\">$playername</a> | ";

$k++;
}

echo "</td></tr>
";

$j++;
}

echo "<tr><th colspan=6><center>Total Slots Used (22 max): $totalslots</center></th></tr></table>
</td></tr>";
}

echo "</table>";

    CloseTable();
    include("footer.php");
}

/************************************************************************/
/* END OFFENSIVE SET EDITOR                                             */
/************************************************************************/

/************************************************************************/
/* BEGIN WAIVER WIRE CALL                                               */
/************************************************************************/

function changeset($user) {
    global $stop, $module_name, $redirect, $mode, $t, $f, $gfx_chk, $action, $set, $type, $position;
    if(!is_user($user)) {
	include("header.php");
	if ($stop) {
	    OpenTable();
            displaytopmenu($tid);

	    echo "<center><font class=\"title\"><b>"._LOGININCOR."</b></font></center>\n";
	    CloseTable();
	    echo "<br>\n";
	} else {
	    OpenTable();
            displaytopmenu($tid);
	    echo "<center><font class=\"title\"><b>"._USERREGLOGIN."</b></font></center>\n";
	    CloseTable();
	    echo "<br>\n";
	}
	if (!is_user($user)) {
	    OpenTable();
            displaytopmenu($tid);

	    mt_srand ((double)microtime()*1000000);
	    $maxran = 1000000;
	    $random_num = mt_rand(0, $maxran);
	    echo "<form action=\"modules.php?name=$module_name\" method=\"post\">\n"
		."<b>"._USERLOGIN."</b><br><br>\n"
		."<table border=\"0\"><tr><td>\n"
		.""._NICKNAME.":</td><td><input type=\"text\" name=\"username\" size=\"15\" maxlength=\"25\"></td></tr>\n"
		."<tr><td>"._PASSWORD.":</td><td><input type=\"password\" name=\"user_password\" size=\"15\" maxlength=\"20\"></td></tr>\n";
	    if (extension_loaded("gd") AND ($gfx_chk == 2 OR $gfx_chk == 4 OR $gfx_chk == 5 OR $gfx_chk == 7)) {
		echo "<tr><td colspan='2'>"._SECURITYCODE.": <img src='modules.php?name=$module_name&op=gfx&random_num=$random_num' border='1' alt='"._SECURITYCODE."' title='"._SECURITYCODE."'></td></tr>\n"
		    ."<tr><td colspan='2'>"._TYPESECCODE.": <input type=\"text\" NAME=\"gfx_check\" SIZE=\"7\" MAXLENGTH=\"6\"></td></tr>\n"
		    ."<input type=\"hidden\" name=\"random_num\" value=\"$random_num\">\n";
	    }
	    echo "</table><input type=\"hidden\" name=\"redirect\" value=$redirect>\n"
		."<input type=\"hidden\" name=\"mode\" value=$mode>\n"
		."<input type=\"hidden\" name=\"f\" value=$f>\n"
		."<input type=\"hidden\" name=\"t\" value=$t>\n"
		."<input type=\"hidden\" name=\"op\" value=\"login\">\n"
		."<input type=\"submit\" value=\""._LOGIN."\"></form><br>\n\n"
		."<center><font class=\"content\">[ <a href=\"modules.php?name=$module_name&amp;op=pass_lost\">"._PASSWORDLOST."</a> | <a href=\"modules.php?name=$module_name&amp;op=new_user\">"._REGNEWUSER."</a> ]</font></center>\n";
	    CloseTable();
	}
	include("footer.php");
    } elseif (is_user($user)) {
        global $cookie;
        cookiedecode($user);
        changesetgo($cookie[1],$action,$set,$type,$position);
    }

}

/************************************************************************/
/* END OFFENSE SET EDIT CALL                                            */
/************************************************************************/

/************************************************************************/
/* BEGIN CHANGE OFFENSIVE SET                                           */
/************************************************************************/

function changesetgo($username, $action, $set, $type, $position) {
    global $user, $cookie, $sitename, $prefix, $user_prefix, $db, $admin, $broadcast_msg, $my_headlines, $module_name, $subscription_url;
    $sql = "SELECT * FROM ".$prefix."_bbconfig";
    $result = $db->sql_query($sql);
    while ( $row = $db->sql_fetchrow($result) )
    {
    $board_config[$row['config_name']] = $row['config_value'];
    }
    $sql2 = "SELECT * FROM ".$user_prefix."_users WHERE username='$username'";
    $result2 = $db->sql_query($sql2);
    $num = $db->sql_numrows($result2);
    $userinfo = $db->sql_fetchrow($result2);
    $teamlogo = $userinfo[user_ibl_team];
    if(!$bypass) cookiedecode($user);
    include("header.php");

	    OpenTable();
            displaytopmenu($tid);

// === CODE TO CHANGE SET ===

    $query_loc=NULL;

    if ($position == 1)
    {
    $query_loc='PG';
    } else if ($position == 2) {
    $query_loc='SG';
    } else if ($position == 3) {
    $query_loc='SF';
    } else if ($position == 4) {
    $query_loc='PF';
    } else if ($position == 5) {
    $query_loc='C';
    }

    if ($type == 'low')
    {
    $query_loc=$query_loc.'_Low_Range';
    } else if ($type == 'high') {
    $query_loc=$query_loc.'_High_Range';
    }

    $sqla = "SELECT * FROM nuke_ibl_offense_sets WHERE `TeamName` = '$teamlogo' AND `SetNumber` = '$set'";
    $resulta = $db->sql_query($sqla);

    $newtarget=mysql_result($resulta,0,"$query_loc");

    if ($action == 'add')
    {
      if ($type == 'low')
      {
      $newtarget=$newtarget-1;
      } else if ($type == 'high' ) {
      $newtarget=$newtarget+1;
      }
    } else if ($action == 'remove') {
      if ($type == 'low')
      {
      $newtarget=$newtarget+1;
      } else if ($type == 'high' ) {
      $newtarget=$newtarget-1;
      }
    }

/************************************************************************/
/* ONCES SEASON STARTS DISABLE CHANGE OPTION                            */
/************************************************************************/

    $sqlb = "UPDATE nuke_ibl_offense_sets SET `$query_loc` = '$newtarget' WHERE `TeamName` = '$teamlogo' AND `SetNumber` = '$set'";
    $resultb = $db->sql_query($sqlb);

	    echo "Your change has been made; please click the button to view changes.";
		//echo "You can't make changes to the editor once the season starts but can only view your game plans.";

            echo"<form name=\"Set_Editor\" method=\"post\" action=\"modules.php?name=Team&op=seteditor\">
                 <input type=\"hidden\" name=\"SelectedSet\" value=\"$set\"><input type=\"submit\" value=\"Return to Set Editor\"></form>";
	    CloseTable();

}

/************************************************************************/
/* END CHANGE OFFENSE SET                                               */
/************************************************************************/



function OffenseSetPositionCheck($Slot, $Low, $High)
{
//echo "Slot:$Slot<br>Low:$Low<br>High:$High<br><br>";
  if ($Low > $High)
    {
      return 6;
    }

  if ($Low < $Slot)
    {
      if ($High > $Slot)
      {
        return 1;
      }
    }

  if ($Low == $Slot)
    {
      return 2;
    }

  if ($High == $Slot)
    {
      return 3;
    }

  if ($Low == ($Slot+1))
    {
      return 4;
    }

  if ($High == ($Slot-1))
    {
      return 5;
    }

  return 0;

}

/************************************************************************/
/* BEGIN WAIVER WIRE CALL                                               */
/************************************************************************/

function waivers($user) {
    global $stop, $module_name, $redirect, $mode, $t, $f, $gfx_chk, $action;
    if(!is_user($user)) {
	include("header.php");
	if ($stop) {
	    OpenTable();
            displaytopmenu($tid);

	    echo "<center><font class=\"title\"><b>"._LOGININCOR."</b></font></center>\n";
	    CloseTable();
	    echo "<br>\n";
	} else {
	    OpenTable();
            displaytopmenu($tid);
	    echo "<center><font class=\"title\"><b>"._USERREGLOGIN."</b></font></center>\n";
	    CloseTable();
	    echo "<br>\n";
	}
	if (!is_user($user)) {
	    OpenTable();
            displaytopmenu($tid);

	    mt_srand ((double)microtime()*1000000);
	    $maxran = 1000000;
	    $random_num = mt_rand(0, $maxran);
	    echo "<form action=\"modules.php?name=$module_name\" method=\"post\">\n"
		."<b>"._USERLOGIN."</b><br><br>\n"
		."<table border=\"0\"><tr><td>\n"
		.""._NICKNAME.":</td><td><input type=\"text\" name=\"username\" size=\"15\" maxlength=\"25\"></td></tr>\n"
		."<tr><td>"._PASSWORD.":</td><td><input type=\"password\" name=\"user_password\" size=\"15\" maxlength=\"20\"></td></tr>\n";
	    if (extension_loaded("gd") AND ($gfx_chk == 2 OR $gfx_chk == 4 OR $gfx_chk == 5 OR $gfx_chk == 7)) {
		echo "<tr><td colspan='2'>"._SECURITYCODE.": <img src='modules.php?name=$module_name&op=gfx&random_num=$random_num' border='1' alt='"._SECURITYCODE."' title='"._SECURITYCODE."'></td></tr>\n"
		    ."<tr><td colspan='2'>"._TYPESECCODE.": <input type=\"text\" NAME=\"gfx_check\" SIZE=\"7\" MAXLENGTH=\"6\"></td></tr>\n"
		    ."<input type=\"hidden\" name=\"random_num\" value=\"$random_num\">\n";
	    }
	    echo "</table><input type=\"hidden\" name=\"redirect\" value=$redirect>\n"
		."<input type=\"hidden\" name=\"mode\" value=$mode>\n"
		."<input type=\"hidden\" name=\"f\" value=$f>\n"
		."<input type=\"hidden\" name=\"t\" value=$t>\n"
		."<input type=\"hidden\" name=\"op\" value=\"login\">\n"
		."<input type=\"submit\" value=\""._LOGIN."\"></form><br>\n\n"
		."<center><font class=\"content\">[ <a href=\"modules.php?name=$module_name&amp;op=pass_lost\">"._PASSWORDLOST."</a> | <a href=\"modules.php?name=$module_name&amp;op=new_user\">"._REGNEWUSER."</a> ]</font></center>\n";
	    CloseTable();
	}
	include("footer.php");
    } elseif (is_user($user)) {
        global $cookie;
        cookiedecode($user);
        waiverexecute($cookie[1],$action);
    }

}

/************************************************************************/
/* END WAIVER WIRE CALL                                                 */
/************************************************************************/

/************************************************************************/
/* BEGIN WAIVER WIRE EXECUTE                                            */
/************************************************************************/

function waiverexecute($username, $action, $bypass=0, $hid=0, $url=0) {
    global $user, $cookie, $sitename, $prefix, $user_prefix, $db, $admin, $broadcast_msg, $my_headlines, $module_name, $subscription_url, $partner, $action;
    $sql = "SELECT * FROM ".$prefix."_bbconfig";
    $result = $db->sql_query($sql);
    while ( $row = $db->sql_fetchrow($result) )
    {
    $board_config[$row['config_name']] = $row['config_value'];
    }
    $sql2 = "SELECT * FROM ".$user_prefix."_users WHERE username='$username'";
    $result2 = $db->sql_query($sql2);
    $num = $db->sql_numrows($result2);
    $userinfo = $db->sql_fetchrow($result2);
    if(!$bypass) cookiedecode($user);
    include("header.php");

// === CODE TO INSERT TRADE STUFF ===

    OpenTable();

    displaytopmenu($tid);

    $teamlogo = $userinfo[user_ibl_team];
    $sql7 = "SELECT * FROM nuke_ibl_team_info ORDER BY teamid ASC ";
    $result7 = $db->sql_query($sql7);

    $sql9 = "SELECT * FROM nuke_iblplyr WHERE teamname='$userinfo[user_ibl_team]' AND retired = '0' AND ordinal < '961' AND injured = '0' ORDER BY ordinal ASC ";
    $result9 = $db->sql_query($sql9);

$healthyrosterslots=15;

    while($row9 = $db->sql_fetchrow($result9)) {
		$healthyrosterslots--;
	}

$sql10 = "SELECT * FROM nuke_iblplyr WHERE teamname='$userinfo[user_ibl_team]' AND retired = '0' AND ordinal < '961' ORDER BY ordinal ASC ";
    $result10 = $db->sql_query($sql10);

$rosterslots=15;

    while($row10 = $db->sql_fetchrow($result10)) {
		$rosterslots--;
	}

    if ($action == 'drop')
    {
      $sql8 = "SELECT * FROM nuke_iblplyr WHERE teamname='$userinfo[user_ibl_team]' AND retired = '0' AND ordinal < '961' ORDER BY ordinal ASC ";
      $result8 = $db->sql_query($sql8);

    } else {

      $sql8 = "SELECT * FROM nuke_iblplyr WHERE ordinal > '960' AND retired = '0' AND in_euro = '0' ORDER BY ordinal ASC ";
      $result8 = $db->sql_query($sql8);

    }

    echo "<hr>
    <form name=\"Waiver_Move\" method=\"post\" action=\"../waivermove.php\">
    <input type=\"hidden\" name=\"Team_Name\" value=\"$teamlogo\">";

    if ($action == 'drop')
    {
      echo "
     <input type=\"hidden\" name=\"Action\" value=\"drop\">";
    } else if ($action == 'add') {
      echo "
     <input type=\"hidden\" name=\"Action\" value=\"add\">";
    }

echo "
    <center><img src=\"online/teamgrfx/$teamlogo.jpg\"><br><table border=1 cellspacing=0 cellpadding=0><tr><th colspan=3><center>WAIVER WIRE - YOUR TEAM CURRENTLY HAS $rosterslots EMPTY ROSTER SPOTS and $healthyrosterslots HEALTHY ROSTER SPOTS</center></th></tr>
<tr><td valign=top><center><B><u>$userinfo[user_ibl_team]</u></b></center>";

$k=0;
$timenow = intval(time());

    while($row8 = $db->sql_fetchrow($result8))
    {
        $wait_time = '';
	$player_pos = $row8[altpos];
	$player_name = $row8[name];
	$player_pid = $row8[pid];
	$cy = $row8[cy];
	$cyy = "cy$cy";
	$player_exp = $row8[exp];
	$cy2 = $row8[$cyy];

	if ($cy2 == '')
	{
		if ($player_exp > 9) {
		$cy2=103;
		} elseif ($player_exp > 8) {
		$cy2=100;
		} elseif ($player_exp > 7) {
		$cy2=89;
		} elseif ($player_exp > 6) {
		$cy2=82;
		} elseif ($player_exp > 5) {
		$cy2=76;
		} elseif ($player_exp > 4) {
		$cy2=70;
		} elseif ($player_exp > 3) {
		$cy2=64;
		} elseif ($player_exp > 2) {
		$cy2=61;
		} elseif ($player_exp > 1) {
		$cy2=51;
		} else {
		$cy2=35;
		}
	}
        $nocheckbox=0;

        if ($action == 'add')
        {

 	  $player_droptime = $row8[droptime];
          $time_diff = $timenow-$player_droptime;

          if ($time_diff < 86400)
          {
            $wait_time = 86400-$time_diff;
            $time_hours = floor($wait_time/3600);
            $time_minutes = floor(($wait_time-$time_hours*3600)/60);
            $time_seconds = ($wait_time%60);
            $wait_time = '(Clears in '.$time_hours.' h, '.$time_minutes.' m, '.$time_seconds.' s)';
            $nocheckbox=1;
          }

        }



        echo "<input type=\"hidden\" name=\"index$k\" value=\"$player_pid\"><input type=\"hidden\" name=\"cy$k\" value=\"$cy2\"><input type=\"hidden\" name=\"type$k\" value=\"1\">";

        if ($nocheckbox == 1)
        {
        echo "** $player_pos $player_name $wait_time
<br>";
        } else {
        echo "<input type=\"checkbox\" name=\"check$k\"> $player_pos $player_name $wait_time $$cy2
<br>";

        }

$k++;

    }
echo $teamlogo;
echo "<input type=\"hidden\" name=\"counterfields\" value=\"$k\"><input type=\"hidden\" name=\"rosterslots\" value=\"$rosterslots\"></td>
";

echo "<input type=\"hidden\" name=\"counterfields\" value=\"$k\"><input type=\"hidden\" name=\"healthyrosterslots\" value=\"$healthyrosterslots\"></td>
";

echo "</td></tr><tr><td colspan=3><center><input type=\"submit\" value=\"Click to $action player(s) to/from Waiver Pool\"></td></tr></form></center></table></center>";
    CloseTable();

// === END INSERT OF TRADE STUFF ===

    include("footer.php");
}

/************************************************************************/
/* END WAIVER WIRE EXECUTE
*/
/************************************************************************/

/************************************************************************/
/* BEGIN DISPLAY TRAINING PREFERENCES                                   */
/************************************************************************/

function viewtraining($user) {
    global $stop, $module_name, $redirect, $mode, $t, $f, $gfx_chk;
    if(!is_user($user)) {
	include("header.php");
	if ($stop) {
	    OpenTable();
            displaytopmenu($tid);

	    echo "<center><font class=\"title\"><b>"._LOGININCOR."</b></font></center>\n";
	    CloseTable();
	    echo "<br>\n";
	} else {
	    OpenTable();
            displaytopmenu($tid);
	    echo "<center><font class=\"title\"><b>"._USERREGLOGIN."</b></font></center>\n";
	    CloseTable();
	    echo "<br>\n";
	}
	if (!is_user($user)) {
	    OpenTable();
            displaytopmenu($tid);

	    mt_srand ((double)microtime()*1000000);
	    $maxran = 1000000;
	    $random_num = mt_rand(0, $maxran);
	    echo "<form action=\"modules.php?name=$module_name\" method=\"post\">\n"
		."<b>"._USERLOGIN."</b><br><br>\n"
		."<table border=\"0\"><tr><td>\n"
		.""._NICKNAME.":</td><td><input type=\"text\" name=\"username\" size=\"15\" maxlength=\"25\"></td></tr>\n"
		."<tr><td>"._PASSWORD.":</td><td><input type=\"password\" name=\"user_password\" size=\"15\" maxlength=\"20\"></td></tr>\n";
	    if (extension_loaded("gd") AND ($gfx_chk == 2 OR $gfx_chk == 4 OR $gfx_chk == 5 OR $gfx_chk == 7)) {
		echo "<tr><td colspan='2'>"._SECURITYCODE.": <img src='modules.php?name=$module_name&op=gfx&random_num=$random_num' border='1' alt='"._SECURITYCODE."' title='"._SECURITYCODE."'></td></tr>\n"
		    ."<tr><td colspan='2'>"._TYPESECCODE.": <input type=\"text\" NAME=\"gfx_check\" SIZE=\"7\" MAXLENGTH=\"6\"></td></tr>\n"
		    ."<input type=\"hidden\" name=\"random_num\" value=\"$random_num\">\n";
	    }
	    echo "</table><input type=\"hidden\" name=\"redirect\" value=$redirect>\n"
		."<input type=\"hidden\" name=\"mode\" value=$mode>\n"
		."<input type=\"hidden\" name=\"f\" value=$f>\n"
		."<input type=\"hidden\" name=\"t\" value=$t>\n"
		."<input type=\"hidden\" name=\"op\" value=\"login\">\n"
		."<input type=\"submit\" value=\""._LOGIN."\"></form><br>\n\n"
		."<center><font class=\"content\">[ <a href=\"modules.php?name=$module_name&amp;op=pass_lost\">"._PASSWORDLOST."</a> | <a href=\"modules.php?name=$module_name&amp;op=new_user\">"._REGNEWUSER."</a> ]</font></center>\n";
	    CloseTable();
	}
	include("footer.php");
    } elseif (is_user($user)) {
        global $cookie;
        cookiedecode($user);
        trainingpage($cookie[1]);
    }

}


function trainingpage($username)
{
    global $user, $cookie, $sitename, $prefix, $user_prefix, $db, $admin, $broadcast_msg, $my_headlines, $module_name, $subscription_url;
    $sql = "SELECT * FROM ".$prefix."_bbconfig";
    $result = $db->sql_query($sql);
    while ( $row = $db->sql_fetchrow($result) )
    {
    $board_config[$row['config_name']] = $row['config_value'];
    }

// ==== PICKUP LOGGED-IN USER INFO

    $sql2 = "SELECT * FROM ".$user_prefix."_users WHERE username='$username'";
    $result2 = $db->sql_query($sql2);
    $num = $db->sql_numrows($result2);
    $userinfo = $db->sql_fetchrow($result2);
    if(!$bypass) cookiedecode($user);

// ===== END OF INFO PICKUP

    include("header.php");

    OpenTable();
    displaytopmenu($tid);

// === GRAB TEAM INFORMATION FOR LOGGED-IN USER===


    $teamlogo = $userinfo[user_ibl_team];

    echo "<hr>
    <center><img src=\"online/teamgrfx/$teamlogo.jpg\"><br>
";

$Team_Name = $_POST['Team_Name'];
$k = 0;
if ($Team_Name != NULL )
{
	while ( $k < 3)
	{
	  if ($k == 0)
		{
		  $n_fga = $_POST['fga0'];
		  $n_fgp = $_POST['fgp0'];
		  $n_fta = $_POST['fta0'];
		  $n_ftp = $_POST['ftp0'];
		  $n_tga = $_POST['tga0'];
		  $n_tgp = $_POST['tgp0'];
		  $n_orb = $_POST['orb0'];
		  $n_drb = $_POST['drb0'];
		  $n_ast = $_POST['ast0'];
		  $n_stl = $_POST['stl0'];
		  $n_tvr = $_POST['tvr0'];
		  $n_blk = $_POST['blk0'];
		  $n_oo = $_POST['oo0'];
		  $n_do = $_POST['do0'];
		  $n_po = $_POST['po0'];
		  $n_to = $_POST['to0'];
		  $n_od = $_POST['od0'];
		  $n_dd = $_POST['dd0'];
		  $n_pd = $_POST['pd0'];
		  $n_td = $_POST['td0'];
		  $n_Foul = $_POST['Foul0'];
		  $n_Sta = $_POST['Sta0'];
			$n_total=$n_fga+$n_fgp+$n_fta+$n_ftp+$n_tga+$n_tgp+$n_orb+$n_drb+$n_ast+$n_stl+$n_tvr+$n_blk+$n_oo+$n_do+$n_po+$n_to+$n_od+$n_dd+$n_pd+$n_td+$n_Foul+$n_Sta;

		} elseif ($k == 1) {
		  $n_fga = $_POST['fga1'];
		  $n_fgp = $_POST['fgp1'];
		  $n_fta = $_POST['fta1'];
		  $n_ftp = $_POST['ftp1'];
		  $n_tga = $_POST['tga1'];
		  $n_tgp = $_POST['tgp1'];
		  $n_orb = $_POST['orb1'];
		  $n_drb = $_POST['drb1'];
		  $n_ast = $_POST['ast1'];
		  $n_stl = $_POST['stl1'];
		  $n_tvr = $_POST['tvr1'];
		  $n_blk = $_POST['blk1'];
		  $n_oo = $_POST['oo1'];
		  $n_do = $_POST['do1'];
		  $n_po = $_POST['po1'];
		  $n_to = $_POST['to1'];
		  $n_od = $_POST['od1'];
		  $n_dd = $_POST['dd1'];
		  $n_pd = $_POST['pd1'];
		  $n_td = $_POST['td1'];
		  $n_Foul = $_POST['Foul1'];
		  $n_Sta = $_POST['Sta1'];

			$n_total=$n_fga+$n_fgp+$n_fta+$n_ftp+$n_tga+$n_tgp+$n_orb+$n_drb+$n_ast+$n_stl+$n_tvr+$n_blk+$n_oo+$n_do+$n_po+$n_to+$n_od+$n_dd+$n_pd+$n_td+$n_Foul+$n_Sta;

		} elseif ($k == 2) {
		  $n_fga = $_POST['fga2'];
		  $n_fgp = $_POST['fgp2'];
		  $n_fta = $_POST['fta2'];
		  $n_ftp = $_POST['ftp2'];
		  $n_tga = $_POST['tga2'];
		  $n_tgp = $_POST['tgp2'];
		  $n_orb = $_POST['orb2'];
		  $n_drb = $_POST['drb2'];
		  $n_ast = $_POST['ast2'];
		  $n_stl = $_POST['stl2'];
		  $n_tvr = $_POST['tvr2'];
		  $n_blk = $_POST['blk2'];
		  $n_oo = $_POST['oo2'];
		  $n_do = $_POST['do2'];
		  $n_po = $_POST['po2'];
		  $n_to = $_POST['to2'];
		  $n_od = $_POST['od2'];
		  $n_dd = $_POST['dd2'];
		  $n_pd = $_POST['pd2'];
		  $n_td = $_POST['td2'];
		  $n_Foul = $_POST['Foul2'];
		  $n_Sta = $_POST['Sta2'];

			$n_total=$n_fga+$n_fgp+$n_fta+$n_ftp+$n_tga+$n_tgp+$n_orb+$n_drb+$n_ast+$n_stl+$n_tvr+$n_blk+$n_oo+$n_do+$n_po+$n_to+$n_od+$n_dd+$n_pd+$n_td+$n_Foul+$n_Sta;

		}
	  $k++;

	  if ($n_fga < 1 || $n_fgp < 1 || $n_fta < 1 || $n_ftp < 1 || $n_tga < 1 || $n_tgp < 1 || $n_orb < 1 || $n_drb < 1 || $n_ast < 1 || $n_stl < 1 || $n_tvr < 1 || $n_blk < 1 || $n_oo < 1 || $n_do < 1 || $n_po < 1 || $n_to < 1 || $n_od < 1 || $n_dd < 1 || $n_pd < 1 || $n_td < 1 || $n_Foul < 1 || $n_Sta < 1)
		{
		  echo "<b>Error!  Each category has to have more than 0 points allocated!</b>";
		} elseif ($n_fga > 7 || $n_fgp > 7 || $n_fta > 7 || $n_ftp > 7 || $n_tga > 7 || $n_tgp > 7 || $n_orb > 7 || $n_drb > 7 || $n_ast > 7 || $n_stl > 7 || $n_tvr > 7 || $n_blk > 7 || $n_oo > 7 || $n_do > 7 || $n_po > 7 || $n_to > 7 || $n_od > 7 || $n_dd > 7 || $n_pd > 7 || $n_td > 7 || $n_Foul > 7 || $n_Sta > 7) {
			 echo "<b>Cheater!  Each category can't have more than 7 points allocated!</b>";
		} else {

		  if ($n_total < 89)
		  {
			$sqla = "UPDATE nuke_ibl_training SET `2ga` = '$n_fga', `2gp` = '$n_fgp', `fta` = '$n_fta', `ftp` = '$n_ftp', `3ga` = '$n_tga', `3gp` = '$n_tgp', `orb` = '$n_orb', `drb` = '$n_drb', `ast` = '$n_ast', `stl` = '$n_stl', `tvr` = '$n_tvr', `blk` = '$n_blk', `oo` = '$n_oo', `do` = '$n_do', `po` = '$n_po', `to` = '$n_to', `od` = '$n_od', `dd` = '$n_dd', `pd` = '$n_pd', `td` = '$n_td', `Foul` = '$n_Foul', `Sta` = '$n_Sta' WHERE `teamname` = '$teamlogo' AND `training_set` = '$k'";
			$resultadj= $db->sql_query($sqla);
		  }
		  if ($n_total > 88)
		  {
			echo "Error!  Too many training points allocated (you allocated $n_total, you can only allocate 88). Changes not committed!";
		  }
		}
	}
}

    $sqltr = "SELECT * FROM nuke_ibl_training WHERE teamname = '$teamlogo' ";
    $resulttr = $db->sql_query($sqltr);
	$numtr = $db->sql_numrows($resulttr);
	$i = 0;

	echo "<form name = \"Training\" method=\"post\" action=\"modules.php?name=Team&op=training\">
	<input type=\"hidden\" name=\"Team_Name\" value=\"$teamlogo\">
	<table border=1><tr>";

	while ($i < $numtr)
	{
	 $training_pref_set=mysql_result($resulttr,$i,"training_set");
     $fga=mysql_result($resulttr,$i,"2ga");
     $fgp=mysql_result($resulttr,$i,"2gp");
     $fta=mysql_result($resulttr,$i,"fta");
     $ftp=mysql_result($resulttr,$i,"ftp");
     $tga=mysql_result($resulttr,$i,"3ga");
     $tgp=mysql_result($resulttr,$i,"3gp");
     $orb=mysql_result($resulttr,$i,"orb");
     $drb=mysql_result($resulttr,$i,"drb");
     $ast=mysql_result($resulttr,$i,"ast");
     $stl=mysql_result($resulttr,$i,"stl");
     $tvr=mysql_result($resulttr,$i,"tvr");
     $blk=mysql_result($resulttr,$i,"blk");

     $oo=mysql_result($resulttr,$i,"oo");
     $do=mysql_result($resulttr,$i,"do");
     $po=mysql_result($resulttr,$i,"po");
     $to=mysql_result($resulttr,$i,"to");
     $od=mysql_result($resulttr,$i,"od");
     $dd=mysql_result($resulttr,$i,"dd");
     $pd=mysql_result($resulttr,$i,"pd");
     $td=mysql_result($resulttr,$i,"td");
     $Foul=mysql_result($resulttr,$i,"Foul");
     $Sta=mysql_result($resulttr,$i,"Sta");



	if ($training_pref_set == 1)
		{
			$traintotal11=$fga+$fgp+$fta+$ftp+$tga+$tgp+$orb+$drb+$ast+$stl+$tvr+$blk+$oo+$do+$po+$to+$od+$dd+$pd+$td+$Foul+$Sta;
			echo "<td><table border=1><tr><th colspan=2><center>Total Training Points Used: $traintotal11 of 88 maximum<br><br>Guards (PG, G and SG)</center></th></tr>";

		} elseif ($training_pref_set == 2) {
			$traintotal12=$fga+$fgp+$fta+$ftp+$tga+$tgp+$orb+$drb+$ast+$stl+$tvr+$blk+$oo+$do+$po+$to+$od+$dd+$pd+$td+$Foul+$Sta;
			echo "<td><table border=1><tr><th colspan=2><center>Total Training Points Used: $traintotal12 of 88 maximum<br><br>Swingmen (GF, SF and F)</center></th></tr>";
		} elseif ($training_pref_set == 3) {
			$traintotal13=$fga+$fgp+$fta+$ftp+$tga+$tgp+$orb+$drb+$ast+$stl+$tvr+$blk+$oo+$do+$po+$to+$od+$dd+$pd+$td+$Foul+$Sta;
			echo "<td><table border=1><tr><th colspan=2><center>Total Training Points Used: $traintotal13 of 88 maximum<br><br>Bigmen (PF, FC and C)</center></th></tr>";
		}
	echo "<tr><td><center>Create Two-Point Shots</center></td><td><center><input type=\"text\" name=\"fga$i\" size=\"1\" value=\"$fga\"></center></td></tr>
	<tr><td><center>Two-Point Accuracy</center></td><td><center><input type=\"text\" name=\"fgp$i\" size=\"1\" value=\"$fgp\"></center></td></tr>
	<tr><td><center>Draw Fouls</center></td><td><center><input type=\"text\" name=\"fta$i\" size=\"1\" value=\"$fta\"></center></td></tr>
	<tr><td><center>Free Throw Accuracy</center></td><td><center><input type=\"text\" name=\"ftp$i\" size=\"1\" value=\"$ftp\"></center></td></tr>
	<tr><td><center>Create Three-point Shots</center></td><td><center><input type=\"text\" name=\"tga$i\" size=\"1\" value=\"$tga\"></center></td></tr>
	<tr><td><center>Three-Point accuracy</center></td><td><center><input type=\"text\" name=\"tgp$i\" size=\"1\" value=\"$tgp\"></center></td></tr>
	<tr><td><center>Offensive Rebounding</center></td><td><center><input type=\"text\" name=\"orb$i\" size=\"1\" value=\"$orb\"></center></td></tr>
	<tr><td><center>Defensive Rebounding</center></td><td><center><input type=\"text\" name=\"drb$i\" size=\"1\" value=\"$drb\"></center></td></tr>
	<tr><td><center>Passing/Assists</center></td><td><center><input type=\"text\" name=\"ast$i\" size=\"1\" value=\"$ast\"></center></td></tr>
	<tr><td><center>Steals</center></td><td><center><input type=\"text\" name=\"stl$i\" size=\"1\" value=\"$stl\"></center></td></tr>
	<tr><td><center>Avoid Turnovers</center></td><td><center><input type=\"text\" name=\"tvr$i\" size=\"1\" value=\"$tvr\"></center></td></tr>
	<tr><td><center>Block Shots</center></td><td><center><input type=\"text\" name=\"blk$i\" size=\"1\" value=\"$blk\"></center></td></tr>
	<tr><td><center>Outside Offense</center></td><td><center><input type=\"text\" name=\"oo$i\" size=\"1\" value=\"$oo\"></center></td></tr>
	<tr><td><center>Drive Offense</center></td><td><center><input type=\"text\" name=\"do$i\" size=\"1\" value=\"$do\"></center></td></tr>
	<tr><td><center>Post Offense</center></td><td><center><input type=\"text\" name=\"po$i\" size=\"1\" value=\"$po\"></center></td></tr>
	<tr><td><center>Transition Offense</center></td><td><center><input type=\"text\" name=\"to$i\" size=\"1\" value=\"$to\"></center></td></tr>
	<tr><td><center>Outside Defense</center></td><td><center><input type=\"text\" name=\"od$i\" size=\"1\" value=\"$od\"></center></td></tr>
	<tr><td><center>Drive Defense</center></td><td><center><input type=\"text\" name=\"dd$i\" size=\"1\" value=\"$dd\"></center></td></tr>
	<tr><td><center>Post Defense</center></td><td><center><input type=\"text\" name=\"pd$i\" size=\"1\" value=\"$pd\"></center></td></tr>
	<tr><td><center>Transition Defense</center></td><td><center><input type=\"text\" name=\"td$i\" size=\"1\" value=\"$td\"></center></td></tr>
	<tr><td><center>Avoid Fouls</center></td><td><center><input type=\"text\" name=\"Foul$i\" size=\"1\" value=\"$Foul\"></center></td></tr>
	<tr><td><center>Stamina</center></td><td><center><input type=\"text\" name=\"Sta$i\" size=\"1\" value=\"$Sta\"></center></td></tr></table></td>";
	$i++;
	}
echo"</tr>
<tr><td colspan=3><center><input type=\"submit\" value=\"Process Changes\"></center></form></td></tr>
</table>
As a reminder, you should enter between 1 and 7 for each training value.  It is suggested that in order to make sure your training is legal, that you first lower one value before raising another; if you try to submit training with more than 88 total points, it will be illegal and none of your changes will be saved (though depending on your browser cache, the 'BACK' button may help you out!
";

    CloseTable();
    include("footer.php");

}


/************************************************************************/
/* END DISPLAY TRAINING PREFERENCES                                     */
/************************************************************************/


switch($op) {

    case "changeset":
    changeset($user);
    break;

    case "seteditor":
    seteditor($user);
    break;

    case "training":
    viewtraining($user);
    break;

    case "team":
    team($tid);
    break;

    case "schedule":
	schedule($tid);
    break;

    case "reviewtrades":
    reviewtrade($user);
    break;

    case "injuries":
    viewinjuries();
    break;

    case "offertrade":
    offertrade($user);
    break;

    case "drafthistory":
    drafthistory($tid);
    break;

    case "waivers":
    waivers($user, $action);
    break;

    default:
    menu();
    break;

}

// Remove the double-slashes from the cases above for the off-season

?>
